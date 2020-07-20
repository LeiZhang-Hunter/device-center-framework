<?php
/**
 * Description:
 * Created by PhpStorm.
 * User: 张磊
 * Date: 2018/12/16
 * Time: 14:39
 */
namespace Pendant;

use Library\Logger\Logger;
use Structural\System\ConfigStruct;
use Structural\System\EventStruct;
use Structural\System\SwooleProtocol;

class SwooleSysSocket{

    /**
     * @var SwooleSysSocket
     */
    private static $instance;

    /**
     * @var \swoole_server
     */
    public static $swoole_server;

    /**
     * @var \Closure
     */
    private static $beforeHook;

    /**
     * @var \Closure
     */
    private static $finishHook;

    /**
     * @var SysConfig
     */
    public $config;

    private $ip;

    private $port;

    private $monitor_list = [];

    /**
     * @var Logger
     */
    public $logger;


    public function __construct()
    {
    }

    /**
     * @param Logger $instance
     */
    public function setLogger(Logger $instance)
    {
        $this->logger = $instance;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param $ip
     * @param $port
     * @param $type
     * @param $controller
     * @param $protocol_type
     */
    public function addMonitor($ip,$port,$type,$controller, $protocol_type)
    {
        $this->monitor_list[] = [
            "ip"=>$ip,
            "port"=>$port,
            "type"=>$type,
            "controller"=>$controller,
            "protocol"=>$protocol_type
        ];
    }

    public function regBeforeHook($beforeFunction)
    {
        self::$beforeHook = \Closure::bind($beforeFunction,$this);
    }

    public function regFinishHook($endFunction)
    {
        self::$finishHook = \Closure::bind($endFunction,$this);
    }

    /**
     * Description:获取系统实例
     * @return SwooleSysSocket
     */
    public static function getInstance()
    {
        if(!self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    //启动服务
    public function run()
    {
        //加载初始化环境通过php.ini检查这个环境到底是测试环境还是说是一个正式环境
        FrameworkEnv::init();
        //加载配置文件中的配置
        $config_instance = SysConfig::getInstance();
        $this->config = $config_instance->getSysConfig();
        //使用命令行解析工具去解析命令
        FrameworkEnv::parseCmd();
        if(is_callable(self::$beforeHook))
        {
            call_user_func_array(self::$beforeHook,[$this]);
        }

        if(!isset($this->config[ConfigStruct::SERVER]))
        {
            throw new \Exception("config (".ConfigStruct::SERVER.") is not exist;stack:(SwooleSysSocket::run)");
        }

        //获取监控的服务列表，并且加入监控
        $server_list = $this->config[ConfigStruct::SERVER];
        if(!is_array($server_list))
        {
            throw new \Exception("config (".ConfigStruct::SERVER.") must be array;stack:(SwooleSysSocket::run)");
        }

        foreach ($server_list as $server)
        {
            if(!isset($server[ConfigStruct::S_IP]))
            {
                throw new \Exception("config (".ConfigStruct::SERVER."=>".ConfigStruct::S_IP.") is not exist;
                stack:(SwooleSysSocket::run)");
            }

            if(!isset($server[ConfigStruct::S_PORT]))
            {
                throw new \Exception("config (".ConfigStruct::SERVER."=>".ConfigStruct::S_PORT.") is not exist;
                stack:(SwooleSysSocket::run)");
            }

            //服务的类型
            if(!isset($server[ConfigStruct::S_TYPE]))
            {
                throw new \Exception("config (".ConfigStruct::SERVER."=>".ConfigStruct::S_TYPE.") is not exist;
                stack:(SwooleSysSocket::run)");
            }

            //回调的控制器
            if(!isset($server[ConfigStruct::S_CONTROLLER]))
            {
                throw new \Exception("config (".ConfigStruct::SERVER."=>".ConfigStruct::S_CONTROLLER.") is not exist;
                stack:(SwooleSysSocket::run)");
            }

            //支持解析的协议类型
            if(!isset($server[ConfigStruct::S_PROTOCOL_TYPE]))
            {
                throw new \Exception("config (".ConfigStruct::SERVER."=>".ConfigStruct::S_PROTOCOL_TYPE.") is not exist;
                stack:(SwooleSysSocket::run)");
            }

            $this->addMonitor($server[ConfigStruct::S_IP], $server[ConfigStruct::S_PORT], $server[ConfigStruct::S_TYPE],
                $server[ConfigStruct::S_CONTROLLER], $server[ConfigStruct::S_PROTOCOL_TYPE]);
        }

        $sys_factory = new SysFactory();
        //移出主要的server信息
        $server = array_shift($this->monitor_list);
        //监控服务
        self::$swoole_server = new \swoole_server($server[ConfigStruct::S_IP],$server[ConfigStruct::S_PORT],SwooleProtocol::Mode, SwooleProtocol::TCP_PROTOCOL);
        //注册服务 ip 端口 和协议类型 进入服务
        $sys_factory->regServerController($server[ConfigStruct::S_TYPE],$server[ConfigStruct::S_CONTROLLER],
            $server[ConfigStruct::S_PROTOCOL_TYPE]);
        //绑定注册的服务
        EventStruct::bindEvent($server[ConfigStruct::S_TYPE],self::$swoole_server, $server[ConfigStruct::S_PROTOCOL_TYPE]);

        //监控其余的端口
        foreach ($this->monitor_list as $monitorInfo) {
            $monitor_type = $monitorInfo[ConfigStruct::S_TYPE];
            $controllerName = $monitorInfo[ConfigStruct::S_CONTROLLER];
            //注册接收的server
            $sys_factory->regServerController($monitor_type,$controllerName, $monitorInfo[ConfigStruct::S_PROTOCOL_TYPE]);
            //绑定处理事件
            $service_object = self::$swoole_server->addListener($monitorInfo[ConfigStruct::S_IP],$monitorInfo[ConfigStruct::S_PORT],$monitorInfo[ConfigStruct::S_TYPE]);
            EventStruct::bindEvent($monitor_type,$service_object, $monitorInfo[ConfigStruct::S_PROTOCOL_TYPE]);
        }
        if(isset($this->config[ConfigStruct::S_TASK_WORKER_NUM]))
        {
            $sys_factory->setTaskNumber($this->config[ConfigStruct::S_TASK_WORKER_NUM]);
        }
        //加入配置文件
        self::$swoole_server->set($this->config);
        //运行程序
        self::$swoole_server->start();
    }
}