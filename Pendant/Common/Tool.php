<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 19-10-24
 * Time: 下午10:21
 */
namespace Pendant\Common;
class Tool{
    public static function ntohll($netData)
    {
        $number = 0x0000;
        $number = ($number << 8)|bin2hex($netData[0]);
        $number = ($number << 8)|bin2hex($netData[1]);
        $number = ($number << 8)|bin2hex($netData[2]);
        $number = ($number << 8)|bin2hex($netData[3]);
        $number = ($number << 8)|bin2hex($netData[4]);
        $number = ($number << 8)|bin2hex($netData[5]);
        $number = ($number << 8)|bin2hex($netData[6]);
        $number = ($number << 8)|(bin2hex(($netData[7])));
        return hexdec($number);
    }

    //解析字节，将字节变为长度
    public static function remainLengthDecode($data)
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

    //压缩字节，将字节变为长度
    public static function remainLengthEncode($length)
    {
        $data = "";
        do{
            $digit = $length % 128;
            $length = $length / 128;
            if($length > 0)
            {
                $digit = $digit | 0x80;
            }

            $data .= pack("C", $digit);
        }while($length > 0);

        return $data;
    }
}