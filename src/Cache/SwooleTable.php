<?php

namespace Monken\CIBurner\OpenSwoole\Cache;

use Config\OpenSwoole as Config;
use Exception;
use OpenSwoole\Table;
use OpenSwoole\Timer;

class SwooleTable
{
    /**
     * swoole table shared instance
     */
    protected static ?Table $table = null;

    /**
     * self clss shard instance
     *
     * @var \Monken\CIBurner\OpenSwoole\Cache\SwooleTable|null
     */
    protected static ?SwooleTable $instance = null;

    protected static ?int $ttlTimerId = null;
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        if (null === self::$instance) {
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
        if ((null === self::$table) === false) {
            self::$table->destroy();
            self::$table = null;
        }
        self::$table = new Table($this->config->fastCacheConfig['tableSize']);
        self::$table->column('key', Table::TYPE_STRING, $this->config->fastCacheConfig['keyLength']);
        self::$table->column('value', Table::TYPE_STRING, $this->config->fastCacheConfig['valueStringLength']);
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
        if (is_int(self::$ttlTimerId)) {
            return;
        }
        self::$ttlTimerId = Timer::tick($interval, static function () {
            $currentTime = time();
            $delKeys     = [];

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
        if (self::$table === null) {
            throw new Exception('You must open $fastCache in "\Config\OpenSwoole".');
        }

        return self::$instance;
    }
}
