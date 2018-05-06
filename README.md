# memory for swoole

swoole memory 工具的一些封裝

> 快速方便的使用swoole server [inhere/server](https://github.com/inhere/php-server)

## install

```bash
composer require swoole-kit/memory
```

## 内存表

* Table使用共享内存来保存数据，在创建子进程前，务必要执行 `Table->create()`
* swoole_server中使用Table，Table->create() 必须在 `swoole_server->start()` 前执行

```php
$userTable = new MemoryTable('user', 1024);
$userTable->setColumns([
     'id' => [Table::TYPE_INT, 10],
     'username' => [Table::TYPE_STRING, 32],
     'password' => [Table::TYPE_STRING, 64],
]);

// create it
$userTable->create();
```

使用：

```php
$userTable->save('key', [
    'username' => 'tom',
    'password' => 'string',
]);

$row = $userTable->find('key');
```

## license

MIT
