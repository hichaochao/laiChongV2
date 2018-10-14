<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2016-11-29
 * Time: 18:04
 */

namespace Wormhole\Validators;


use Illuminate\Support\Facades\Validator;

class StartChargeValidator extends Validator
{


    public function rule(){
        return [

            'order_id'   =>'required|string|max:36',         //订单标识
            'code' => 'required|string|max:15',             //monitor的充电桩编号
            'charge_type' => 'required|integer|max:4',         //充电模式：1：时间，2：金额
            'charge_args' => 'required|integer',


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
