<?php

/**
 * 这个任务作为任务进程
 * Class DeviceCenterHandle
 */

namespace Pendant\MQTT;

use Library\Logger\Logger;
use Pendant\ProtoInterface\DeviceCenter;
use Pendant\SysFactory;
use Structural\System\ConfigStruct;
use Structural\System\DeviceCenterClientStruct;
use Structural\System\MQTTProxyProtocolStruct;

abstract class DeviceCenterHandle implements DeviceCenter
{

    private static $isInit = false;

    private $worker_num = 0;

    private $task_worker_num = 0;

    /**
     * @var \Swoole\Server
     */
    private $server;

    private $clientPool = [];

    //初始化任务进程
    public function onTaskInit($server, Logger $logger)
    {
        if (isset($server->setting[ConfigStruct::S_WORKER_NUM])) {
            $this->worker_num = $server->setting[ConfigStruct::S_WORKER_NUM];
        }
        $this->task_worker_num = SysFactory::getInstance()->getTaskNumber();
        $this->server = $server;
    }

    public function onTaskHandle($server, MQTTProxyProtocolStruct $protocol)
    {
        if (!$protocol->client_id) {
            return false;
        }
        if ($protocol->type == DeviceCenterClientStruct::OnClientReceive) {
            $this->onReceive($protocol);
        } else if ($protocol->type == DeviceCenterClientStruct::OnClientClose) {
            $this->onClose($protocol);
        }
    }

    //进行任务派遣，算法采用散列表，链接地址法
    public function dispatcher(MQTTProxyProtocolStruct $protocol)
    {
        $clientId = $protocol->client_id;

        if (!$this->task_worker_num) {
            return false;
        }

        if (!$this->worker_num) {
            return false;
        }

        if (!$clientId) {
            return false;
        }

        if (is_string($clientId)) {
            $len = strlen($clientId);
            $hash_value = 0;
            for ($i = 0; $i < $len; $i++) {
                $hash_value += ord($clientId[$i]);
            }

            $dispatcher_process_id = $hash_value % $this->task_worker_num;
            $dispatcher_process_id += $this->worker_num;
            $this->clientPool[$protocol->fd] = $clientId;
            $this->server->sendMessage($protocol, $dispatcher_process_id);

            return true;
        } else {
            return false;
        }
    }

    /**
     * 关闭设备中心的业务套接字
     * @param $fd
     */
    public function close($fd)
    {
        if (isset($this->clientPool[$fd])) {
            $protocol = new MQTTProxyProtocolStruct();
            $protocol->fd = $fd;
            $protocol->mqtt_type = MQTTProxyProtocolStruct::DEVICE_CENTER_CLIENT;
            $protocol->type = DeviceCenterClientStruct::OnClientClose;
            $protocol->client_id = $this->clientPool[$fd];
            $protocol->payload["token"] = $this->clientPool[$fd];
            unset($this->clientPool[$fd]);
            $this->dispatcher($protocol);
        }
    }
}