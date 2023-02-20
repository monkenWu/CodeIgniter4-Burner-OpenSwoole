<?php

namespace Monken\CIBurner\OpenSwoole\Cache;

use CodeIgniter\Cache\Handlers\BaseHandler;
use CodeIgniter\Exceptions\CriticalError;
use CodeIgniter\I18n\Time;
use Config\Cache;
use Exception;
use OpenSwoole\Table;

/**
 * Burner OpenSwoole cache handler
 */
class OpenSwooleHandler extends BaseHandler
{
    /**
     * Default config
     *
     * @var array
     */
    protected $config = [
        'defaultTtl' => 0,
    ];

    /**
     * SwooleTable Class Instance
     *
     * @var SwooleTable
     */
    protected $swooleTable;

    /**
     * swoole driver instance
     *
     * @var Table
     */
    protected $table;

    public function __construct(Cache $config)
    {
        $this->prefix = $config->prefix;

        $this->config = array_merge($this->config, $config->redis);
    }

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        try {
            $this->swooleTable = SwooleTable::instance();
            $this->table       = $this->swooleTable->getTable();
        } catch (Exception $e) {
            throw new CriticalError('Cache: RedisException occurred with message (' . $e->getMessage() . ').');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key)
    {
        $key  = static::validateKey($key, $this->prefix);
        $data = $this->table->get($key);

        if (! isset($data['key'], $data['value'], $data['value_int'], $data['value_double'], $data['type'], $data['expire'])) {
            return null;
        }

        switch ($data['type']) {
            case 'array':
            case 'object':
                return unserialize($data['value']);

            case 'boolean':
                return $data['value_int'] === 1 ? true : false;

            case 'integer':
                return $data['value_int'];

            case 'double':
                return $data['value_double'];

            case 'string':
                return $data['value'];

            case 'NULL':
            case 'resource':
            default:
                return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function save(string $key, $value, int $ttl = 60)
    {
        $key     = static::validateKey($key, $this->prefix);
        $setData = [
            'key'          => $key,
            'value'        => '',
            'value_int'    => 0,
            'value_double' => 0.0,
            'type'         => '',
            'expire'       => 0,
        ];

        switch ($dataType = gettype($value)) {
            case 'array':
            case 'object':
                $setData['value'] = serialize($value);
                break;

            case 'boolean':
                $setData['value_int'] = $value ? 1 : 0;
                break;

            case 'integer':
                $setData['value_int'] = $value;
                break;

            case 'double':
                $setData['value_double'] = $value;
                break;

            case 'string':
                $setData['value'] = $value;
                break;

            case 'NULL':
            case 'resource':
            default:
                return false;
        }

        $setData['type']   = $dataType;
        $setData['expire'] = $ttl > 0 ? (int) $ttl + Time::now()->getTimestamp() : 0;

        $result = $this->table->set($key, $setData);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key)
    {
        $key = static::validateKey($key, $this->prefix);

        return $this->table->del($key);
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $offset = 1)
    {
        $key  = static::validateKey($key, $this->prefix);
        $type = $this->table->get($key, 'type');
        if ($type !== 'integer') {
            return false;
        }

        return $this->table->incr($key, 'value_int', $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $key, int $offset = 1)
    {
        $key  = static::validateKey($key, $this->prefix);
        $type = $this->table->get($key, 'type');
        if ($type !== 'integer') {
            return false;
        }

        return $this->table->decr($key, 'value_int', $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function clean()
    {
        return $this->swooleTable->cleanTable();
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheInfo()
    {
        return 'burner_OpenSwoole';
    }

    /**
     * {@inheritDoc}
     */
    public function getMetaData(string $key)
    {
        $key   = static::validateKey($key, $this->prefix);
        $value = $this->get($key);
        if ($value !== null) {
            $time   = Time::now()->getTimestamp();
            $expire = $this->table->get($key, 'expire');

            return [
                'expire' => $expire > 0 ? $expire : null,
                'mtime'  => $time,
                'data'   => $value,
            ];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function isSupported(): bool
    {
        return extension_loaded('openswoole');
    }
}
