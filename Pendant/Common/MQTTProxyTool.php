<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-23
 * Time: 下午12:40
 */
namespace Pendant\Common;

use Structural\System\MQTTProxyProtocolStruct;

class MQTTProxyTool{

    private static $instance;

    private $server;

    public static function getInstance()
    {
        if(!self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function loadServer($server)
    {
        $this->server = $server;
    }

    public function send(MQTTProxyProtocolStruct $protocol)
    {
        return true;
    }
}