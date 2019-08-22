<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyProjectsAddDuration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('duration')->default(12);
        });

        // $sql =
        //     "UPDATE `projects` `p2` SET `duration`=(SELECT PERIOD_DIFF(DATE_FORMAT(`end_date`, '%Y%m'),DATE_FORMAT(`start_date`, '%Y%m')) from `projects` `p1` WHERE `p1`.`id`=`p2`.`id`)";
        // DB::connection()
        //     ->getPdo()
        //     ->exec($sql);
        // $sql = "UPDATE `projects` SET `duration`=1 WHERE `duration`=0";
        // DB::connection()
        //     ->getPdo()
        //     ->exec($sql);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
