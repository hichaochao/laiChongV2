<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2016-11-29
 * Time: 18:04
 */

namespace Wormhole\Validators;
use Illuminate\Support\Facades\Validator;

class StartChargeValidator extends Validator
{
    public function rule(){
        return [
            'code'       =>'required|string|max:36',         //桩编号
            'port_numbers' => 'required|string|max:15',      //通道号
            'charge_time' => 'required|integer',            //充电时间
        ];
    }
    public function message(){
        return [
            //'params.evse_code' => '需要monitor的充电桩编号',
            //'params.start_type' => '',
            //'params.charge_type' => '',
            //'params.charge_args' => '',
            //'params.user_id' => '',
            //'params.user_balance' => '',

        ];
    }

    public function make($data){
        return Validator::make($data,$this->rule(),$this->message());
    }
}
