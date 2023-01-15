<?php

namespace Monken\CIBurner\OpenSwoole\Cache;

use Config\OpenSwoole;
use Exception;
use Swoole\Table;
use Swoole\Timer;

class SwooleTable
{

    /**
     * swoole table shared instance
     *
     * @var \Swoole\Table|null
     */
    protected static ?Table $table = null;

    /**
     * self clss shard instance
     *
     * @var \Monken\CIBurner\OpenSwoole\Cache\SwooleTable|null
     */
    protected static ?SwooleTable $instance = null;

    protected static ?int $ttlTimerId = null;

    public function __construct(OpenSwoole $config)
    {
        if(is_null(self::$instance)){
            self::$instance = $this;
            $this->initTable();
        }
    }

    /**
     * Init Swoole Table
     *
     * @return void
     */
    public function initTable()
    {
        if(is_null(self::$table) === false){
            self::$table->destroy();
            self::$table = null;
        }

        self::$table = new Table(40960);
        self::$table->column('key', Table::TYPE_STRING, 1024);
        self::$table->column('value', Table::TYPE_STRING, 1024);
        self::$table->column('value_int', Table::TYPE_INT);
        self::$table->column('value_double', Table::TYPE_FLOAT);
        self::$table->column('type', Table::TYPE_STRING, 10);
        self::$table->column('expire', Table::TYPE_INT, 4);
        self::$table->create();
    }

    public function cleanTable()
    {
        foreach (self::$table as $cacheItem) {
            self::$table->del($cacheItem['key']);
        }
    }

    /**
     * Init TTL-recycler timer
     * 
     * @param int $interval
     */
    public function initTtlRecycler($interval = 1000)
    {
        if(is_int(self::$ttlTimerId)) return;
        self::$ttlTimerId = Timer::tick($interval, function(){
            $currentTime = time();
            $delKeys = [];
            foreach (self::$table as $cacheItem) {
                if ($cacheItem['expire'] !== 0 && $cacheItem['expire'] < $currentTime) {
                    $delKeys[] = $cacheItem['key'];
                }
            }
            foreach ($delKeys as $key) {
                self::$table->del($key);
            }
        });
    }

    public function deleteTtlRecycler()
    {
        timer::clear(self::$ttlTimerId);
        self::$ttlTimerId = null;
    }

    /**
     * Get Swoole Table Shared Instance
     *
     * @return Table
     */
    public function getTable(): Table
    {
        return self::$table;
    }

    /**
     * Get Shared Instance
     *
     * @return \Monken\CIBurner\OpenSwoole\Cache\SwooleTable
     */
    public static function instance(): SwooleTable
    {
        if(self::$table === null){
            throw new Exception('You must open $fastCache in "\Config\OpenSwoole".');
        }
        return self::$instance;
    }

}