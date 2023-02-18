<?php /** @noinspection PhpUnhandledExceptionInspection */

use Tester\Assert;

require_once __DIR__ . "/bootstrap.php";
LUA->setGlobal("six", 6);
LUA->execute(<<<lua
twelve=six+six
lua);
Assert::same(12, LUA->getGlobal("twelve")->value());
