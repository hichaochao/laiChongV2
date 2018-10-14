<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:14
 */

namespace Wormhole\Protocols\Library;


class CRC16 implements \JsonSerializable
{
    use Tools;
    private $value=0;
    public $length;
    protected $dir=TRUE;
    public function __construct($length=4,$dir=true)
    {
        $this->length=$length;
        $this->dir = $dir;
    }

    public function getValue(){
        return $this->value;
    }

    public function __invoke($value="", &$position=0, $checkPosition=0, $firstPosition=0)
    {
        if($firstPosition == 0){
            $firstPosition = $position;
        }else{
            $firstPosition = $firstPosition - 2;
        }

        //计算出校验码
        if($position == 0){
            $frame = self::asciiStringToDecArray($value);
            $crc = Tools::crc16($frame, count($frame));
            $crc_arr = Tools::decToArray( $crc,2);
            $this->value = $crc_arr;
        }
        //解析对比校验码
        if($position != 0){
            //计算校验码
            //$frame = substr($value, 0, $position);
            $frame = substr($value, $checkPosition, $firstPosition);

            $frame = self::asciiStringToDecArray($frame);
            $crc = Tools::crc16($frame, count($frame));
            $crc_arr = Tools::decToArray( $crc,2);
            $crc_arr = intval( self::arrayToDec( $crc_arr,$this->dir));

            //解析校验码
            $str = substr($value, $position, $this->length);
            $check = intval( self::arrayToDec( self::asciiStringToDecArray($str),$this->dir));

            $position = $position+$this->length; //当前字段位置
            if($crc_arr == $check){
                $this->value = 1;
            }else{
                $this->value = 0;
            }


        }


    }

    public function __toString()
    {

        return self::decArrayToAsciiString($this->value);
        //return self::decArrayToAsciiString(self::decToArray($this->value,$this->length,$this->dir));

    }

    function jsonSerialize()
    {
        $array = [];
        foreach ($this as $key=>$value){

            $array[$key]= $value;
        }
        return $array;
    }

}