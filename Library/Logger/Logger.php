<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 19-11-12
 * Time: 下午3:45
 */

namespace Library\Logger;

use Pendant\SwooleSysSocket;
use Pendant\SysConfig;
use Pendant\SysFactory;
use Structural\System\ConfigStruct;

class Logger
{

    const LOG_WARING = "WARING";

    const LOG_ERROR = "ERROR";

    private $log_dir;

    public function __construct()
    {
        $config = SwooleSysSocket::getInstance()->config;
        $this->log_dir = isset($config[ConfigStruct::SEN_LOG_FILE]) ? $config[ConfigStruct::SEN_LOG_FILE] : "";
    }

    public function trace($level, $class, $method, $log, $file = __FILE__, $line = __LINE__)
    {
        if (!$this->log_dir) {
            trigger_error("please set " . ConfigStruct::SEN_LOG_FILE . " in configure", E_USER_WARNING);
            return false;
        }
        $date = date("Y-m-d H:i:s", time());

        $msg = "[$date] $level $class->$method --$log--\n";

        $dateExt = date("Ymd", time());

        $file_name = $this->log_dir . "trace_" . $dateExt . ".log";

        echo $msg;
        //放入文件
        return file_put_contents($file_name, $msg, FILE_APPEND);
    }

}