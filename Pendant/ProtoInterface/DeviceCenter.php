<?php

namespace Pendant\ProtoInterface;

interface DeviceCenter
{
    public function onConnect();

    public function onReceive();

    public function onClose();
}