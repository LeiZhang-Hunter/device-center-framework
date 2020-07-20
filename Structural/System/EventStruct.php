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

class EventStruct{

    const Event = "event";

    const Call = "call";

    const OnReceive = "onReceive";

    const OnWorkerStart = "onWorkerStart";

    public static $collect = [
        SwooleProtocol::TCP_PROTOCOL=>[
            self::Event=>TcpEvent::class,
            self::Call=>[
                ProtocolTypeStruct::LOG_SENTRY_PROTOCOL => LogSentryProtocol::class,
                ProtocolTypeStruct::MQTT_PROXY_PROTOCOL => MqttProxyProtocol::class
            ]
        ]
    ];

    //获取回调实例
    public static function getCall($protocol, $protocol_type)
    {
        return isset(self::$collect[$protocol][self::Call][$protocol_type]) ?
            self::$collect[$protocol][self::Call][$protocol_type] : null;
    }

    //获取事件
    public static function getEvent($protocol)
    {
        return isset(self::$collect[$protocol][self::Event]) ? self::$collect[$protocol][self::Event] : null;
    }

    //绑定处理事件
    public static function bindEvent($protocol,$object, $protocol_type)
    {
        if(is_object($object))
        {
            $eventName = self::getEvent($protocol);
            $bindReactorObject = new $eventName($object,self::getCall($protocol, $protocol_type));
            $bindReactorObject->call();
            unset($bindReactorObject);
            return true;
        }
        return;
    }
}