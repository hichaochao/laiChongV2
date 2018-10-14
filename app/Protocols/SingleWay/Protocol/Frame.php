<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 17:59
 */

namespace Wormhole\Protocols\QianNiu\Protocol;
use Wormhole\Protocols\Library\ASCII;
use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\CRC16;
use Wormhole\Protocols\Library\Variable;
use Wormhole\Protocols\QianNiu\Protocol\PortInfo;
use Illuminate\Support\Facades\Log;
class Frame implements \JsonSerializable
{
    public $isValid = FALSE;
    //起始域
    protected  $start = 0xAA;
    //数据长度,从起始域到检验位
    protected $length = [BIN::class,2,TRUE];
    //版本号
    protected $version = [BIN::class,1,TRUE];
    //流水号
    protected $serial_number = [BIN::class,4,TRUE];
    //设备编号
    protected $code = [BIN::class,4,TRUE];
    //指令
    protected $operator = [BIN::class,2,TRUE];
    /**
     * @var CRC16 CRC16-MODBUS校验
     */
    protected $check =[CRC16::class,2,TRUE];


    //初始化数据
    public function __construct()
    {
        //获取对象的所有属性
        $properties = get_object_vars($this);

        foreach ($properties as $key=>$value){
            $repeat = count($value);

            if(1 == $repeat && !class_exists($value)){
                $this->$key = $value;
                continue;
            }

            if(1 == $repeat && class_exists($value)){

                $this->$key = new $value;
                continue;
            }


            $class = $value[0];
            $length = 1 < $repeat ? $value[1] : NULL;
            $dir = 3 == $repeat ? $value[2] : NULL;
            if(class_exists($class)){
                if(is_null($length) && is_null($dir)){

                    $this->$key = new $class();
                    continue;
                }

                if(!is_null($length) && is_null($dir)){

                    $this->$key = new $class($length);
                    continue;
                }

                if(!is_null($length) && !is_null($dir)){
                    $this->$key = new $class($length,$dir);
                    //初始化版本号和流水号值
                    if($key == 'version'){
                        $this->$key(1);
                    }elseif ($key == 'serial_number'){
                        $this->$key(time());
                    }
                    continue;
                }
            }

        }


    }

    //组装帧
    public function __toString()
    {

        $str = "";
        //return parent::__toString(); // TODO: Change the autogenerated stub
        //获取对象的所有属性
        $properties = get_object_vars($this);
        //去除frame属性,只留数据域属性
        $this->unsetProperty($properties);

        $dataArea = '';
        //组装数据域
        foreach ($properties as $key=>$value){
            $dataArea .= $value;
        }
        //起始头
        $frame = chr($this->start);
        $position = 0;
        //帧长度分别是 数据域长度+起始域长度+数据长度+版本号+流水号+设备编号+指令+校验
        $this->length(strlen($dataArea)+1+2+1+4+4+2+2);
        $frame .=strval($this->length);
        //版本号
        $frame .=strval($this->version);
        //流水号
        $frame .=strval($this->serial_number);
        //桩编号
        $frame .=strval($this->code);
        //指令
        $operator = $this->operator($this->instructions);
        $frame .=strval($this->operator);

        $frame .=strval($dataArea);


        //校验
        $this->check($frame);
        $frame.=strval($this->check);

        return $frame;
    }


    public function __get($name)
    {

        return $this->$name;
    }
    function jsonSerialize()
    {
        $array = [];
        foreach ($this as $key=>$value){

            $array[$key]= $value;
        }
        return $array;
    }

    //给数据域字段赋值
    public function __call($name, $arguments)
    {


        if(is_object($this->$name)) {
            $name = $this->$name;
            $name($arguments[0]);
        }else{
            $type = gettype($this->$name);
            switch ($type){
                case "boolean":{
                    $this->$name=boolval($arguments[0]);
                    break;
                }
                case "string":{
                    $this->$name=strval($arguments[0]);
                    break;
                }
                case "float":{
                    $this->$name=floatval($arguments[0]);
                    break;
                }
                case "array":{
                    $this->$name=array_values($arguments[0]);
                    break;
                }
                case "object":{
                    call_user_func($this->$name, $arguments[0]);
                }
            }
        }
    }

