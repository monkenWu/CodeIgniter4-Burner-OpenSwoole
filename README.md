# CodeIgniter4-Burner-OpenSwoole


This Library is the OpenSwoole Driver for [CodeIgniter4 Burner](https://github.com/monkenWu/CodeIgniter4-Burner).

## Install

### Prerequisites
1. CodeIgniter Framework 4.2.0^
2. Composer
3. PHP8^
4. [OpenSwoole Pre Requisites](https://openswoole.com/docs/get-started/prerequisites)
5. [How to Install OpenSwoole](https://openswoole.com/docs/get-started/installation)

### Composer Install

You can install this Driver with the following command.

```
composer require monken/codeigniter4-burner-OpenSwoole
```

Initialize Server files using built-in commands in the library

```
php spark burner:init OpenSwoole
```

## Command

When you do not pass any parameters, it will be preset to start the server.

```
php spark burner:start OpenSwoole
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

### Automatic reload

#### OpenSwoole

> Burner does not currently support automatic loading of OpenSwoole drivers.

### Developing and debugging in a environment with only one Worker

Since the OpenSwoole and Workerman has fundamentally difference with other server software(i.e. Nginx, Apache), every Codeigniter4 will persist inside RAMs as the form of Worker, HTTP requests will reuse these Workers to process. Hence, we have better develop and test stability under the circumstance with only one Worker to prove it can also work properly under serveral Workers in the formal environment.

#### OpenSwoole

You can reference the `app/Config/OpenSwoole.php` settings below to lower the amount of Worker to the minimum:

```php
public $config = [
    'worker_num' => 1,
    /** hide */
];
```
