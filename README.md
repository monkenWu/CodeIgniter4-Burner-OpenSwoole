# CodeIgniter4-Burner-OpenSwoole

This Library is the OpenSwoole Driver for [CodeIgniter4 Burner](https://github.com/monkenWu/CodeIgniter4-Burner).

## Install

### Prerequisites
1. CodeIgniter Framework 4.2.0^
2. CodeIgniter4-Burner 0.3.2^
3. Composer
4. PHP8^
5. OpenSwoole 22^, [OpenSwoole Pre Requisites](https://openswoole.com/docs/get-started/prerequisites)
6. [How to Install OpenSwoole](https://openswoole.com/docs/get-started/installation)

### Composer Install

You can install this Driver with the following command.

```
composer require monken/codeigniter4-burner-OpenSwoole
```

Initialize Server files using built-in commands in the library.

The `basic` parameter will initialize the normal http server configuration file, and if the `websocket` parameter is used, it will initialize the websocket-specific configuration file.

```
php spark burner:init OpenSwoole [basic or websocket]
```

## Command

When you do not pass any parameters, it will be preset to start the server.

```
php spark burner:start OpenSwoole
```

### daemon mode

Let OpenSwoole work in the background.

When you run the server with this option, Burner will ignore the Automatic reload setting.

```
php spark burner:start OpenSwoole daemon
```

### stop server

```
php spark burner:start OpenSwoole stop
```

### reload worker

```
php spark burner:start OpenSwoole reload worker
```

```
php spark burner:start OpenSwoole reload task_worker
```

## OpenSwoole Server Settings

The server settings are all in the `app/Config` directory `OpenSwoole.php`. The default file will look like this:

```php
class OpenSwoole extends BaseConfig
{
    /**
     * TCP HTTP service listening ip
     *
     * @var string
     */
    public $listeningIp = '0.0.0.0';

    /**
     * TCP HTTP service listening port
     *
     * @var int
     */
    public $listeningPort = 8080;

    /**
     * SWOOLE_PROCESS or SWOOLE_BASE
     *
     * @var int
     *
     * @see https://openswoole.com/docs/modules/swoole-server-reload#server-modes-and-reloading
     */
    public $mode = SWOOLE_BASE;

    //hide
}
```

You can refer to the [OpenSwoole HTTP Server Settings](https://openswoole.com/docs/modules/swoole-http-server/configuration), [OpenSwoole TCP Server Settings](https://openswoole.com/docs/modules/swoole-server/configuration), etc. to crete a configuration profile that meets your project requirements.

## Development Suggestions

### Fast Cache

We provide [Swoole-Table](https://openswoole.com/docs/modules/swoole-table) based Key/Value caching. It implements the CodeIgniter4 Cache Interface, so you can use it out of the box in your projects, just like you know how!

You can modify your `app/Config/OpenSwoole.php` configuration file, add the following settings and restart the server.

```php
/**
 * Whether to open the Key/Value Cache provided by Burner.
 * Shared high-speed caching with Swoole-Table implementation.
 * 
 * @var boolean
 */
public $fastCache = true;

/**
 * Buerner Swoole-Table Driver Settings
 * 
 * @var string[]
 */
public $fastCacheConfig = [
    //Number of rows of the burner cache table
    'tableSize' => 4096,   
    //Key/value key Maximum length of string
    'keyLength' => 1024,
    //The maximum length of the key/value (if the save type is object, array, string).
    'valueStringLength' => 1024
];
```

Next, you need to open `app/Config/Cache` and add `Monken\CIBurner\BurnerCacheHandler` to the `Available Cache Handlers`.

```php
public $validHandlers = [
    //hide
    'burner' => \Monken\CIBurner\BurnerCacheHandler::class
];
```

Finally, you have to switch from `Primary Handler` to Burner.

```php
public $handler = 'burner';
```

Now you can operate the Swoole-Table Cache provided by Burner in the same way as the [Cache Library](https://www.codeigniter.com/user_guide/libraries/caching.html) provided by CodeIgniter4. Please note that the Swoole-Table loses all data due to server restarts and is only a data sharing solution across workers.

### Automatic reload

#### OpenSwoole

In the default circumstance of OpenSwoole, you must restart the server everytime after you revised any PHP files so that your revision will effective. It seems not that friendly during development.

You can modify your `app/Config/OpenSwoole.php` configuration file, add the following settings and restart the server.

```php
/**
 * Auto-scan changed files
 *
 * @var bool
 */
public $autoReload = true;

/**
 * Auto Reload Mode
 *
 * @var string restart or reload
 */
public $autoReloadMode = 'restart';
```

Burner offers two types of Reload, which you can switch between by adjusting `autoReloadMode`.

* `restart` means that the server is automatically restarted every time a file is changed. It's as if you shut down the server yourself and then turn it back on again, which ensures that all php files are reloaded.
* `reload` only reloads the running worker, just as the [documentation](https://openswoole.com/docs/modules/swoole-server-reload#hot-code-linux-signal-trigger) says. Note that this mode may not handle all cases where, for example, you generate some changes to the project core-php file via `composer require/update`. 

> The `Automatic reload` function is very resource-intensive, please do not activate the option in the formal environment.

### Developing and debugging in a environment with only one Worker

Since the OpenSwoole has fundamentally difference with other server software(i.e. Nginx, Apache), every Codeigniter4 will persist inside RAMs as the form of Worker, HTTP requests will reuse these Workers to process. Hence, we have better develop and test stability under the circumstance with only one Worker to prove it can also work properly under serveral Workers in the formal environment.

You can reference the `app/Config/OpenSwoole.php` settings below to lower the amount of Worker to the minimum:

```php
public $config = [
    'worker_num' => 1,
    /** hide */
];
```
