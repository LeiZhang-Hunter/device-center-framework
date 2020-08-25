<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-23
 * Time: 上午9:49
 */

namespace Pendant\MQTT;

use Pendant\Common\MQTTProxyTool;
use Pendant\ProtoInterface\MQTTProxy;
use Structural\System\MQTTProxyProtocolStruct;

abstract class MQTTProxyHandle implements MQTTProxy
{
    private static $server;

    public $deviceCenterClass = "";


    protected function regDeviceCenterServer($class)
    {
        return $this->deviceCenterClass = $class;
    }


    public function getDeviceCenterServer()
    {
        return $this->deviceCenterClass;
    }
}