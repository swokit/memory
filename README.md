# memory for swoole

swoole memory 工具的一些封裝

> 快速方便的使用swoole server [inhere/server](https://github.com/inhere/php-server)

主要包含有：

- 一个语言管理类
- 内存表使用封装(除了基本的 get/set/del 增加了数据导出和恢复，简单的字段搜索)
- 当使用多个内存表时，可以使用 `MemoryDB` 来管理
- 一个使用swoole table的缓存类实现，PSR 16

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

$row = $userTable->get('key');
$password = $userTable->get('key', 'password');
```

## license

MIT
