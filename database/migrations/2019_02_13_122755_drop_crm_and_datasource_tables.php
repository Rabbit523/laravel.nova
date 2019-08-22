<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropCrmAndDatasourceTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('project_contact');
        Schema::dropIfExists('mailchimp_list_contact');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('datasources');

        DB::transaction(function () {
            DB::table('taggable_taggables')->delete();
            $sql =
                "DELETE FROM `subscriptions` WHERE `user_id` IN (SELECT `c`.`id` FROM `customers` `c` WHERE `c`.`id`=`subscriptions`.`user_id`)";
            DB::connection()
                ->getPdo()
                ->exec($sql);
            $sql =
                "DELETE FROM `subscriptions` WHERE `user_id` IN (SELECT `c`.`id` FROM `contacts` `c` WHERE `c`.`id`=`subscriptions`.`user_id`)";
            DB::connection()
                ->getPdo()
                ->exec($sql);
        });

        Schema::dropIfExists('customers');
        Schema::dropIfExists('contacts');

        Schema::dropIfExists('costs_monthly');
        Schema::dropIfExists('costs_daily');
        Schema::dropIfExists('costs');
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
