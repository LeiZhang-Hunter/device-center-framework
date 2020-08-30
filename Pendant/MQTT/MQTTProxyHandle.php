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

    /**
     * @var DeviceCenterHandle
     */
    private $deviceCenterInstance;


    protected function regDeviceCenterServer($class)
    {
        return $this->deviceCenterClass = $class;
    }


    public function getDeviceCenterServer()
    {
        return $this->deviceCenterClass;
    }

    /**
     * 获取设备中心的派遣实例
     * @return DeviceCenterHandle
     */
    public function getDeviceCenterDispatch()
    {
        return $this->deviceCenterInstance;
    }

    /**
     * 获取设备中心的派遣实例
     * @return DeviceCenterHandle
     */
    public function regDeviceCenterDispatch(DeviceCenterHandle $dispatcher)
    {
        $this->deviceCenterInstance = $dispatcher;
    }
}