<?php

namespace iggyvolz\Lua;

final class LuaBoolean extends LuaValue
{
    
    public function value(): bool
    {
        return $this->lua->luaCall("lua_toboolean",$this->index);
    }
    
    public function __toString(): string
    {
        return "<bool>" . ($this->value() ? "true" : "false");
    }

    
    protected static function _push(LuaState $lua, mixed $value): void
    {
        $lua->luaCall("lua_pushboolean",$value ? 1 : 0);
    }
}