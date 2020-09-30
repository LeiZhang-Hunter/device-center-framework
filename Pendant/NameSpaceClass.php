<?php

/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-22
 * Time: 下午8:41
 */
class NameSpaceClass
{
    static $namespace;

    public static function addNameSpace($namespace, $dir)
    {
        self::$namespace[$namespace] = $dir;
    }

    public static function autoload()
    {
        //自动加载
        spl_autoload_register(function ($var) {
            $collect = explode("\\", $var);
            $namespace = array_shift($collect);
            if (isset(self::$namespace[$namespace])) {
                $dir = realpath(self::$namespace[$namespace] . "/" . implode("/", $collect) . ".php");
                if (is_file($dir)) {
                    include_once $dir;
                }
            } else {
                $dir = realpath(__ROOT__ . "/" . str_replace("\\", "/", $var) . ".php");
                if (is_file($dir)) {
                    include_once $dir;
                }
            }
        });
    }

}