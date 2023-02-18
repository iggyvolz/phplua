<?php

use iggyvolz\Lua\LuaState;
use Tester\Environment;

require_once __DIR__ . "/../vendor/autoload.php";
Environment::setup();
define("LUA", LuaState::new(__DIR__ . "/lua.so"));