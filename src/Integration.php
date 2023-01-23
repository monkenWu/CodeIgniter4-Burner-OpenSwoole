<?php

namespace Monken\CIBurner\OpenSwoole;

use CodeIgniter\CLI\CLI;
use Monken\CIBurner\IntegrationInterface;

class Integration implements IntegrationInterface
{
    public function initServer(string $configType = 'basic', string $frontLoader = '')
    {
        $allowConfigType = ['basic', 'websocket'];
        if (in_array($configType, $allowConfigType, true) === false) {
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
        $cnf = str_replace('{{reload_path}}', realpath(APPPATH . '../'), $cnf);
        $cnf = str_replace('{{log_path}}', realpath(WRITEPATH . 'logs') . DIRECTORY_SEPARATOR . 'OpenSwoole.log', $cnf);
        file_put_contents($configPath, $cnf);
    }

    public function startServer(string $frontLoader, string $commands = '')
    {
        if ($commands === 'daemon') {
            $commands = '-s=daemon';
        }

        if ($commands === 'stop') {
            self::stopServer();
            CLI::write('The OpenSwoole server is stop.');

            return;
        }

        if ($commands === 'reload worker') {
            self::reloadWork(false);
            CLI::write('The OpenSwoole server workers is reload.');

            return;
        }

        if ($commands === 'reload task_worker') {
            self::reloadWork(true);
            CLI::write('The OpenSwoole server task-workers is reload.');

            return;
        }

        $nowDir     = __DIR__;
        $workerPath = $nowDir . DIRECTORY_SEPARATOR . 'Worker.php';
        $start      = popen("php {$workerPath} -f={$frontLoader} {$commands}", 'w');
        pclose($start);
        if (self::needRestart()) {
            $this->startServer($frontLoader, '-r=restart');
        } else {
            echo PHP_EOL;
        }
    }

    protected static function getTempFilePath(string $fileName): string
    {
        $nowDir      = __DIR__;
        $projectHash = substr(sha1($nowDir), 0, 5);
        $baseDir     = sys_get_temp_dir() . DIRECTORY_SEPARATOR;

        return sprintf('%s%s_%s', $baseDir, $projectHash, $fileName);
    }

    public static function writeMasterPid(int $pid)
    {
        $temp = self::getTempFilePath('burner_swoole_master.tmp');
        if (is_file($temp)) {
            unlink($temp);
        }
        file_put_contents($temp, $pid);
    }

    public static function writeRestartSignal()
    {
        $temp = self::getTempFilePath('burner_swoole_restart.tmp');
        file_put_contents($temp, 'restart');
    }

    public static function needRestart(): bool
    {
        $temp   = self::getTempFilePath('burner_swoole_restart.tmp');
        $result = false;
        if (is_file($temp)) {
            $text = file_get_contents($temp);
            if ($text === 'restart') {
                $result = true;
            }
            unlink($temp);
        }

        return $result;
    }

    public static function stopServer(): bool
    {
        $temp   = self::getTempFilePath('burner_swoole_master.tmp');
        $result = false;
        if (is_file($temp)) {
            $pid  = file_get_contents($temp);
            $kill = popen("kill -TERM {$pid}", 'w');
            pclose($kill);
            $result = true;
            unlink($temp);
        }

        return $result;
    }

    public static function reloadWork(bool $isTaskWork): bool
    {
        $temp   = self::getTempFilePath('burner_swoole_master.tmp');
        $result = false;
        if (is_file($temp)) {
            $pid    = file_get_contents($temp);
            $signal = $isTaskWork ? '-USR2' : '-USR1';
            $kill   = popen("kill {$signal} {$pid}", 'w');
            pclose($kill);
            $result = true;
        }

        return $result;
    }
}
