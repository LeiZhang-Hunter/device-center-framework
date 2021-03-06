<?php
/**
 * Description:拆解syslog 协议
 * Created by PhpStorm.
 * User: 张磊
 * Date: 2018/12/16
 * Time: 16:18
 */

namespace Pendant\Protocol\Tcp;

use Library\Logger\Logger;
use Pendant\Common\CRC16;
use Pendant\Common\MQTTProxyTool;
use Pendant\Common\Tool;
use Pendant\MQTT\DeviceCenterHandle;
use Pendant\MQTT\MQTTProxyHandle;
use Pendant\MQTT\MQTTProxyPool;
use Pendant\ProtoInterface\MQTTProxy;
use Pendant\ProtoInterface\ProtoServer;
use Pendant\SwooleSysSocket;
use Pendant\SysFactory;
use Structural\System\ConfigStruct;
use Structural\System\DeviceCenterClientStruct;
use Structural\System\EventStruct;
use Structural\System\MQTTProxyProtocolStruct;
use Structural\System\OnEventTcpStruct;
use Structural\System\SwooleProtocol;

class MQTTProxyProtocol implements ProtoServer
{

    const protocol_type = SwooleProtocol::TCP_PROTOCOL;

    private static $packBuffer;

    const F_fileName = "fileName";

    const F_msg = "msg", F_happen_time = "happen_time";


    //最大的包头
    const UNPACK_HEADERLEN = "Jlength";

    const MAX_PACK_HEADER = 1024 * 10;//最大10M数据了不能再收了

    const MIN_PACK_HEADER = 16;

    const PACK_HEADER_STRUCT = "c1version/c1magic/c1server";

    const MAGIC = 103;

    private $buffer = [];

    public static $data;

    //控制器
    /**
     * @var MQTTProxyHandle
     */
    private $controller;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * 设备处理中心
     * @var DeviceCenterHandle
     */
    private $deviceCenter;

    private $proxyPool;


    public function __construct()
    {
        $this->proxyPool = MQTTProxyPool::getInstance();
    }


    public function bindWorkerStart(...$args)
    {
        $server = $args[0];
        $worker_id = $args[1];
        $controller_collect = SysFactory::getInstance()->getServerController(self::protocol_type);
        $this->controller = $controller_collect[\Structural\System\ProtocolTypeStruct::MQTT_PROXY_PROTOCOL];
        $deviceCenter = $this->controller->getDeviceCenterServer();
        $this->deviceCenter = new $deviceCenter();
        $this->logger = SwooleSysSocket::getInstance()->getLogger();
        //注册到mqtt控制器方便处理
        $this->controller->regDeviceCenterDispatch($this->deviceCenter);
        //初始化任务进程，用来对业务套接字进行处理
        $this->deviceCenter->onTaskInit($server, $this->logger);
    }

    public function bindTask(...$args)
    {

    }

    private function closeClient($fd)
    {
        $fdinfo = SwooleSysSocket::$swoole_server->getClientInfo($fd);
        SwooleSysSocket::$swoole_server->close($fd);
        $this->buffer[$fd] = "";
        $this->logger->trace(Logger::LOG_WARING, self::class, "closeClient", "[" . self::class . "->" . "closeClient" . "] is closed;remote ip:" . $fdinfo["remote_ip"] . ";remote port:" . $fdinfo["remote_port"]);
        return true;
    }


