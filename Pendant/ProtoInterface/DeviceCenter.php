<?php

namespace Pendant\ProtoInterface;

use Structural\System\MQTTProxyProtocolStruct;

interface DeviceCenter
{
    public function onConnect();

    public function onReceive(MQTTProxyProtocolStruct $protocol);

    public function onClose(MQTTProxyProtocolStruct $protocol);
}