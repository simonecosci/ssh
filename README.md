# ssh
Simple replacement for Laravel Collective Remote package

## Install 

```sh
composer require phpseclib/phpseclib
```

Place `SshService.php` in `app/Services`

Place `remote.php` in `config/`

## Usage

```php

...

use App\Services\SshService as SSH;

$username = 'root';
$password = 'password';
$id = 'my-host';
config(['remote.connections.' . $id => [
    'host' => $,
    'username' => $username,
    'password' => $password,
    'key' => '',
    'keytext' => '',
    'keyphrase' => '',
    'agent' => '',
    'timeout' => 0,
]]);

$connection = SSH::into($id);

// run a command
$output = $connection->run('ls -la');

// rum multiple commands
$output = $connection->run(['ls -la', 'pwd']);

// upload a file
$connection->putFile('test.txt');

// create a file remotely
$connection->putString('test.txt', 'content of the file');

```

