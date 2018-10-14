<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017/4/21
 * Time: 14:05
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol;


use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\MultipleByte;
use Wormhole\Protocols\Library\BitArray;
use Wormhole\Protocols\Library\Tools;
class ElectricityInfo extends MultipleByte
{


    public $length = 0;
    public $value=[];
    public $dir = TRUE;


    public function __construct($length=4,$dir=true)
    {
        $this->length=$length;
        $this->dir = $dir;
    }

    public function getValue(){
        return $this->value;
    }

    public function __invoke($value = "", &$position=0)
    {


        if(empty($value)){
            $this->value[] = 0;
            return;
        }

        if(is_string($value)){

            $len = $this->length / 2;
            for ($i=0;$i<$len;$i++){
                $str = substr($value, $position, 2);
                $this->value[] =intval( self::arrayToDec( self::asciiStringToDecArray($str),$this->dir));
                $position = $position+2; //当前字段位置

            }

        }

        if(is_int($value)){
            $this->value[] = $value;
            return;
        }




    }




    public function __toString()
    {
        //return Tools::decArrayToAsciiString(Tools::decToArray($this->value,$this->length,$this->dir));
    }


}