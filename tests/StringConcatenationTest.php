<?php /** @noinspection PhpUnhandledExceptionInspection */

use iggyvolz\Lua\LuaState;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__  . "/bootstrap.php";
LUA->setGlobal("hello", "hello");
LUA->setGlobal("world", "world");
[$helloworld] = LUA->execute(<<<lua
return hello .. " " .. world
lua
);
Assert::same("hello world", $helloworld->value());
