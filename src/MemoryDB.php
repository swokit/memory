<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/6 0006
 * Time: 13:01
 */

namespace SwoKit\Memory;

/**
 * Class MemoryDB - memory table manager
 * @package SwoKit\Memory
 */
class MemoryDB implements \Countable
{
    /**
     * @var MemoryTable[]
     */
    private $tables = [];

    /**
     * MemoryDB constructor.
     * @param array $tables
     */
    public function __construct(array $tables = [])
    {
        $this->setTables($tables);
    }

    /**
     * @param MemoryTable $table
     */
    public function addTable(MemoryTable $table)
    {
        $this->tables[$table->getName()] = $table;
    }

    /**
     * @param string $name
     * @return null|MemoryTable
     */
    public function getTable(string $name)
    {
        if (isset($this->tables[$name])) {
            return $this->tables[$name];
        }

        return null;
    }

    /**
     * @param string $name
     */
    public function delTable(string $name)
    {
        if (isset($this->tables[$name])) {
            unset($this->tables[$name]);
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasTable(string $name): bool
    {
        return isset($this->tables[$name]);
    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @param array $tables
     * @return MemoryDB
     */
    public function setTables(array $tables): self
    {
        foreach ($tables as $table) {
            if (\is_string($table)) {
                $table = new $table;
            }

            $this->addTable($table);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getTableNames(): array
    {
        return \array_keys($this->tables);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \count($this->tables);
    }
}
