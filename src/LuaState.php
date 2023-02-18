<?php

namespace iggyvolz\Lua;

use ArrayAccess;
use Closure;
use Countable;
use FFI;
use IteratorAggregate;
use LogicException;
use Throwable;
use Traversable;

final readonly class LuaState implements IteratorAggregate, Countable, ArrayAccess
{
    public function __construct(
        public FFI        $lua,
        private FFI\CData $state,
        private bool      $managed = true
    ) {
        $this->setPanicFunction(function(string $error) {
            throw new LuaException($error);
        });
    }

    public static function new(string $luaBinary): self
    {
        $ffi = FFI::cdef(file_get_contents(__DIR__ . "/lua.h"), $luaBinary);
        return new self($ffi, $ffi->luaL_newstate());
    }

    public function __destruct()
    {
        if($this->managed) {
            $this->luaCall("lua_close");
        }
    }

    /** @internal  */
    public function luaCall(string $name, mixed ...$arguments): mixed
    {
        $arguments = array_map(fn($x) => $x instanceof self ? $x->state : $x, $arguments);
        return $this->lua->$name($this->state, ...$arguments);
    }

    /**
     * @throws Throwable
     */
    public function protectedCall(Closure $closure, mixed ...$arguments): ?LuaValue
    {
        $fun = (LuaValue::new($this, $closure, managed: false, forcetop: true));
        if(!$fun instanceof LuaFunction) {
            throw new LogicException();
        }
        return $fun->invokeAndFree(...$arguments)[0] ?? null;
    }

    
    public function __toString(): string
    {
        return implode(", ", iterator_to_array($this));
    }

    
    public function getIterator(): Traversable
    {
        for($i = 1; $i<=$this->luaCall("lua_gettop"); $i++) {
            if(LuaValue::isFreeIndex($this, $i)) {
                yield "<free>";
            } else {
                yield LuaValue::fromIndex($this, $i, false);
            }
        }
    }

    
    /**
     * @param Closure(string):void $function
     * @return void
     */
    public function setPanicFunction(Closure $function): void
    {
        $this->luaCall("lua_atpanic", LuaFunction::wrap($this, function(LuaState $luaState) use($function): void{
            $function(LuaValue::fromIndex($luaState, -1, managed: false)->value());
        }));
    }

    /**
     * @return list<LuaValue>
     * @throws Throwable
     */
    public function execute(string $code, array $params = [], ?string $name = null): array
    {
        if(is_null($name)) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            ["line" => $line, "file" => $file] = array_pop($bt);
            $file = basename($file);
            $name = "$file:$line";
        }
        return LuaFunction::fromString($this, $code, name: $name, managed: false)->invokeAndFree(...$params);
    }

    /**
     * @throws Throwable
     */
    public function executeFile(string $file, array $params = []): array
    {
        return $this->execute(file_get_contents($file), $params, $file);
    }

    
    public function setGlobal(string $name, mixed $value): void
    {
        LuaValue::new($this, $value, managed: false, forcetop: true);
        $this->luaCall("lua_setglobal",$name);
    }

    
    public function getGlobal(string $name): LuaValue
    {
        $this->luaCall("lua_getglobal", $name);
        return LuaValue::fromIndex($this, -1);
    }

//  Can't test  from here because MethodBalance relies on this
    public function count(): int
    {
        return $this->luaCall("lua_gettop") - LuaValue::countFreeIndeces($this);
    }

    private function absindex(int $index): int
    {
        return ($index > 0) ? $index : $this->luaCall("lua_absindex", $index);
    }

    public function offsetExists(mixed $offset): bool
    {
        if(!is_int($offset)) return false;
        $offset = $this->absindex($offset);
        if(LuaValue::isFreeIndex($this, $offset)) return false;
        if($this->luaCall("lua_gettop") < $offset || $offset === 0) return false;
        return true;
    }

    public function offsetGet(mixed $offset): ?LuaValue
    {
        if(!$this->offsetExists($offset)) {
            return null;
        }
        return LuaValue::fromIndex($this, $offset, false);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException("Cannot set items directly on the Lua stack");
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException("Cannot unset items directly on the Lua stack");
    }

    public function equals(LuaState $lua): bool
    {
        return $lua->state === $this->state;
    }
}