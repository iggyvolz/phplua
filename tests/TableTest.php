<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection PhpUnhandledExceptionInspection */

use iggyvolz\Lua\LuaState;
use iggyvolz\Lua\LuaTable;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . "/bootstrap.php";
LUA->setGlobal("table", ["foo" => $inner = ["bin" => "bar", 2 => "bak"]]);
[$table] = LUA->execute(<<<lua
return table.foo
lua
);
Assert::type(LuaTable::class, $table);
$table = $table->value(true);
Assert::equal($inner, $table);
