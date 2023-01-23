<?php

namespace Monken\CIBurner\OpenSwoole;

use OpenSwoole\Http\Server;
use OpenSwoole\WebSocket\Server as WebscoketServer;
use Config\OpenSwoole;

class FileMonitor
{
    public static ?int $lastMtime = null;

    public static function checkFilesChange(
        OpenSwoole $openSwooleConfig,
        Server|WebscoketServer $server
    ){
        $monitor_dir = $openSwooleConfig->autoReloadDir;
        $scanExtensions = $openSwooleConfig->autoReloadScanExtensions;
        $reloadMode = $openSwooleConfig->autoReloadMode;

        if (is_null(self::$lastMtime)) {
            self::$lastMtime = time();
        }

        // recursive traversal directory
        $dir_iterator = new \RecursiveDirectoryIterator($monitor_dir);
        $iterator     = new \RecursiveIteratorIterator($dir_iterator);

        foreach ($iterator as $file) {
            // only check php files
            if (in_array(pathinfo($file, PATHINFO_EXTENSION), $scanExtensions, true) !== true) {
                continue;
            }

            // check mtime
            if (self::$lastMtime < $file->getMTime()) {
                fwrite(STDOUT, sprintf(
                    '%s Change detected, reloading... ',
                    $file
                ));
                $forceRestart = $openSwooleConfig->mode == SWOOLE_BASE && (int)$openSwooleConfig->config['worker_num'] == 1;
                if($reloadMode == 'restart' || $forceRestart){
                    Integration::writeRestartSignal();
                    $mPid = $server->master_pid;
                    exec("kill {$mPid}");    
                }else if($reloadMode == 'reload'){
                    $server->reload();
                    fwrite(STDOUT, sprintf(
                        'Swoole workers are reload.%s',
                        PHP_EOL . PHP_EOL
                    ));
                }
                self::$lastMtime = time();
            }
        }
    }
}