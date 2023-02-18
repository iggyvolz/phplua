<?php

namespace iggyvolz\Lua;

final class LuaTable extends LuaValue
{

    public function __toString(): string
    {
        return "<table>";
    }

    
    public function value(bool $recurse = false): array
    {
        $data = [];
        LuaValue::new($this->lua, null, managed: false, forcetop: true);
        while($this->lua->luaCall("lua_next", $this->index)) {
            $key = LuaValue::fromIndex($this->lua, -2, managed: false);
            $value = LuaValue::fromIndex($this->lua, -1, managed: false);
            $data[$key->value()] = match(true) {
                $recurse && $value instanceof self => $value->value(true),
                $recurse => $value->value(),
                default => $value
            };
            $this->lua->luaCall("lua_settop", -2);
        }
        return $data;
    }

    
    protected static function _push(LuaState $lua, mixed $value): void
    {
        $lua->luaCall("lua_createtable",0, 0);
        $self = new self($lua, -1, false);
        foreach($value as $k => $v) {
            $self[$k] = $v;
        }
    }
}