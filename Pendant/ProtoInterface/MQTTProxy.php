<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-22
 * Time: 下午8:55
 */

namespace Pendant\ProtoInterface;

use Structural\System\MQTTProxyProtocolStruct;

interface MQTTProxy
{
    public function onConnect(MQTTProxyProtocolStruct $protocol);

    public function onDisConnect(MQTTProxyProtocolStruct $protocol);

    public function onSubscribe(MQTTProxyProtocolStruct $protocol);

    public function onUnSubscribe(MQTTProxyProtocolStruct $protocol);

    public function onPublish(MQTTProxyProtocolStruct $protocol);
}