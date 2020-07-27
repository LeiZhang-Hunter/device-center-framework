<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-23
 * Time: 上午9:49
 */
namespace Pendant;

use Pendant\Common\MQTTProxyTool;
use Pendant\ProtoInterface\MQTTProxy;
use Structural\System\MQTTProxyProtocolStruct;

abstract class MQTTProxyHandle implements MQTTProxy
{
    private static $server;


    private static $pool = [];

    public function regServer($server)
    {
        self::$server = $server;
    }

    //注册连接
    public static function regConn($conn)
    {
        self::$pool[$conn] = $conn;
    }

    //取消注册连接
    public static function unRegConn($conn)
    {
        unset(self::$pool[$conn]);
    }

    //响应代理
    public function responseProxy(MQTTProxyProtocolStruct $protocol)
    {
        $mTool = MQTTProxyTool::getInstance();
        $data = $mTool->pack($protocol);
        if(self::$pool)
        {
            $key = array_rand(self::$pool);
            if(self::$server)
            {
                self::$server->send(self::$pool[$key], $data);
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
}