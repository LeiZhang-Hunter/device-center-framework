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
use Pendant\MQTTProxyHandle;
use Pendant\ProtoInterface\MQTTProxy;
use Pendant\ProtoInterface\ProtoServer;
use Pendant\SwooleSysSocket;
use Pendant\SysFactory;
use Structural\System\ConfigStruct;
use Structural\System\EventStruct;
use Structural\System\MQTTProxyProtocolStruct;
use Structural\System\OnEventTcpStruct;
use Structural\System\SwooleProtocol;

class MqttProxyProtocol implements ProtoServer{

    const protocol_type = SwooleProtocol::TCP_PROTOCOL;

    private static $packBuffer;

    const F_fileName = "fileName";

    const F_msg = "msg",F_happen_time = "happen_time";


    //最大的包头
    const UNPACK_HEADERLEN = "Jlength";

    const MAX_PACK_HEADER = 1024*10;//最大10M数据了不能再收了

    const MIN_PACK_HEADER = 16;

    const PACK_HEADER_STRUCT = "c1version/c1magic/c1server";

    const MAGIC = 103;

    private $buffer;

    public static $data;

    //控制器
    /**
     * @var MQTTProxy
     */
    private $controller;

    /**
     * @var Logger
     */
    private $logger;




    public function __construct()
    {
        $this->controller = SysFactory::getInstance()->getServerController(self::protocol_type);
        $this->logger = SwooleSysSocket::getInstance()->getLogger();
    }



    public function bindWorkerStart(...$args)
    {
        $server = $args[0];
        $worker_id = $args[1];
        if($worker_id >= $server->setting[ConfigStruct::S_WORKER_NUM]) {
            $callfunc = [$this->controller,EventStruct::OnWorkerStart];
            if(is_callable($callfunc)) {

                //从配置文件中获取实例的静态
                call_user_func_array($callfunc, [$server]);
            }else{
                $this->logger->trace(Logger::LOG_ERROR,self::class,OnEventTcpStruct::ON_bindWorkerStart,"[controller[".self::protocol_type."]->".EventStruct::OnWorkerStart."] is not callable");
            }
        }

    }

    public function bindTask(...$args)
    {
        $server = $args[0];
        $task_id = $args[1];
        $from_id = $args[2];
        $data = $args[3];

        $callfunc = [$this->controller,EventStruct::OnReceive];
        if(is_callable($callfunc)) {

            //从配置文件中获取实例的静态
            call_user_func_array($callfunc, [$data]);
        }else{
            $this->logger->trace(Logger::LOG_ERROR,self::class,OnEventTcpStruct::ON_bindTask,"[controller[".self::protocol_type."]->".EventStruct::OnReceive."] is not callable");
        }

    }

    private function closeClient($fd)
    {
        $fdinfo = SwooleSysSocket::$swoole_server->getClientInfo($fd);
        SwooleSysSocket::$swoole_server->close($fd);
        $this->buffer[$fd] = "";
        $this->logger->trace(Logger::LOG_WARING,self::class,"closeClient","[".self::class."->"."closeClient"."] is closed;remote ip:".$fdinfo["remote_ip"].";remote port:".$fdinfo["remote_port"]);
        return true;
    }

    //解析字节，将字节变为长度
    private function remainLengthDecode($data)
    {
        $data = unpack("C",$data)[1];
        $multiplier = 1;
        $value = 0;
        do{
            $data++;
            $value += ($data AND 127) * $multiplier;
            $multiplier *= 128;
        }while(($data & 128) != 0);
        return $data;
    }

    //调试函数用来输出16进制
    public function printCommand($command)
    {
        $str = '';
        $length = strlen($command);
        for ($i = 0; $i < $length; $i++) {
            $str .= ("0x" . dechex(ord((($command[$i]))))." ");
        }
        echo $str."\n";
        return $str;
    }


    public function bindReceive(...$args)
    {
        $data = $args[3];
        $fd = $args[1];
        $server = $args[0];

        //如果说在套接字缓冲区里有数据
        if(isset($this->buffer[$fd]))
        {
            $data = $this->buffer[$fd].$data;
        }

        //计算出整个包的长度
        $dataLen = strlen($data);


        $leftLen = $dataLen;//没开始解包之前剩余的数据就是收到包的长度
        $packData = $data;//初始包就是接收的整个包

        $protocol = new \Structural\System\MQTTProxyProtocolStruct();

        //如果说剩余的长度大于0
        while($leftLen > 0)
        {

            //包不完整出现了半包直接放入到缓冲区中
            if($leftLen < 5)
            {
                $this->buffer[$fd] = $packData;
                $this->logger->trace(Logger::LOG_WARING,self::class,"bindReceive","[".self::class."->"."bindReceive"."] recv bytes is small;len:$dataLen");
                return true;
            }

            //解析协议的类型
            $protocol->type = unpack("C",$data[0])[1];
            $leftLen -= 1;

            //解析mqtt消息的类型
            $protocol->mqtt_type = unpack("C",$data[1])[1];
            $leftLen -= 1;

            //mqtt服务器的错误码
            $protocol->message_no = unpack("C",$data[2])[1];
            $leftLen -= 1;

            //解析载荷的长度，算法跟mqtt中的算法一致
            $remain_length = $this->remainLengthDecode($data[3]);
            $protocol->remain_length = $remain_length;
            $leftLen -= 1;

            //校验client_id 长度的合法性 半包或者是一个错误的包,继续放入缓冲区中，当超过一定长度之后直接清空掉
            if($remain_length > $leftLen)
            {
                return false;
            }

            //获取到客户端id
            $protocol->client_id = substr($data, 3, $remain_length);
            $leftLen -= $remain_length;

            $payload_len = unpack("C", $data[3 + $remain_length])[1];
            //半包或者是错误的包
            if($payload_len > $leftLen)
            {
                return false;
            }
            $payload = json_decode(substr($data, 4 + $remain_length, $payload_len) , 1);
            $protocol->payload = $payload;

            //拆包代理协议
            switch ($protocol->mqtt_type)
            {
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
            }
        }

        return true;
    }

    public function bindWorkerStop()
    {

    }

    public function bindPipeMessage(...$args)
    {

    }

    public function bindFinish(...$args)
    {

    }

    public function bindClose(...$args)
    {

    }

}