<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-23
 * Time: 下午12:40
 */

namespace Pendant\Common;

use Structural\System\MQTTProxyProtocolStruct;

class MQTTProxyTool
{

    private static $instance;

    private $server;

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function loadServer($server)
    {
        $this->server = $server;
    }

    //打包加压数据
    public function pack(MQTTProxyProtocolStruct $protocol)
    {
        //根据协议类型返回数据
        $data = "";
        $data .= pack("C", $protocol->type);
        $data .= pack("C", $protocol->mqtt_type);
        $data .= pack("C", $protocol->message_no);
        $client_id_length = strlen($protocol->client_id);
        $data_len = pack("N", $client_id_length);
        $data .= $data_len;

        if ($protocol->payload && is_array($protocol->payload)) {
            $protocol->payload = json_encode($protocol->payload);
        }

        $data .= $protocol->client_id;
        $payload_len = strlen($protocol->payload);
        var_dump($payload_len);
        $payload_len = Tool::remainLengthEncode($payload_len);
        $r = Tool::remainLengthDecode($payload_len, $byte);
        var_dump($r);
        $data .= $payload_len;
        $data .= $protocol->payload;
        $data .= CRC16::CheckCRC16($data);
        return $data;
    }
}