    //解析帧
    public function __invoke($value)
    {
        $data = [];
        //截取校验数据的位置
        $checkPosition = 0;
        $firstPosition = 0;

        //判断帧长度
        if(strlen($value)<16){
            return false;
        }

        //起始位置
        $position = 0;
        //获取对象所有属性
        $properties = get_object_vars($this);
        $this->unsetProperty($properties);

        //如果是多个帧,循环解析多个
        while ($position < strlen($value)) {
            //起始域
            $startArea = new BIN(1, TRUE);
            $startArea($value, $position);

            //起始域是否正确
            if ($startArea->getValue() != $this->start) {
                Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 无效开始 ");
                return false;
            }

            //数据长度
            $leng = $this->length;
            $leng($value, $position);
            //判断帧长度是否正确
//            if($leng->getValue() != strlen($value)){
//                Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 帧长度不正确 ");
//                return false;
//            }
            //版本号
            $version = $this->version;
            $version($value, $position);

            //流水号
            $serial_number = $this->serial_number;
            $serial_number($value, $position);

            //设备编号
            $code = $this->code;
            $code($value, $position);

            //指令
            $operator = $this->operator;
            $operator($value, $position);

            //如果是第一次进来，获取指令的时候
            if (empty($properties)) {
                return $this;
            }

            //数据域
            //判断有没有可变参数,有用可变参数方法处理
            $flag = 0;
            foreach ($properties as $key => $v) {
                if ($this->$key instanceof Variable) {
                    $flag = 1;
                    $variable = $this->$key;
                    $variable($value, $position);
                }
            }

            //没有可变参数，正常处理
            if ($flag == 0) {
                foreach ($properties as $key => $v) {

                    $faild = $this->$key;
                    $faild($value, $position);

                }
            }

            //校验
            $check = $this->check;
            $check($value, $position, $checkPosition, $firstPosition);
            if (!$check->getValue()) {
                Log::debug(__NAMESPACE__ .  "/" . __CLASS__."/" . __FUNCTION__ . "@" . __LINE__ . " 校验不正确 ");
                return false;
            }

            //下一帧校验位置
            if ($checkPosition == 0) {
                $checkPosition = $firstPosition = $position + $checkPosition;
            } else {
                $checkPosition = $checkPosition + $firstPosition;
            }
            //如果有多个帧,由于实例化的是一个对象,许克隆后放进数组
            $data[] = clone $this;
        }
        $this->isValid = TRUE;
        if(count($data) > 1){
            return $data;
        }
        return $this;
    }

    private function unsetProperty(array &$properties){
        //起始域
        unset($properties['start']);
        //长度
        unset($properties['length']);
        //版本
        unset($properties['version']);
        //功能码
        unset($properties['serial_number']);
        //桩编号
        unset($properties['code']);
        //命令码
        unset($properties['operator']);
        //校验
        unset($properties['check']);
        //解析是否成功标志
        unset($properties['isValid']);
        //数据域中指令
        unset($properties['instructions']);

    }


    //克隆某个对象
    public function __clone() {

        $properties = get_object_vars($this);
        unset($properties['instructions']);
        unset($properties['start']);
        unset($properties['isValid']);
        foreach ($properties as $key => $v) {

            $this->$key = clone $this->$key;

        }


    }

    //验证帧的正确性,并返回帧的长度
    public function verify($value){

        //验证帧长度是否正确
        if(strlen($value)<16){
            return false;
        }

        //起始位置
        $position = 0;
        $properties = get_object_vars($this);
        $this->unsetProperty($properties);

        $startArea = new BIN(1, TRUE);
        $startArea($value, $position);

        //帧头是否正确
        if ($startArea->getValue() != $this->start) {

            return false;
        }

        $leng = $this->length;
        $leng($value, $position);
        //判断帧长度是否正确
        if($leng->getValue() > strlen($value)){
            return false;
        }

        $position = $position+1+4+4+2+$leng->getValue();
        $check = $this->check;
        $check($value, $position);
        if (!$check->getValue()) {
            return false;
        }

        return $leng->getValue();


    }





}