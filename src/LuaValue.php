<?php

namespace iggyvolz\Lua;

use ArrayAccess;
use Closure;
use JsonSerializable;
use LogicException;
use Stringable;
use Throwable;
use WeakMap;

abstract class LuaValue implements Stringable, JsonSerializable, ArrayAccess
{
    public function __debugInfo(): ?array
    {
        return ["index" => $this->index, "value" => $this->value()];
    }

    /**
     * @var WeakMap<LuaState,int[]> Array of free indeces in the stack
     */
    private static WeakMap $freeIndeces;

    private static function getFreeIndex(LuaState $lua): ?int
    {
        self::$freeIndeces ??= new WeakMap();
        $freeIndeces = self::$freeIndeces[$lua] ?? [];
        if(empty($freeIndeces)) {
            return null;
        } else {
            $freeIndex = array_pop($freeIndeces);
            self::$freeIndeces[$lua] = $freeIndeces;
            return $freeIndex;
        }
    }

    //  Can't test  from here because MethodBalance relies on this
    public static function countFreeIndeces(LuaState $lua): int
    {
        self::$freeIndeces ??= new WeakMap();
        $freeIndeces = self::$freeIndeces[$lua] ?? [];
        return count($freeIndeces);
    }

    private static function addFreeIndex(LuaState $lua, int $index): void
    {
        $index = $lua->luaCall("lua_absindex",$index);
        self::$freeIndeces ??= new WeakMap();
        $freeIndeces = self::$freeIndeces[$lua] ?? [];
        $freeIndeces[] = $index;
        self::$freeIndeces[$lua] = $freeIndeces;
    }

    private static function removeFreeIndex(LuaState $lua, int $index): void
    {
        $index = $lua->luaCall("lua_absindex",$index);
        self::$freeIndeces ??= new WeakMap();
        $freeIndeces = self::$freeIndeces[$lua] ?? [];
        $freeIndeces = array_diff($freeIndeces, [$index]);
        self::$freeIndeces[$lua] = $freeIndeces;
    }
    /** @internal  */
    public static function isFreeIndex(LuaState $lua, int $index): int
    {
        $index = $lua->luaCall("lua_absindex",$index);
        self::$freeIndeces ??= new WeakMap();
        $freeIndeces = self::$freeIndeces[$lua] ?? [];
        return in_array($index, $freeIndeces);
    }

    public function getMetatable(bool $managed = true): ?LuaTable
    {
        return $this->lua->luaCall("lua_getmetatable",$this->index) ? new LuaTable($this->lua, -1, $managed) : null;
    }

    public function setMetatable(string $key, mixed $value): void
    {
        $metatable = $this->getMetatable(false) ?? LuaValue::new($this->lua, [], managed: false, forcetop: true);
        $metatable[$key] = $value;
        $this->lua->luaCall("lua_setmetatable",$this->index);
    }


    public /* **mostly*** readonly */ int $index; // needs to be modified in __clone
    /** @internal */
    public function __construct(public readonly LuaState $lua, int $index, protected bool $managed = true)
    {
        $this->index = $lua->luaCall("lua_absindex",$index);
//        var_dump("Create " . spl_object_id($this) . ", " . ($this->managed ? "managed" : "unmanaged") . ", at index $index on state " . spl_object_id($lua));
//        debug_print_backtrace();
        if($this->index <= 0) {
            debug_print_backtrace();
            var_dump(get_debug_type($this));
            throw new LogicException("Attempted to read a value below the stack");
        }
    }

