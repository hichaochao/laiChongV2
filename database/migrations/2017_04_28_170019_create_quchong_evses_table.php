<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuChongEvsesTable extends Migration
{


    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quchong_evses', function (Blueprint $table) {

            //桩信息
            $table->uuid('id',32)->comment('充电桩ID');
            $table->string('code',10)->unique()->comment('充电桩编号');
            $table->string('worker_id',20)->default('')->comment('worker的ID');
            $table->string('protocol_name',20)->default('')->comment('充电枪所用协议名称');
            $table->tinyInteger('channel_num',FALSE,TRUE)->default(0)->comment('通道数量');
            $table->string('identification_number',20)->default('')->comment('设备识别号');
            $table->integer('serial_number')->unsigned()->default(1)->comment('流水号');
            //状态信息
            $table->tinyInteger('online_status',FALSE,TRUE)->default(0)->comment('是否离线 0/离线,1/在线');
            $table->timestamp('last_update_status_time')->nullable()->comment('最后更新充电状态时间');
            $table->tinyInteger('signal_intensity',FALSE,TRUE)->default(0)->comment('信号强度');

            //通信信息
            $table->tinyInteger('heartbeat_cycle',FALSE,TRUE)->default(1)->comment('心跳周期');
            $table->string('domain_name',30)->default('')->comment('域名');
            $table->integer('port_number')->unsigned()->default(0)->comment('端口号');
            $table->string('parameter',200)->default('')->comment('参数,json字符串,key表示功能,value表示值');
            $table->string('device_id',20)->default('')->comment('设备ID');
            $table->tinyInteger('operation_time',FALSE,TRUE)->default(0)->comment('设置或者查询监控次数, 不超过3次');


            $table->string('request_result',200)->default('')->comment('设置参数结果');

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
        Schema::dropIfExists('quchong_evses');
    }
}
