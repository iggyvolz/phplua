<?php /** @noinspection PhpUnhandledExceptionInspection */

use iggyvolz\Lua\LuaState;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . "/bootstrap.php";

$x=0;
LUA->setGlobal("func", function(){
    global $x;
    $x++;
});
LUA->execute(<<<lua
func()
func()
func()
lua);
Assert::same(3, $x);