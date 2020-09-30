<?php

namespace Pendant\MQTT;

use Pendant\Common\MQTTProxyTool;
use Structural\System\MQTTProxyProtocolStruct;

class DeviceCenterClient
{
    private $socket;

    private $ip = "0.0.0.0";

    private $port = 9800;

    private $messageId = 0;

    private static $instance;

    private $qosLevel = 0;

    private $connected;

    /**
     * @var MQTTProxyTool
     */
    private $tool;

    private function __construct()
    {
        $this->socket = new \Swoole\Client(SWOOLE_SOCK_TCP);
        $this->tool = MQTTProxyTool::getInstance();
    }

    public function setIp($ip = "0.0.0.0")
    {
        $this->ip = $ip;
    }

    public function setPort($port = 9800)
    {
        $this->port = $port;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setQosLevel($qosLevel) {
        if (!in_array($qosLevel, [1, 2,3 ])) {
            throw new \Exception("qos level error--->[1, 2, 3]");
        }

        $this->qosLevel = $qosLevel;
    }

    //对设备中心推送消息
    public function publish($clientId , $message, $timeOut = 5)
    {
        if (!$clientId) {
            throw new \Exception("Client id cannot be empty");
        }

        if (!$message) {
            throw new \Exception("Message cannot be empty");
        }

        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        $this->connected = $this->socket->connect($this->ip, $this->port);

        if (!$this->connected) {
            throw new \Exception(swoole_strerror($this->socket->errCode), $this->socket->errCode);
        }

        $protocol = new MQTTProxyProtocolStruct();
        $message_id = $this->messageId;
        $qos_level = $this->qosLevel;
        $protocol->type = MQTTProxyProtocolStruct::DEVICE_CENTER_CLIENT;
        $protocol->mqtt_type = MQTTProxyProtocolStruct::OnSubscribeMessage;
        $protocol->client_id = $clientId;
        $protocol->message_no = 0;
        $protocol->payload = json_encode([
            "message_id" => $message_id,
            "qos_level" => $qos_level,
            "message" => $message
        ]);

        $res = $this->socket->send($this->tool->pack($protocol));

        if (!$res) {
            throw new \Exception($this->socket->errCode, socket_strerror($this->socket->errCode));
        }

        //可读事件
        $write = $error = [];
        $read = [$this->socket];
        $n = swoole_client_select($read, $write, $error, $timeOut);
        $response = false;
        if ($n > 0)
        {
            foreach ($read as $index => $client)
            {
                $response = $this->socket->recv();
            }
        } else {
            throw new \Exception($this->socket->errCode, socket_strerror($this->socket->errCode));
        }


        return $response;
    }

    public function __destruct()
    {
        if ($this->connected)
            $this->socket->close(true);
    }
}
