<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLaiChongV2ChargeOrderMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('laichongv2_charge_order_mappings', function (Blueprint $table) {
            $table->uuid('id')->comment("映射id");
            $table->string('evse_id',36)->comment("充电桩id");
            $table->string('port_id',36)->comment("充电抢id");
            $table->string('code',10)->comment("充电桩code");
            $table->string('monitor_order_id',36)->default("")->commnet('moniotr订单号');
            $table->string('order_id',10)->default("")->commnet('订单号');
            $table->integer('charge_args')->unsigned()->default(0)->comment('充电时长(单位：分钟)');
            $table->tinyInteger('is_success')->unsigned()->default(0)->comment("是否成功，0：启动失败，1：启动成功，2停止失败，3停止成功");
            $table->integer('stop_reason')->unsigned()->default(0)->comment('结束原因 0/时间用完 1/保险丝断 2/充电过流 3/充电器拔掉 4/充满 5/充电时间用时超过12小时 6主动停止');
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
        Schema::dropIfExists('laichongv2_charge_order_mappings');
    }
}
