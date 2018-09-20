<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/6 0006
 * Time: 12:59
 */

namespace SwoKit\Memory;

use Swoole\Async;
use Swoole\Table;

/**
 * Class MemoryTable
 * @package SwoKit\Memory
 * @link https://wiki.swoole.com/wiki/page/256.html
 *
 * Table使用共享内存来保存数据，在创建子进程前，务必要执行Table->create()
 * swoole_server中使用Table，Table->create() 必须在 swoole_server->start()前执行
 *
 * @example
 *
 * ```php
 * $userTable = new MemoryTable('user', 1024);
 * $userTable->setColumns([
 *      'id' => [Table::TYPE_INT, 10],
 *      'username' => [Table::TYPE_STRING, 32],
 *      'nickname' => [Table::TYPE_STRING, 32],
 *      'password' => [Table::TYPE_STRING, 64],
 * ]);
 *
 * // create it
 * $userTable->create();
 *
 * // usage
 *
 * ```
 *
 * @method bool exist(string $key)
 * @method bool del(string $key)
 * @method false|int incr(string $key, string $column, int $incrBy = 1)
 * @method false|int decr(string $key, string $column, int $decrBy = 1)
 */
class MemoryTable implements \Countable
{
    /** @var string The table name */
    private $name;

    /** @var Table The swoole table */
    private $table;

    /** @var int The table size(max rows) */
    private $size;

    /**
     * @var array[]
     * [
     *    field => [type, length]
     * ]
     */
    private $columns;

    /**
     * @var string 数据落地文件
     */
    private $dumpFile = '';

    // 搜索时将要清理的字符
    const INVALID_CHARS = [
        '\\', '/', ',', '.', ';', ':', '?', '`', '!', '@', '#', '$', '%', '^', '&', '*',
        "\n", "\r\n", "\r", "\t",
        // 中文符号
        '，', '。', '、', '？', '；', '：'
    ];

    /*******************************************************************************
     * init table methods
     ******************************************************************************/

    /**
     * MemTable constructor.
     * @param string $name The table name
     * @param int $size The table size(max rows)
     * @param array $columns The table columns definition
     */
    public function __construct(string $name, int $size = 0, array $columns = [])
    {
        $this->name = $name;
        $this->size = $size;
        $this->columns = $columns;
    }

    /**
     * @param string $name The column name
     * @param int $type Please @see Table::TYPE_*
     * @param int $length
     * Table::TYPE_INT 默认为4个字节，可以设置1，2，4，8一共4种长度
     * Table::TYPE_STRING 设置后，设置的字符串不能超过此长度
     * Table::TYPE_FLOAT 会占用8个字节的内存
     * @return $this
     */
    public function addColumn($name, int $type, $length = 0): self
    {
        $this->columns[$name] = [$type, $length];

        return $this;
    }

    /**
     * @param array $columns
     * @return $this
     */
    public function addColumns(array $columns): self
    {
        foreach ($columns as $column => $settings) {
            $this->columns[$column] = (array)$settings;
        }

        return $this;
    }

    /**
     * @param bool $thrException
     * @return bool
     * @throws \RuntimeException
     */
    public function create(bool $thrException = true): bool
    {
        $this->table = new Table($this->size);

        foreach ($this->columns as $column => list($type, $length)) {
            $this->table->column($column, $type, $length);
        }

        $ok = $this->table->create();

        if (!$ok && $thrException) {
            throw new \RuntimeException("Create the memory table '{$this->name}' failed!");
        }

        return $ok;
    }

    /**
     * @param MemoryDB $db
     */
    public function attachTo(MemoryDB $db)
    {
        $db->addTable($this);
    }

    /*******************************************************************************
     * operate table
     ******************************************************************************/

    /**
     * @param string $key
     * @param string|null $field
     * @return mixed
     */
    public function get(string $key, string $field = null)
    {
        return $this->table->get($key, $field);
    }

