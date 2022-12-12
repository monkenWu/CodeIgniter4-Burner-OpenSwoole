<?php

namespace Monken\CIBurner\OpenSwoole;

use Monken\CIBurner\IntegrationInterface;

class Integration implements IntegrationInterface
{

    public function initServer()
    {
        $basePath = ROOTPATH . 'app/Config' . DIRECTORY_SEPARATOR;
        $configPath = $basePath . 'OpenSwoole.php';

        if(file_exists($configPath)){
            rename($configPath, $basePath . 'OpenSwoole.backup.' . time() . '.php');
        }

        $cnf = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'OpenSwooleConfig.php');
        $cnf = str_replace('{{static_path}}', ROOTPATH . 'public', $cnf);
        $cnf = str_replace('{{log_path}}', realpath(WRITEPATH . 'logs') . DIRECTORY_SEPARATOR . 'OpenSwoole.log', $cnf);
        file_put_contents($configPath ,$cnf);
    }

    public function startServer(string $frontLoader)
    {
        $nowDir     = __DIR__;
        $workerPath = $nowDir . DIRECTORY_SEPARATOR . 'Worker.php';
        $start      = popen("php {$workerPath} -f={$frontLoader}", 'w');
        pclose($start);
        echo PHP_EOL;
    }

}
