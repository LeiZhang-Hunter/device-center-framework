<?php
/**
 * Created by PhpStorm.
 * User: zhanglei
 * Date: 19-10-24
 * Time: 下午10:21
 */

namespace Pendant\Common;
class Tool
{
    public static function ntohll($netData)
    {
        $number = 0x0000;
        $number = ($number << 8) | bin2hex($netData[0]);
        $number = ($number << 8) | bin2hex($netData[1]);
        $number = ($number << 8) | bin2hex($netData[2]);
        $number = ($number << 8) | bin2hex($netData[3]);
        $number = ($number << 8) | bin2hex($netData[4]);
        $number = ($number << 8) | bin2hex($netData[5]);
        $number = ($number << 8) | bin2hex($netData[6]);
        $number = ($number << 8) | (bin2hex(($netData[7])));
        return hexdec($number);
    }

    //解析字节，将字节变为长度
    public static function remainLengthDecode($buffer, &$head_bytes)
    {
        $multiplier = 1;
        $head_bytes = 0;
        $value = 0;
        do {
            if (!isset($buffer[$head_bytes])) {
                $head_bytes = 0;
                return 0;
            }
            $digit = ord($buffer[$head_bytes]);
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $head_bytes++;
        } while (($digit & 128) != 0);
        return $value;
    }

    //压缩字节，将字节变为长度
    public static function remainLengthEncode($length)
    {
        $data = "";
        do {
            $digit = $length % 128;
            $length = $length >> 7;
            if ($length > 0) {
                $digit = $digit | 0x80;
            }
            $data .= chr($digit);
        } while ($length > 0);

        return $data;
    }

    //调试函数用来输出16进制
    public static function printCommand($command)
    {
        $str = '';
        $length = strlen($command);
        for ($i = 0; $i < $length; $i++) {
            $str .= ("0x" . dechex(ord((($command[$i])))) . " ");
        }
        echo $str . "\n";
        return $str;
    }

    public static function printCommandChr($command)
    {
        $str = '';
        $length = strlen($command);
        for ($i = 0; $i < $length; $i++) {
            $code = ord(((($command[$i]))));
            if (!$code) {
                $str .= (($code) . " ");
            } else {
                $str .= hexdec(chr($code) . " ");
            }
        }
        echo $str . "\n";
        return $str;
    }
}