<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/6 0006
 * Time: 17:05
 */

namespace SwooleKit\Memory;

use Psr\SimpleCache\CacheInterface;
use Swoole\Table;

/**
 * Class SwooleCache
 * @package SwooleKit\Memory
 */
class SwooleCache implements CacheInterface
{
    /** @var Table */
    private $table;

    /**
     * SwooleCache constructor.
     * @param int $size
     * @throws \RuntimeException
     */
    public function __construct(int $size = 10240)
    {
        $table = new Table($size);
        $table->column('eTime', Table::TYPE_STRING, 10);
        $table->column('value', Table::TYPE_STRING);

        if (!$table->create()) {
            throw new \RuntimeException('Create the memory cache table failed!');
        }

        $this->table = $table;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            $row = $this->table->get($key);

            if (\time() > $row['eTime']) {
                return \unserialize($row['value'], ['allowed_classes' => false]);
            }
        }

        return $default;
    }

    /**
     * @param iterable $keys
     * @param null $default
     * @return array|iterable
     */
    public function getMultiple($keys, $default = null)
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key): bool
    {
        return $this->table->exist($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = null): bool
    {
        $this->table->set($key, [
            'eTime' => \time() + $ttl,
            'value' => \serialize($value)
        ]);

        return true;
    }

    /**
     * @param iterable $values
     * @param null $ttl
     * @return bool
     */
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }


    /**
     * @param string $key
     * @return bool
     */
    public function delete($key): bool
    {
        return $this->table->del($key);
    }

    /**
     * @param iterable $keys
     * @return bool
     */
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->table->del($key);
        }

        return true;
    }

    public function all(): array
    {
        $rows = [];

        foreach ($this->table as $key => $row) {
            $rows[] = $this->table->get($key)['value'];
        }

        return $rows;
    }

    /**
     * @return bool|void
     */
    public function clear()
    {
        foreach ($this->table as $key => $row) {
            $this->table->del($key);
        }
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * @param Table $table
     */
    public function setTable(Table $table)
    {
        $this->table = $table;
    }
}
