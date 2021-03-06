<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLaiChongV2TurnoverTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('laichongv2_turnover', function (Blueprint $table) {
            //营业额信息
            $table->uuid('id',32)->comment('营业额ID');
            $table->string('code',10)->comment('充电桩编号');
            $table->string('electricity_meter_number',20)->default("")->comment('电表编号');
            $table->integer('coin_number')->unsigned()->default(0)->comment('总投币次数; 单位：次');
            $table->integer('current_coin_number')->unsigned()->default(0)->comment('当天投币次数; 单位：次');

            $table->integer('card_free')->unsigned()->default(0)->comment('总刷卡金额; 单位：分');
            $table->integer('current_card_free')->unsigned()->default(0)->comment('当天刷卡金额; 单位：分');

            $table->float('charged_power')->unsigned()->default(0)->comment('电表读数,总电量; 单位：kwh');
            $table->float('current_charged_power')->unsigned()->default(0)->comment('电表读数,当天电量; 单位：kwh');

            $table->timestamp('stat_date')->nullable()->commnet('统计日期');

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
        Schema::dropIfExists('laichongv2_turnover');
    }
}
