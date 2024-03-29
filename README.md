# CodeIgniter4-Burner-OpenSwoole

<p align="center">
  <a href="https://ciburner.com//">
    <img src="https://i.imgur.com/YI4RqdP.png" alt="logo" width="200" />
  </a>
</p>

This Library is the OpenSwoole Driver for [CodeIgniter4 Burner](https://github.com/monkenWu/CodeIgniter4-Burner).

[English Document](https://ciburner.com/en/openswoole/)

[正體中文文件](https://ciburner.com/zh_TW/openswoole/)

## Install

### Prerequisites
1. CodeIgniter Framework 4.4.0^
2. CodeIgniter4-Burner 1.0.0-beta.3
3. Composer
4. PHP8^
5. OpenSwoole 22^, [OpenSwoole Pre Requisites](https://openswoole.com/docs/get-started/prerequisites)
6. [How to Install OpenSwoole](https://openswoole.com/docs/get-started/installation)

### Composer Install

You can install this Driver with the following command.

```
composer require monken/codeigniter4-burner-OpenSwoole:1.0.0-beta.3
```

Initialize Server files using built-in commands in the library.

The `http` parameter will initialize the normal http server configuration file, and if the `websocket` parameter is used, it will initialize the websocket-specific (including http) configuration file.

```
php spark burner:init OpenSwoole [http or websocket]
```

Start the server.

```
php spark burner:start
```
