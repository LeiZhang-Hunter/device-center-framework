<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 19-10-31
 * Time: 下午8:35
 */

namespace Structural\System;

use Pendant\CallEvent\TcpEvent;
use Pendant\Protocol\Tcp\LogSentryProtocol;
use Pendant\Protocol\Tcp\MqttProxyProtocol;
use Pendant\Protocol\Tcp\TcpProtocol;
use Pendant\ProtoInterface\ProtoServer;

class EventStruct
{

    const Event = "event";

    const Call = "call";

    const OnReceive = "onReceive";

    const OnWorkerStart = "onWorkerStart";

    public static $collect = [
        SwooleProtocol::TCP_PROTOCOL => [
            self::Event => TcpEvent::class,
            self::Call => [
                ProtocolTypeStruct::LOG_SENTRY_PROTOCOL => LogSentryProtocol::class,
                ProtocolTypeStruct::MQTT_PROXY_PROTOCOL => MqttProxyProtocol::class
            ]
        ]
    ];

    //获取回调实例
    public static function getCall($protocol, $protocol_type)
    {
        $call = isset(self::$collect[$protocol][self::Call][$protocol_type]) ?
            self::$collect[$protocol][self::Call][$protocol_type] : null;
        if (!$call) {
            throw new \Exception("protocol[$protocol]->call[$protocol_type] is not exist");
        }

        if (!class_exists($call)) {
            throw new \Exception("protocol[$protocol]->call[$protocol_type] is not class");
        }

        return $call;
    }

    //添加回调事件，这样可以支持自定义协议
    public static function addCall($protocol, $protocol_type, $protocol_class_name)
    {
        if ($protocol != SwooleProtocol::TCP_PROTOCOL) {
            throw new \Exception("protocol:$protocol is not support");
        }
        self::$collect[$protocol][self::Call][$protocol_type] = $protocol_class_name;
        return true;
    }

    //获取事件
    public static function getEvent($protocol)
    {
        return isset(self::$collect[$protocol][self::Event]) ? self::$collect[$protocol][self::Event] : null;
    }

    //绑定处理事件
    public static function bindEvent($protocol, $object, $protocol_type)
    {
        if (is_object($object)) {
            $eventName = self::getEvent($protocol);
            if (!$eventName) {
                throw new \Exception("protocol[$protocol][$protocol_type] is not exist");
            }
            $bindReactorObject = new $eventName($object, self::getCall($protocol, $protocol_type));
            $bindReactorObject->call();
            unset($bindReactorObject);
            return true;
        }
        return;
    }
}