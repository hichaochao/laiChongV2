<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuChongChargeRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quchong_charge_records', function (Blueprint $table) {


            $table->string('id',40)->default("")->commnet('电桩充电记录ID');
            //桩数据
            $table->string('code',10)->default("")->commnet('充电桩编号');
            $table->integer('port_number')->unsigned()->default(0)->comment('充电枪口号');


            //充电信息
            $table->string('monitor_order_id',36)->default("")->commnet('moniotr订单号');
            $table->string('order_id',10)->default("")->commnet('订单号');
            //$table->integer('duration')->unsigned()->default(0)->comment('充电时长，单位：分钟');
            $table->tinyInteger('charge_type')->unsigned()->comment('充电模式 时间/1 金额/2');
            $table->integer('charge_args')->unsigned()->default(0)->comment('充电时长(单位：分钟) 或 充电金额（单位：分）');
            $table->timestamp('start_time')->nullable()->commnet('充电开始时间');
            $table->timestamp('end_time')->nullable()->commnet('充电结束时间');
            $table->integer('stop_reason')->unsigned()->default(0)->comment('结束原因 0/时间用完 1/保险丝断 2/充电过流 3/充电器拔掉 4/充满 5/充电时间用时超过12小时 6主动停止');
            $table->float('charged_power')->default(0)->comment('本次充电电量（单位:kwh）');
            //$table->integer('charged_fee')->unsigned()->default(0)->comment('本次充电金额（单位：分）');
            $table->integer('left_time')->unsigned()->default(0)->comment('剩余时间，单位：分钟');
            $table->timestamp('charge_records_time')->nullable()->commnet('充电记录生成时间');

            $table->timestamps();
            $table->softDeletes();
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
