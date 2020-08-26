<?php

namespace Pendant\MQTT;
use Swoole\Table;

class MQTTProxyPool
{
    private static $instance;

    private $table;

    const FD_KEY = "fd";

    private function __construct()
    {
        $this->table = new Table(1024);
        $this->table->column(self::FD_KEY, Table::TYPE_INT, 8);
        $this->table->create();
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    //注册
    public function reg($fd)
    {
        return $this->table->set($fd, [self::FD_KEY => $fd]);
    }

    //获取代理描述符
    public function getProxy()
    {
        $count = $this->table->count();
        $number = rand(0, $count);
        $i = 0;
        foreach ($this->table as $row) {
            $i++;
            if ($i == $number) {
                break;
            }
        }
        var_dump($row);
        return $row[self::FD_KEY];
    }

    //解除注册
    public function unReg($fd)
    {
        return $this->table->del($fd);
    }
}
