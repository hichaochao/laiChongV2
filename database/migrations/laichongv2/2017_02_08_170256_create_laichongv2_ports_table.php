<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLaiChongV2PortsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('laichongv2_ports', function (Blueprint $table) {

            //枪信息
            $table->uuid('id',36)->default("")->commnet('枪ID');
            $table->string('evse_id',36)->default("")->commnet('充电桩id');
            $table->string('code',10)->default("")->commnet('充电桩编号');
            $table->string('monitor_code',36)->default("")->commnet('moniotr桩编号');
            $table->tinyInteger('port_number')->unsigned()->comment('枪口号');
            $table->string('monitor_order_id',36)->default("")->commnet('moniotr订单号');
            $table->integer('order_no')->unsigned()->default(1)->comment('单号');

            //状态
            $table->tinyInteger('work_status')->unsigned()->default(0)->comment('工作状态 0/空闲 1/启动中 2/充电中 3/启动失败 4/停止中 5/停止失败');

            //启动数据
            $table->timestamp('start_time')->nullable()->commnet('充电开始时间');
            $table->timestamp('end_time')->nullable()->commnet('充电结束时间');
            $table->timestamp('response_time')->nullable()->commnet('启动充电响应时间');
            $table->integer('charge_args')->unsigned()->default(0)->comment('充电时长(单位：分钟)');
            $table->integer('renew')->unsigned()->default(0)->comment('续费数据');
            $table->tinyInteger('is_response',FALSE,TRUE)->default(0)->comment('启动续费停止是否收到桩响应 0/未收到 1/收到');
            $table->tinyInteger('operation_time',FALSE,TRUE)->default(0)->comment('启动续费停止接收桩响应监控次数, 不超过3次');

            //实时充电
            $table->integer('left_time')->unsigned()->default(0)->comment('剩余时间，单位：分钟');

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
