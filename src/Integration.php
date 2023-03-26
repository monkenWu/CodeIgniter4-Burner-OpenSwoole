<?php

namespace Monken\CIBurner\OpenSwoole;

use CodeIgniter\CLI\CLI;
use Monken\CIBurner\IntegrationInterface;

class Integration implements IntegrationInterface
{
    public function initServer(string $configType = 'basic', string $frontLoader = '')
    {
        $allowConfigType = ['basic', 'http', 'websocket'];
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

        if ($configType === 'http') {
            $configType = 'basic';
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

    public function startServer(string $frontLoader, bool $daemon = false, string $commands = '')
    {
        if ($daemon === true) {
            $commands = '-s=daemon ' . $commands;
        }

        $nowDir     = __DIR__;
        $workerPath = $nowDir . DIRECTORY_SEPARATOR . 'Worker.php';
        $start      = popen("php {$workerPath} -f={$frontLoader} {$commands}", 'w');
        pclose($start);
        if (self::needRestart()) {
            $this->startServer($frontLoader, $daemon, '-r=restart');
        } else {
            echo PHP_EOL;
        }
    }

    public function stopServer(string $frontLoader, string $commands = '', bool $checkPort = false)
    {
        if (self::isDaemon()) {
            CLI::write('[Daemon mode] Trying to stop the OpenSwoole server....' . PHP_EOL);
        } else {
            CLI::write('[Debug mode] Trying to stop the OpenSwoole server...');
        }

        $temp   = self::getTempFilePath('burner_swoole_master.tmp');
        $result = false;
        if (is_file($temp)) {
            $pid  = file_get_contents($temp);
            $kill = popen("kill -15 {$pid}", 'w');
            pclose($kill);
            $result = true;
            unlink($temp);
        }

        if ($checkPort) {
            while ($this->checkPortBindable() === false) {
                sleep(1);
            }
        }

        if ($result) {
            CLI::write('The OpenSwoole server is stop.' . PHP_EOL);
        } else {
            CLI::write('There is no OpenSwoole server running.');
        }
    }

    public function restartServer(string $frontLoader, string $commands = '')
    {
        if (self::isDaemon(false)) {
            $this->stopServer($frontLoader, checkPort: true);
            CLI::write('The OpenSwoole server is restarting...');
            $this->startServer($frontLoader, true);
        } else {
            self::writeRestartSignal();
            $this->stopServer($frontLoader);
            CLI::write('The OpenSwoole server is restarting...');
        }
    }

    public function checkPortBindable()
    {
        $port   = config('OpenSwoole')->listeningPort;
        $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        $result = true;
        if (! $socket) {
            $result = false;
        }
        if (is_resource($socket)) {
            fclose($socket);
        }
        unset($socket);

        return $result;
    }

    public function reloadServer(string $frontLoader, string $commands = '')
    {
        if ($commands === '') {
            CLI::write('You must use the "mode" option like: "--mode [worker, task_worker]".');

            return;
        }

        $allowTarget = ['worker', 'task_worker'];
        if (in_array($commands, $allowTarget, true) === false) {
            CLI::write(
                CLI::color(
                    sprintf(
                        'Error mode! We only support: %s. The mode type you have entered is: %s.',
                        implode(', ', $allowTarget),
                        $commands
                    ),
                    'red'
                )
            );
            echo PHP_EOL;

            return;
        }

        $result = false;

        if ($commands === 'worker') {
            $result = self::reloadWorker(false);
            if ($result) {
                CLI::write('The OpenSwoole server workers is reload.');
            }
        }

        if ($commands === 'task_worker') {
            $result = self::reloadWorker(true);
            if ($result) {
                CLI::write('The OpenSwoole server task-workers is reload.');
            }
        }

        if ($result === false) {
            CLI::write('There is no OpenSwoole server running.');
        }
    }

    protected static function getTempFilePath(string $fileName): string
    {
        $nowDir      = __DIR__;
        $projectHash = substr(sha1($nowDir), 0, 5);
        $baseDir     = sys_get_temp_dir() . DIRECTORY_SEPARATOR;

        return sprintf('%s%s_%s', $baseDir, $projectHash, $fileName);
    }

    public static function writeIsDaemon()
    {
        $temp = self::getTempFilePath('burner_swoole_daemon.tmp');
        if (is_file($temp)) {
            unlink($temp);
        }
        file_put_contents($temp, '');
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

    public static function isDaemon(bool $unlink = true): bool
    {
        $temp   = self::getTempFilePath('burner_swoole_daemon.tmp');
        $result = false;
        if (is_file($temp)) {
            $result = true;
            if ($unlink) {
                unlink($temp);
            }
        }

        return $result;
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

    public static function reloadWorker(bool $isTaskWorker): bool
    {
        $temp   = self::getTempFilePath('burner_swoole_master.tmp');
        $result = false;
        if (is_file($temp)) {
            $pid    = file_get_contents($temp);
            $signal = $isTaskWorker ? '-USR2' : '-USR1';
            $kill   = popen("kill {$signal} {$pid}", 'w');
            pclose($kill);
            $result = true;
        }

        return $result;
    }
}