    public function bindReceive(...$args)
    {
        $data = $args[3];
        $fd = $args[1];
        $server = $args[0];
        //如果说在套接字缓冲区里有数据
        if (isset($this->buffer[$fd])) {
            $data = $this->buffer[$fd] . $data;
        }

        //清空掉缓冲区
        $this->buffer[$fd] = "";

        //计算出整个包的长度
        $dataLen = strlen($data);

        $leftLen = $dataLen;//没开始解包之前剩余的数据就是收到包的长度

        $protocol = new \Structural\System\MQTTProxyProtocolStruct();

        //如果说剩余的长度大于0
        while ($leftLen > 0) {
            $read_len = 0;

            //包不完整出现了半包直接放入到缓冲区中
            if ($leftLen < 5) {
                $this->buffer[$fd] = substr($data, 0, $leftLen);
                $this->logger->trace(Logger::LOG_WARING, self::class, "bindReceive",
                    "[" . self::class . "->" . "bindReceive" . "] recv bytes is small;len:$dataLen;file:" . __FILE__ . "line:" .
                    __LINE__);
                return true;
            }

            //解析协议的类型
            $protocol->type = unpack("C", $data[0])[1];
            $leftLen -= 1;
            $read_len += 1;

            //解析mqtt消息的类型
            $protocol->mqtt_type = unpack("C", $data[1])[1];
            $leftLen -= 1;
            $read_len += 1;

            //mqtt服务器的错误码
            $protocol->message_no = unpack("C", $data[2])[1];
            $leftLen -= 1;
            $read_len += 1;

            //计算长度
            $protocol->client_id_length = unpack("N", substr($data, 3, 4))[1];
            $leftLen -= 4;
            $read_len += 4;

            //获取到客户端id
            $protocol->client_id = substr($data, 7, $protocol->client_id_length);
            $leftLen -= $protocol->client_id_length;
            $read_len += $protocol->client_id_length;

            $payload_len_position = 7 + $protocol->client_id_length;

            //解析载荷的长度，算法跟mqtt中的算法一致
            $remain_length = Tool::remainLengthDecode(substr($data, $payload_len_position),
                $remain_offset);
            var_dump($remain_length);
            $leftLen -= $remain_offset;
            $read_len += $remain_offset;

            //校验client_id 长度的合法性 半包或者是一个错误的包,继续放入缓冲区中，当超过一定长度之后直接清空掉
            if ($remain_length > $leftLen) {
                $this->buffer[$fd] = substr($data, 0, $leftLen);
                $this->logger->trace(Logger::LOG_WARING, self::class, "bindReceive",
                    "[" . self::class . "->" . "bindReceive" . "] client id length($remain_length) > leftLen($leftLen);file:"
                    . __FILE__ . "line:" .
                    __LINE__);
                return false;
            }

            $protocol->remain_length = $remain_length;
            $payload_position = $payload_len_position + $remain_offset;
            $payload = json_decode(substr($data, $payload_position, $remain_length), 1);
            $protocol->payload = $payload;
            $leftLen -= $remain_length;
            $read_len += $remain_length;

            //校验CRC
            $protocol->fd = $fd;

            //如果说包错误则不要解析了
            $check_crc = CRC16::CheckCRC16(substr($data, 0, $read_len), $read_len);
            $crc = substr($data, $read_len, 2);
            $leftLen -= 2;
            $read_len += 2;

            if ($check_crc != $crc) {
                $this->buffer[$fd] = substr($data, 0, $leftLen);
                $this->logger->trace(Logger::LOG_WARING, self::class, "bindReceive",
                    "[" . self::class . "->" . "bindReceive" . "] crc error;file:"
                    . __FILE__ . "line:" .
                    __LINE__);
                return false;
            }

            if ($protocol->type == MQTTProxyProtocolStruct::DEVICE_CENTER_CLIENT) {
                $protocol->fd = $fd;
                $protocol->type = DeviceCenterClientStruct::OnClientReceive;
                if (!$this->deviceCenter->dispatcher($protocol)) {
                    $server->close($fd);
                }
                continue;
            }

            //拆包代理协议
            switch ($protocol->mqtt_type) {
                case MQTTProxyProtocolStruct::OnConnect:
                    $this->controller->onConnect($protocol);
                    break;

                case MQTTProxyProtocolStruct::OnSubscribe:
                    $this->controller->onSubscribe($protocol);
                    break;

                case MQTTProxyProtocolStruct::OnUnSubscribe:
                    $this->controller->onUnSubscribe($protocol);
                    break;

                case MQTTProxyProtocolStruct::OnPublish:
                    $this->controller->onPublish($protocol);
                    break;

                case MQTTProxyProtocolStruct::OnDISCONNECT:
                    $this->controller->onDisConnect($protocol);
                    break;
                case MQTTProxyProtocolStruct::PROXY_CONNECT:
                    //将代理连接注册
                    $this->proxyPool->reg($fd);
                    //发送反馈表示握手成功
                    $pack_protocol = new MQTTProxyProtocolStruct();
                    $pack_protocol->type = MQTTProxyProtocolStruct::MQTT_PROXY;
                    $pack_protocol->mqtt_type = MQTTProxyProtocolStruct::PROXY_CONNECT_MESSAGE;
                    $pack_protocol->client_id = "";
                    $pack_protocol->message_no = MQTTProxyProtocolStruct::RETURN_OK;
                    $pack_protocol->remain_length = 0;
                    $server->send($fd, MQTTProxyTool::getInstance()->pack($pack_protocol));
                    break;

                case MQTTProxyProtocolStruct::PROXY_PINGREQ:
                    //心跳包反馈，表示收到了心跳包
                    $pack_protocol = new MQTTProxyProtocolStruct();
                    $pack_protocol->type = MQTTProxyProtocolStruct::MQTT_PROXY;
                    $pack_protocol->mqtt_type = MQTTProxyProtocolStruct::PROXY_PINGRESP;
                    $pack_protocol->client_id = "";
                    $pack_protocol->message_no = MQTTProxyProtocolStruct::RETURN_OK;
                    $pack_protocol->remain_length = 0;
                    $server->send($fd, MQTTProxyTool::getInstance()->pack($pack_protocol));
                    break;
            }
            $data = substr($data, $read_len);
        }


        return true;
    }

    public function bindWorkerStop()
    {

    }

    public function bindPipeMessage(...$args)
    {
        $server = $args[0];
        $task_id = $args[1];
        $data = $args[2];


        $this->deviceCenter->onTaskHandle($server, $data);
    }

    public function bindFinish(...$args)
    {

    }

    public function bindClose(...$args)
    {
        $server = ($args[0]);
        $fd = $args[1];
        if (isset($this->buffer[$fd])) {
            unset($this->buffer[$fd]);
        }
        MQTTProxyPool::getInstance()->unReg($fd);

        $this->deviceCenter->close($fd);
    }

}