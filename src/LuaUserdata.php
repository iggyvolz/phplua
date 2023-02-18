<?php

namespace iggyvolz\Lua;

use FFI;
use FFI\CData;

final class LuaUserdata extends LuaValue
{
    public function __construct(LuaState $lua, int $index, bool $managed = true)
    {
        parent::__construct($lua, $index, $managed);
        // Set initial metatable
        $this->setMetatable("__gc", function(LuaUserdata $x) {
                unset(self::$values[self::getAddress($this->lua->luaCall("lua_touserdata",$x->index))]);
            }
        );
    }

    private static array $values = [];

    /**
     * Creates a new userdata
     * @param array<string,mixed> $metatable
     * @param mixed $associatedValue Associated ->value() to assign
     * @return static
     */
    public static function create(LuaState $lua, array $metatable, mixed $associatedValue = null, bool $managed = false, bool $forcetop = false): self
    {
        /** @var self $self */
        $self = LuaValue::new($lua, $associatedValue, true, $managed, $forcetop);
        foreach($metatable as $key => $entry) {
            $self->setMetatable($key, $entry);
        }
        return $self;
    }

    private static function getAddress(CData $userdataptr): int
    {
        $x = FFI::new("size_t");
        FFI::memcpy(FFI::addr($x), FFI::addr($userdataptr), 8);
        return $x->cdata;
    }

    
    public function value(): mixed
    {
        return self::$values[self::getAddress($this->lua->luaCall("lua_touserdata",$this->index))] ?? null;
    }

    public function __toString(): string
    {
        return "<userdata>";
    }

    
    protected static function _push(LuaState $lua, mixed $value): void
    {
        $ret = $lua->luaCall("lua_newuserdatauv",0, 0);
        $address = self::getAddress($ret);
        if(!is_null($value)) {
            self::$values[$address] = $value;
        }
    }
}