    /**
     * @param array $keys
     * @param string|null $field
     * @return array
     */
    public function getMulti(array $keys, string $field = null): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[] = $this->table->get($key, $field);
        }

        return $values;
    }

    /**
     * insert/update one row data
     * @param string $key
     * @param array $values
     * [
     *   field => value
     * ]
     */
    public function save(string $key, array $values)
    {
        $this->table->set($key, $values);
    }

    /**
     * @param string|array $keys
     * @return bool
     */
    public function delete($keys): bool
    {
        if (\is_string($keys)) {
            return $this->table->del($keys);
        }

        // delete multi
        if (\is_array($keys)) {
            foreach ($keys as $key) {
                $this->table->del($key);
            }

            return true;
        }

        return false;
    }

    /**
     * search in the table
     * @param  string $keyword
     * @param int $limit
     * @param array $opts
     * @return array
     */
    public function search(string $keyword, int $limit = 30, array $opts = []): array
    {
        $opts = \array_merge([
            'mark' => false,
            'keepIndex' => true,
            'searchField' => ''
        ], $opts);

        if (!$field = $opts['searchField']) {
            return [
                'message' => 'Option setting is error!',
            ];
        }

        $keepIndex = (bool)$opts['keepIndex'];
        $keyword = \trim(\str_replace(self::INVALID_CHARS, '', \trim($keyword)));

        if (!$keyword) {
            return [
                'message' => 'Keywords is invalid',
            ];
        }

        $encode = 'utf-8';
        $keyword = \mb_strlen($keyword, $encode) > 12 ? mb_substr($keyword, 0, 12, $encode) : $keyword;

        $counter = 0;
        $cutLen = 120;
        $results = [];

        foreach ($this->table as $key => $row) {
            $text = $row[$field];

            if (!($pos = \mb_stripos($text, $keyword, 0, $encode))) {
                continue;
            }

            if ($counter >= $limit) {
                break;
            }

            $counter++;
            $start = $pos < 20 ? 0 : $pos - 20;
            $context = \mb_substr($text, $start, $cutLen, $encode);

            if ($opts['mark']) {
                $context = \str_ireplace(
                    $keyword,
                    \sprintf('<span class="search-mark">%s</span>', $keyword),
                    $context
                );
            }

            $item = [
                $field => $row[$field],
                'kwCount' => \substr_count($text, $keyword),
                'context' => $context,
            ];

            if ($keepIndex) {
                $results[$key] = $item;
            } else {
                $results[] = $item;
            }
        }

        return [
            'message' => 'ok',
            'total' => $this->count(),
            'resultRows' => \count($results),
            'results' => $results
        ];
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->table->count();
    }

    /*******************************************************************************
     * data restore/dump/load/flush methods
     ******************************************************************************/

    /**
     * 从数据落地文件中恢复
     * @param bool $async
     */
    public function restore(bool $async = false)
    {
        if (!$file = $this->dumpFile) {
            return;
        }

        if (!\file_exists($file)) {
            return;
        }

        if (!$async) {
            $content = \file_get_contents($file);
            $this->load((array)\json_decode($content, true));
            return;
        }

        Async::readFile($file, function($file, $content) {
            $this->load((array)\json_decode($content, true));
        });
    }

    /**
     * 导出 table 里的数据到 dumpFile
     * @param bool $async
     */
    public function dump(bool $async = false)
    {
        if (!$file = $this->dumpFile) {
            return;
        }

        $data = [];

        foreach ($this->table as $key => $row) {
            $data[$key] = $row;
        }

        if (!$async) {
            \file_put_contents($file, \json_encode($data));
            return;
        }

        Async::writeFile($file, \json_encode($data), function ($file) {
            // \log("Dump data to file: $file");
        });
    }

    /**
     * @param array $data
     * @param string|null $indexKey If you want to use a field in the row as the key
     */
    public function load(array $data, string $indexKey = null)
    {
        foreach ($data as $key => $row) {
            if ($indexKey && isset($row[$indexKey])) {
                $this->table->set($row[$indexKey], $row);
            } else {
                $this->table->set($key, $row);
            }
        }
    }

    /**
     * flush table
     * @param bool $unset
     */
    public function flush($unset = false)
    {
        $this->clear($unset);
    }

    /**
     * clear table data.
     * @param bool $unset
     */
    public function clear($unset = false)
    {
        foreach ($this->table as $key => $row) {
            $this->table->del($key);
        }

        if ($unset) {
            unset($this->table);
        }
    }

    /*******************************************************************************
     * getter/setter methods
     ******************************************************************************/

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize(int $size)
    {
        $this->size = $size;
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

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param array $columns
     * @return MemoryTable
     */
    public function setColumns(array $columns): self
    {
        return $this->addColumns($columns);
    }

    /**
     * @param $method
     * @param array $args
     * @return mixed
     * @throws \RuntimeException
     */
    public function __call($method, array $args = [])
    {
        if (\method_exists($this->table, $method)) {
            return $this->table->$method(...$args);
        }

        throw new \RuntimeException('Call a not exists method: ' . $method);
    }

    /**
     * @return string
     */
    public function getDumpFile(): string
    {
        return $this->dumpFile;
    }

    /**
     * @param string $dumpFile
     */
    public function setDumpFile(string $dumpFile)
    {
        $this->dumpFile = $dumpFile;
    }
}
