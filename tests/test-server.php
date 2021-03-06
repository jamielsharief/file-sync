<?php
/**
 * FileSync
 * Copyright 2020 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright   Copyright (c) Jamiel Sharief
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */

 /**
  * This is the test server for the PHPUnit tests
  */
use FileSync\Server;

define('ROOT', dirname(__DIR__, 1));

require ROOT . '/vendor/autoload.php';
$server = new Server(ROOT . '/tests/Fixture/keys');
$server->dispatch(ROOT . '/tests/Fixture/data');
