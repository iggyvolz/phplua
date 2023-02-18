<?php

namespace iggyvolz\Lua;

final class LuaInt extends LuaValue
{
    
    public function value(): int
    {
        return $this->lua->luaCall("lua_tointegerx",$this->index, null);
    }
    public function __toString(): string
    {
        return "<int>" . $this->value();
    }
    
    protected static function _push(LuaState $lua, mixed $value): void
    {
        $lua->luaCall("lua_pushinteger",$value);
    }
}