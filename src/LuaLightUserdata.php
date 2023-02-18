<?php

namespace iggyvolz\Lua;

use LogicException;

final class LuaLightUserdata extends LuaValue
{
    public function value(): string
    {
        return "<lightuserdata>";
    }

    public function __toString(): string
    {
        return "<lightuserdata>";
    }

    protected static function _push(LuaState $lua, mixed $value): void
    {
        throw new LogicException("Light userdata objects cannot be pushed");
    }
}