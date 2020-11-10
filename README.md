# File Sync (alpha)

![license](https://img.shields.io/badge/license-MIT-brightGreen.svg)
[![Build Status](https://travis-ci.com/jamielsharief/file-sync.svg?branch=main)](https://travis-ci.com/jamielsharief/file-sync)
[![Coverage Status](https://coveralls.io/repos/github/jamielsharief/file-sync/badge.svg?branch=main)](https://coveralls.io/github/jamielsharief/file-sync?branch=main)

A HTTP file syncing library that uses public/private keys for authorization.

This library can be used to install or update applications from private sources, sync data files or for any other reason that you can think off where you want `rsync` functionality but to be able to control it from PHP easily.

## Setup

Create the script on the remote server e.g. `sync.php` on the server, that `Client` will communicate with.

```php
use FileSync\Server;
$server = new Server(__DIR__ . '/storage/keys');
$server->dispatch('/server/data');
```

Call the `Client` `dispatch` method from a script or your application

```php
use FileSync\Client;
$client = new Client(__DIR__ . '/storage/keys');
$client->dispatch('https://localhost:8000/sync.php','demo@example.com','/var/www/app.example.com/public_html');
```

## Generating Key Pairs

`FileSync` looks for keys using the extension based upon type of key that it needs  e.g. `.privateKey` and `.publicKey`.

You need to generate a `private` key and save this on the client machine, save the `public` key on the server.

### PHP

`FileSync` uses the [jamielsharief/encryption](https://github.com/jamielsharief/encryption) library for encryption and decryption.

> You can also use AsymmetricEncryption::generateKeyPair for key generation.

To generate a `private` key

```php
$privateKey = (new AsymmetricEncryption())->generatePrivateKey();
```

To extract a `public` key from a `private` key

```php
$publicKey = (new AsymmetricEncryption())->extractPublicKey($privateKey);
```

### Command Line

To generate a `private` key and save this to a file

```bash
$ openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out demo@example.com.privateKey
```

To generate a `public` key from the `private` key

```bash
$ openssl rsa -in demo@example.com.privateKey -pubout > demo@example.com.publicKey
```


## Ignoring Files

To ignore files on either the client or server just create a `.syncignore` file.

Here is an example show how to exclude single files, files with an extension or complete folders

```bash
composer.lock
config/.env
*.json
tests/
tmp/
vendor/
```

## Demo

To load the demo, first start the built in PHP web server

```bash
$ php -S localhost:8000
```

Then run the following command, this will create a folder called `dest` and sync the files from `src`.

```bash
$ php demo.php
```