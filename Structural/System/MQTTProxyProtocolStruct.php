<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-21
 * Time: 下午12:24
 */

namespace Structural\System;

/**
 * 这个文件是做MQTT代理协议的 一般用在内网传输，不推荐用加密的方式，当然载荷部分可以加密的
 *
 * 具体结构:
 *
 * -------------------------------
 * |
 * |          代理协议类型(0是MQTT代理)
 * |
 * |------------------------------
 * |          MQTT消息类型
 * |------------------------------
 * |
 * |          错误码
 * |-------------------------------
 * |
 * |          设备id长度
 * |
 * |--------------------------------
 * |          设备id
 * |
 * |-------------------------------
 * |
 * |          剩余长度
 * |-------------------------------
 * |
 * |          载荷(使用json)
 * |-------------------------------
 * |
 * |          CRC校验
 * |-------------------------------
 */
class MQTTProxyProtocolStruct
{
    const Type = "type";
    //协议类型
    const MQTT_proxy = 0;

    public $type;

    public $message_no;

    public $mqtt_type;

    public $remain_length;

    public $client_id;

    public $payload;

    //描述符,存放一个收到请求的描述符
    public $fd;

    const OnConnect = 0;

    const OnConnectMessage = 1;

    const OnSubscribe = 2;

    const OnSubscribeMessage = 3;

    const OnUnSubscribe = 4;

    const OnUnSubscribeMessage = 5;

    const OnPublish = 6;

    const OnPublishMessage = 7;

    const OnDISCONNECT = 8;
}