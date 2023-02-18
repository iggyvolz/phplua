<?php

namespace iggyvolz\Lua;

use FFI;

final class LuaString extends LuaValue
{
    
    public function value(): string
    {
        $length = FFI::new("uint64_t");
        $str = $this->lua->luaCall("lua_tolstring",$this->index, FFI::addr($length));
        return FFI::string($str, $length->cdata);
    }
    public function __toString(): string
    {
        return "<string>" . $this->value();
    }

    
    protected static function _push(LuaState $lua, mixed $value): void
    {
        $lua->luaCall("lua_pushlstring",$value, strlen($value));
    }
}