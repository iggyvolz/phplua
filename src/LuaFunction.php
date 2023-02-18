<?php

namespace iggyvolz\Lua;

use Closure;
use FFI\CData;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use Throwable;

final class LuaFunction extends LuaValue
{
    private bool $isWrapped = false;
    public function value(): self
    {
        return $this;
    }
    public function jsonSerialize(): string
    {
        return "<function>";
    }

    public function __toString(): string
    {
        return "<function>";
    }

    
    protected static function _push(LuaState $lua, mixed $value): void
    {
        $lua->luaCall("lua_pushcclosure",self::wrap($lua, $value), 0);
    }

    public static function wrap(LuaState $lua, Closure $closure): Closure
    {
        return function(CData $state) use($lua, $closure){
            $state = new LuaState($lua->lua, $state, false);
            $refl = new ReflectionFunction($closure);
            $params = [];
            for($i = 0; $i < $refl->getNumberOfParameters(); $i++) {
                $type = $refl->getParameters()[$i]->getType();
                if($type instanceof ReflectionNamedType && ($type->getName() === LuaValue::class || is_subclass_of($type->getName(), LuaValue::class))) {
                    $params[] = LuaValue::fromIndex($state, $i + 1, managed: false);
                } elseif($type instanceof ReflectionNamedType && $type->getName() === LuaState::class) {
                    $params[] = $state;
                } else {
                    $params[] = LuaValue::fromIndex($state, $i + 1, managed: false)->value();
                }
            }
            try {
                $ret = $closure(...$params);
            } catch(Throwable $throwable) {
                // Push exception to the top
                LuaValue::new($state, $throwable, true, false, true);
                // Trigger an error
                $state->luaCall("lua_error");
                // Never returns
                throw new LogicException();
            }
            if($refl->getReturnType() instanceof ReflectionNamedType && $refl->getReturnType()->getName() === "void") {
                $ret = [];
            }
            if(!is_array($ret)) {
                $ret = [$ret];
            }
            foreach($ret as $value) {
                LuaValue::new($state, $value, false, false, true);
            }
            return count($ret);
        };
    }

    
    public static function fromString(LuaState $lua, string $code, string $name, bool $managed = true): self
    {
        $lua->luaCall("luaL_loadbufferx", $code, strlen($code), $name, null);
        return new self($lua, -1, managed: $managed);
    }

    
    /**
     * @param mixed ...$params
     * @return list<LuaValue>
     * @throws Throwable
     * @internal
     * Invokes the function (must be on the top of the stack) and frees it from the stack (aka doesn't make a copy)
     */
    public function invokeAndFree(mixed ...$params): array
    {
        if($this->managed) {
            throw new LogicException("Cannot invokeAndFree a managed function");
        }
        $top = $this->lua->luaCall("lua_gettop");
        if($this->index !== $top) {
            throw new LogicException("Cannot invokeAndFree a function not on top");
        }
        foreach ($params as $param) {
            LuaValue::new($this->lua, $param, false, false, true);
        }
        if($this->lua->luaCall("lua_pcallk",count($params), -1, 0, 0, null)) {
            $value = LuaValue::fromIndex($this->lua, -1)->value();
            if($value instanceof Throwable) {
                throw $value;
            }
            if(is_string($value)) {
                throw new LuaException($value);
            }
            throw new LuaException("Unhandled error type " . get_debug_type($value));
        }
        $returns = [];
        // Be sure to create *managed* LuaValue pointers from this point on
        for($i = $top; $i <= $this->lua->luaCall("lua_gettop"); $i++) {
            $returns[] = LuaValue::fromIndex($this->lua, $i);
        }
        return $returns;
    }

    
    /** @return list<LuaValue>
     * @throws Throwable
     */
    public function __invoke(mixed ...$params): array
    {
        return ($this->clone(managed: false, forceTop: true))->invokeAndFree(...$params);
    }
}