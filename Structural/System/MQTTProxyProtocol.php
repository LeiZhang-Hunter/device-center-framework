<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-21
 * Time: 下午12:24
 */
namespace Structural\System;

class MQTTProxyProtocol{
    const Type = "type";
    //协议类型
    const MQTT_proxy = 0;

    public $type;

    public $message_no;

    public $mqtt_type;

    public $remain_length;

    public $client_id;

    public $payload;
}