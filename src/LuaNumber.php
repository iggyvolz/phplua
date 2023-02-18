<?php

namespace iggyvolz\Lua;

final class LuaNumber extends LuaValue
{
    
    public function value(): float
    {
        return $this->lua->luaCall("lua_tonumberx",$this->index, null);
    }
    public function __toString(): string
    {
        return "<num>" . $this->value();
    }
    
    protected static function _push(LuaState $lua, mixed $value): void
    {
        $lua->luaCall("lua_pushnumber",$value);
    }
}