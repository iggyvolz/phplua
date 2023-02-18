<?php

namespace iggyvolz\Lua;

final class LuaNil extends LuaValue
{
    
    public static function _push(LuaState $lua, mixed $value): void
    {
        $lua->luaCall("lua_pushnil");
    }
    public function __toString(): string
    {
        return "<nil>";
    }

    public function value(): ?int
    {
        return null;
    }
}