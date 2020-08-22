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

    /**
     * @var MQTTProxyTool
     */
    private $tool;

    private function __construct()
    {
        $this->socket = new Swoole\Client(SWOOLE_SOCK_TCP);
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

    public static function __getInstance()
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
    public function publish($topic, $message, $timeOut)
    {
        $res = $this->socket->connect($this->ip, $this->port);

        if (!$res) {
            throw new \Exception($this->socket->errCode, socket_strerror($this->socket->errCode));
        }

        $protocol = new MQTTProxyProtocolStruct();
        $topic = $protocol->payload["topic"];
        $message_id = $this->messageId;
        $qos_level = $this->qosLevel;
        $protocol->type = MQTTProxyProtocolStruct::DEVICE_CENTER_CLIENT;
        $protocol->mqtt_type = MQTTProxyProtocolStruct::OnSubscribeMessage;
        $protocol->message_no = 0;
        $protocol->payload = json_encode([
            "topic"=>$topic,
            "message_id" => $message_id,
            "qos_level" => $qos_level,
            "message" => $message
        ]);

        $res = $this->socket->send($this->tool->pack($protocol));

        if (!$res) {
            throw new \Exception($this->socket->errCode, socket_strerror($this->socket->errCode));
        }

        $response = $this->socket->recv();

        return $response;
    }

    public function __destruct()
    {
        $this->socket->close(true);
    }
}
