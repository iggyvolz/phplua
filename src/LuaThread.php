<?php

namespace iggyvolz\Lua;

use LogicException;

final class LuaThread extends LuaValue
{
    public function value(): string
    {
        return "<thread>";
    }

    public function __toString(): string
    {
        return "<thread>";
    }

    protected static function _push(LuaState $lua, mixed $value): void
    {
        throw new LogicException("Threads cannot be pushed");
    }
}