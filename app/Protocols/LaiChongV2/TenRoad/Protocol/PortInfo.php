<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017/4/21
 * Time: 14:05
 */

namespace Wormhole\Protocols\TenRoad\Protocol;


use Wormhole\Protocols\Library\BIN;
use Wormhole\Protocols\Library\Variable;
use Wormhole\Protocols\Library\BitArray;
use Wormhole\Protocols\Library\Tools;
class PortInfo extends Variable
{
    protected  $info = [
        "current"=>[BIN::class,2,TRUE],
        "left_time"=>[BIN::class,2,TRUE]
        //"status"=>[BIT::class,1,TRUE],
    ];
    protected $status = [
        BitArray::class,[['worke_state',1],['fuses',1],['overcurrent',1],['connect',1],['full',1],['start_up',1],['pull_out',1]],1
    ];


    //protected $field = ['current'=>2, 'left_time'=>2];
    //protected  $status = ["state", "fuses", "overcurrent", "connection", "full"];
    public $data = [];
    public $signal = 0;
    public $num;
    public $length = 12;
    public $value='15';//001300150010001100120013
    public $dir = TRUE;


    public function __construct()
    {
        //parent::__construct();
        //$this->length=$length;
        //$this->dir = $dir;
    }

    public function __invoke($value = "", &$position)
    {

        
        if(empty($value)){
            $this->value=0;
            return;
        }

        if(is_string($value)){

            //枪口数量
            //$num = substr($value, $position, 2);
            //$this->num =intval( Tools::arrayToDec( Tools::asciiStringToDecArray($num),$this->dir));
            //$position = $position + 2;

            //信号强度
            $bin = new BIN(1,TRUE);
            //call_user_func($bin, $value, $position);
            $bin($value, $position);
            $this->signal = $bin->getValue();

            //通道数量
            $bin = new BIN(1,TRUE);
            //call_user_func($bin, $value, $position);
            $bin($value, $position);
            $this->num = $bin->getValue();


            //可变参数
            for($i=0;$i<$this->num;$i++){
                //通道电流和剩余时间
                foreach ($this->info as $k=>$v){
                    //$variable = substr($value, $position, $v[1]);
                    //$this->data[$i]['$k'] = intval( Tools::arrayToDec( Tools::asciiStringToDecArray($variable),$this->dir));
                    //$position = $position + $v[1];
                    $current = new $v[0]($v[1], $v[2]);
                    $this->data[$i][$k] = $current;
                    //$this->info[$k] = $current;
                    //call_user_func($current, $value, $position);
                    $current($value, $position);

                }

                //设备状态
                $bitArray = new $this->status[0]($this->status[1],$this->status[2]);
                $this->data[$i]['status'] = $bitArray;
                //call_user_func($bitArray, $value, $position);
                $bitArray($value, $position);



            }


            //return;
        }

        if(is_int($value)){
            $this->value =$value;
            return;
        }




    }




    public function __toString()
    {
        return Tools::decArrayToAsciiString(Tools::decToArray($this->value,$this->length,$this->dir));
    }


}