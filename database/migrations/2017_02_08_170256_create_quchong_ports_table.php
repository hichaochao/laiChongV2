<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuChongPortsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quchong_ports', function (Blueprint $table) {

            //枪信息
            $table->uuid('id',36)->default("")->commnet('枪ID');
            $table->string('evse_id',36)->default("")->commnet('充电桩id');
            $table->string('code',10)->default("")->commnet('充电桩编号');
            $table->string('monitor_code',36)->default("")->commnet('moniotr桩编号');
            $table->tinyInteger('port_number')->unsigned()->comment('枪口号');
            $table->string('monitor_order_id',36)->default("")->commnet('moniotr订单号');
            $table->string('order_id',10)->default("")->commnet('订单号');

            //状态
            $table->tinyInteger('work_status')->unsigned()->default(0)->comment('工作状态 0/空闲 1/启动中 2/充电中 3/启动失败 4/停止中 5/停止失败');
            $table->tinyInteger('is_fuse')->unsigned()->default(0)->comment('0/未熔断 1/熔断'); //->nullable()
            $table->tinyInteger('is_flow')->unsigned()->default(0)->comment('0/未过流 1/过流');
            $table->tinyInteger('is_connect')->unsigned()->default(0)->comment('0/未连接 1/连接');
            $table->tinyInteger('is_full')->unsigned()->default(0)->comment('0/未充满 1/充满');
            $table->tinyInteger('start_up')->unsigned()->default(0)->comment('0/表示闭合继电器后无电流流过 1/表示闭合继电器后有电流流过');
            $table->tinyInteger('pull_out')->unsigned()->default(0)->comment('0/启动后电流未降为0 1/启动后电流降为0,认为被拔掉');

            $table->string('channel_status',200)->default('')->comment('通道状态 json字符串，记录通道详细状态');

            //启动数据
            $table->timestamp('start_time')->nullable()->commnet('充电开始时间');
            $table->timestamp('end_time')->nullable()->commnet('充电结束时间');
            $table->timestamp('response_time')->nullable()->commnet('启动充电响应时间');
            $table->float('charged_power')->default(0)->comment('本次充电电量（单位:kwh）');
            $table->tinyInteger('charge_type')->unsigned()->default(0)->comment('充电模式 时间/1 金额/2');
            $table->integer('charge_args')->unsigned()->default(0)->comment('充电时长(单位：分钟) 或 充电金额（单位：分）');
            $table->integer('renew')->unsigned()->default(0)->comment('续费数据');
            $table->tinyInteger('is_response',FALSE,TRUE)->default(0)->comment('启动续费停止是否收到桩响应 0/未收到 1/收到');
            $table->tinyInteger('operation_time',FALSE,TRUE)->default(0)->comment('启动续费停止接收桩响应监控次数, 不超过3次');

            //实时充电
            $table->integer('left_time')->unsigned()->default(0)->comment('剩余时间，单位：分钟');
            //$table->integer('duration')->unsigned()->default(0)->comment('充电时长，单位：分钟');
            $table->float('current')->unsigned()->default(0)->comment('电流，单位：A');


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
        Schema::dropIfExists('quchong_ports');
    }
}
