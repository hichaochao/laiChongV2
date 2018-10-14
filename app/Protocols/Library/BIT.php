<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:14
 */

namespace Wormhole\Protocols\Library;


class BIT implements \JsonSerializable
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

    public function __invoke($value="")
    {

        if(is_string($value)){
            $this->value =decbin(intval( self::arrayToDec( self::asciiStringToDecArray($value),$this->dir)));
            return;
        }
    }

    public function __toString()
    {

        return self::decArrayToAsciiString(self::decToArray($this->value,$this->length,$this->dir));
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