    /** @internal  */
    public static function fromIndex(LuaState $lua, int $index, bool $managed = true): self
    {
        return match($lua->luaCall("lua_type",$index)) {
            0 => new LuaNil($lua, $index, $managed), // NIL
            1 => new LuaBoolean($lua, $index, $managed), // BOOLEAN
            2 => new LuaLightUserdata($lua, $index, $managed), // LIGHTUSERDATA
            3 => $lua->luaCall("lua_isinteger", $index) ? new LuaInt($lua, $index, $managed) : new LuaNumber($lua, $index, $managed), // NUMBER
            4 => new LuaString($lua, $index, $managed), // STRING
            5 => new LuaTable($lua, $index, $managed), // TABLE
            6 => new LuaFunction($lua, $index, $managed), // FUNCTION
            7 => new LuaUserdata($lua, $index, $managed), // USERDATA
            8 => new LuaThread($lua, $index, $managed), // THREAD
        };
    }

    
    /**
     * @param LuaState $lua Lua environment to use
     * @param mixed $value Value to push
     * @param bool $isUserData Whether to force the value to be userdata
     * @param bool $managed Whether the value should be managed
     * @param bool $forcetop Whether to force the value to be on top of the queue
     * @return static
     *@internal
     * Push a value to the lua stack
     */
    public static function new(LuaState $lua, mixed $value, bool $isUserData = false, bool $managed = true, bool $forcetop = false): self
    {
        if(!$isUserData && $value instanceof LuaValue) {
            if($lua->equals($value->lua)) {
                // Value already exists and is in the same lua state, we just need to perform a copy
                // Push a null
                $index = self::new($lua, null, managed: false, forcetop: $forcetop)->index;
                // Copy our object into that slot
                $lua->luaCall("lua_copy",$value->index, $index);
                // Return a new reference at the new index
                $class = get_class($value);
                return new $class($lua, $index, $managed);
            } else {
                if($value->value() !== $value) {
                    // Perform a copy
                    return self::new($lua, $value->value(), $isUserData, $managed, $forcetop);
                }
//                throw new LogicException(get_class($value));
                // Agh we need to move the object across lua states
                $copyOnSource = $value->clone(false, true);
                // Move object to desired state
//                echo __LINE__ . ": " . $value->lua . ";$lua" . PHP_EOL;
                $value->lua->luaCall("lua_xmove", $lua, 1);
//                echo __LINE__ . ": " . $value->lua . ";$lua" . PHP_EOL;
                return LuaValue::fromIndex($lua, -1, managed: $managed);
            }
        } else {
            // We actually have to push it
            $class = $isUserData ? LuaUserData::class : match(get_debug_type($value)) {
                "bool" => LuaBoolean::class,
                "int" => LuaInt::class,
                "null" => LuaNil::class,
                "float" => LuaNumber::class,
                "string" => LuaString::class,
                "array" => LuaTable::class,
                Closure::class => LuaFunction::class,
                default => LuaUserdata::class,
            };
            if($lua->luaCall("lua_checkstack",1) === 0) {
                throw new LuaException("Out of memory");
            }
            $class::_push($lua, $value);
//            var_dump([$class, $lua->count()]);
            $index = -1;
        }
        if(!$forcetop && !is_null($freeIndex = self::getFreeIndex($lua))) {
            // Copy to the new free index
            $lua->luaCall("lua_copy",$index, $freeIndex);
            // Free the old index
            self::addFreeIndex($lua, $index);
        }
        return new $class($lua, $index, $managed);
    }

    protected static abstract function _push(LuaState $lua, mixed $value): void;

    /** @internal */
    public function free(): void
    {
        if($this->index === 3 && $this->lua === $GLOBALS["lua"]) debug_print_backtrace();
        self::addFreeIndex($this->lua, $this->index);
        // Pop as many values as are on the top of the stack
        while(self::isFreeIndex($this->lua, $this->lua->luaCall("lua_gettop"))) {
            self::removeFreeIndex($this->lua, $this->lua->luaCall("lua_gettop"));
            // lua_pop(1)
            $this->lua->luaCall("lua_settop",-2);
        }
    }

    public function __destruct()
    {
        if($this->managed) {
            $this->free();
        }
    }
    public function jsonSerialize(): mixed
    {
        return $this->value();
    }

    public abstract function value(): mixed;

    /**
     * @throws Throwable
     */
    public function offsetExists(mixed $offset): bool
    {
        return !($this->offsetGet($offset) instanceof LuaNil);
    }

    /**
     * @throws Throwable
     */
    public function offsetGet(mixed $offset): ?LuaValue
    {
        return $this->lua->protectedCall(function(LuaState $lua){
            $lua->luaCall("lua_gettable", -2);
            return LuaValue::fromIndex($lua, -1);
        }, $this, $offset);
    }

    /**
     * @throws Throwable
     */
    public function rawGet(mixed $offset): ?LuaValue
    {
        return $this->lua->protectedCall(function(LuaState $lua): LuaValue{
            $lua->luaCall("lua_rawget", 1);
            return LuaValue::fromIndex($lua, -1);
        }, $this, $offset);
    }

    /**
     * @throws Throwable
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->lua->protectedCall(function(LuaState $lua): void{
            // table, key, value
            $lua->luaCall("lua_settable", 1);
        }, $this, $offset, $value);
    }

    /**
     * @throws Throwable
     */
    public function rawSet(mixed $offset, mixed $value): void
    {
        $this->lua->protectedCall(function(LuaState $lua): void{
            // table, key, value
            $lua->luaCall("lua_rawset", 1);
        }, $this, $offset, $value);
    }

    /**
     * @throws Throwable
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->offsetSet($offset, null);
    }


    public function clone(bool $managed = true, bool $forceTop = false): self
    {
        return LuaValue::new($this->lua, $this, managed: $managed, forcetop: $forceTop);
    }
    public function __clone(): void
    {
        $this->index = LuaValue::new($this->lua, $this, managed: false)->index;
    }
}