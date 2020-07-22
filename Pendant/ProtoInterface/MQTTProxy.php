<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 20-7-22
 * Time: 下午8:55
 */
namespace Pendant\ProtoInterface;

interface MQTTProxy{
    public function onConnect();

    public function onDisConnect();

    public function onSubscribe();

    public function onUnSubscribe();

    public function onPublish();
}