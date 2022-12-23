<?php

namespace Monken\CIBurner\OpenSwoole;

use Monken\CIBurner\IntegrationInterface;
use CodeIgniter\CLI\CLI;

class Integration implements IntegrationInterface
{
    public function initServer(string $configType = 'basic', string $frontLoader = '')
    {
        $allowConfigType = ['basic', 'websocket'];
        if(in_array($configType, $allowConfigType) == false){
            CLI::write(
                CLI::color(
                    sprintf(
                        'Error config type! We only support: %s. The config type you have entered is: %s.',
                        implode(', ', $allowConfigType),
                        $configType
                    ),
                    'red'
                )
            );
            echo PHP_EOL;
            exit;
        }

        $basePath   = ROOTPATH . 'app/Config' . DIRECTORY_SEPARATOR;
        $configPath = $basePath . 'OpenSwoole.php';

        if (file_exists($configPath)) {
            rename($configPath, $basePath . 'OpenSwoole.backup.' . time() . '.php');
        }

        $cnf = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'OpenSwoole.php.' . $configType);
        $cnf = str_replace('{{static_path}}', ROOTPATH . 'public', $cnf);
        $cnf = str_replace('{{log_path}}', realpath(WRITEPATH . 'logs') . DIRECTORY_SEPARATOR . 'OpenSwoole.log', $cnf);
        file_put_contents($configPath, $cnf);
    }

    public function startServer(string $frontLoader, string $commands = '')
    {
        $nowDir     = __DIR__;
        $workerPath = $nowDir . DIRECTORY_SEPARATOR . 'Worker.php';
        $start      = popen("php {$workerPath} -f={$frontLoader}", 'w');
        pclose($start);
        echo PHP_EOL;
    }
}
