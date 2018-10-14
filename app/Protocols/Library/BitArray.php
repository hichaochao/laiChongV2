<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:14
 */

namespace Wormhole\Protocols\Library;


class BitArray implements \JsonSerializable
{
    use Tools;
    private $value=0;
    public $length;
    protected $statusBit = [];
    protected $status = [BIN::class,1];
    public function __construct($bitArr, $length=1)
    {
        $this->length=$length;
        foreach ($bitArr as $k=>$v){
            $this->statusBit[$v[0]] = $v[1];
        }
    }
    public function getValue(){
        return $this->statusBit;
    }
    public function getClassNamw(){
        return BIN::class;
    }
    public function __invoke($value="", &$position)
    {

        if(is_string($value)){

            $bin = new $this->status[0]($this->status[1]); //实例化bin
            //call_user_func($bin, $value, $position); //解析状态帧
            $bin($value, $position);
            $status = $bin->getValue(); //取得值
            $status = decbin($status);  //转换为二进制
            $status = str_pad($status, 8, "0", STR_PAD_LEFT);
            $len = strlen($status);
            foreach($this->statusBit as $k=>$v){
                $this->statusBit[$k] = substr($status,--$len,$v);
            }

            //return $position;
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