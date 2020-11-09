<?php
/**
 * DEMO Script
 *
 * The demo will create a folder called dest and sync the contents from src.
 *
 * Instructions:
 *
 *  $ php -S localhost:8000
 *  $ php demo.php
 */

use FileSync\Client;
use FileSync\Server;

require __DIR__ . '/vendor/autoload.php';

$keyPath = __DIR__ . '/tests/fixture/keys';
$srcPath = __DIR__ . '/src';
$destPath = __DIR__ . '/dest';

@mkdir($destPath);

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    $client = new Client($keyPath);
 
    try {
        $client->dispatch('http://localhost:8000/demo.php', 'demo@example.com', $destPath, ['checksum' => true]);
    } catch (Exception $exception) {
        echo $exception->getMessage() . "\n";
    }
} else {
    $server = new Server($keyPath);
    $server->dispatch($srcPath);
}
