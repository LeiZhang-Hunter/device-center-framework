<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-20
 * Time: 下午7:16
 */
namespace Pendant;

class FrameworkEnv
{

    static $project_dir;

    private static $pid = 0;

    private static $beforeStopHook;

    private static $fp;

    private static $service_name = "device-center";

    public static function setServiceName($device_name)
    {
        self::$service_name = $device_name;
    }

    //检查这个项目文件到底是测试环境还是正式环境，通过php.ini来判断
    public static function init()
    {
        if(!is_dir(self::$project_dir))
        {
            trigger_error("project_dir  is not INIT ; stack:(FrameworkEnv::init)", E_USER_ERROR);
            return false;
        }
        $env = get_cfg_var('env.name');
        if(!$env)
        {
            define("ENV", "develop");
        }else{
            define("ENV", $env);
        }

        define("CONFIG_DIR",realpath(self::$project_dir."/Config/".ENV));
    }

    //设置项目的根路径
    public static function setProjectDir($project_dir)
    {
        if(!is_dir($project_dir))
        {
            trigger_error("project_dir ($project_dir) is not exist;stack:(FrameworkEnv::setProjectDir)", E_USER_ERROR);
            return false;
        }else{
            self::$project_dir = $project_dir;
            return true;
        }
    }

    //生成配置文件
    public static function makePid($file,$pid)
    {
        file_put_contents($file, $pid);
    }

    public static function getPid($process_pid_file_name)
    {
        if(!isset(SwooleSysSocket::getInstance()->config[$process_pid_file_name]))
        {
            trigger_error("pid config is not exist ; stack:(FrameworkEnv::getPid)", E_USER_ERROR);
            return 0;
        }
        $file = SwooleSysSocket::getInstance()->config[$process_pid_file_name];
        if(is_file($file)) {
            return (int)file_get_contents($file);
        }else{
            return 0;
        }
    }

    //注册命令
    public static function parseCmd()
    {
        global $argv;

        $command = isset($argv[1]) ? $argv[1] : "";
        if($command != "stop" && $command!="start" && $command != "reload")
        {
            exit("please input stop|start|reload\n");
        }

        $config_pid_key = self::$service_name."-pid-file";

        $pid = self::getPid($config_pid_key);
        $pid_file = SwooleSysSocket::getInstance()->config[$config_pid_key];
        if($command == "stop")
        {
            if(!$pid)
            {
                exit("process not run\n");
            }else{

                //触发下线前置钩子
                if(is_callable(self::$beforeStopHook)) {
                    call_user_func(self::$beforeStopHook);
                }

                //发送关闭信号
                posix_kill($pid,SIGTERM);
                exit();
            }
        }else if($command == "start")
        {
            //如果说是一个文件
            self::$fp = fopen($pid_file,"w+");
            //检查文件是否被加锁了，加锁了那么就说明文件还在启动，就不能执行启动操作了
            $lockRes = flock(self::$fp,LOCK_EX|LOCK_NB);

            if(!$lockRes)
            {
                //这个文件已经启动了
                exit("process has run:".self::$service_name.";".__FILE__."\n");
            }
            self::makePid($pid_file,posix_getpid());
        }else{
            if(!$pid)
            {
                exit("process not run");
            }else{
                //发送重载信号
                posix_kill($pid,SIGUSR1);
                exit(0);
            }

        }
    }

    public static function loadPid($pid)
    {
        self::$pid = $pid;
    }

}