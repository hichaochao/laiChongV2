<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuChongModifyInfoLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quchong_modify_info_log', function (Blueprint $table) {


            $table->uuid('id',36)->default("")->commnet('日志id');

            //桩信息
            $table->string('before_info',200)->default("")->commnet('修改之前信息');
            $table->string('after_info',200)->default("")->commnet('修改之后信息');
            $table->timestamp('modify_time')->nullable()->commnet('修改时间');
            $table->string('remarks',100)->default("")->commnet('备注');

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
