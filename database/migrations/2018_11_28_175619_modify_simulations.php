<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifySimulations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared(
            "ALTER TABLE `simulations` CHANGE `market_size` `market_size` BIGINT(20)  NULL;
                ALTER TABLE `simulations` CHANGE `market_share` `market_share` BIGINT(20)  NULL;
                ALTER TABLE `simulations` CHANGE `apc` `apc` INT(10)  UNSIGNED  NULL  COMMENT 'average payment count';
                ALTER TABLE `simulations` CHANGE `mi` `mi` INT(10)  UNSIGNED  NULL  COMMENT 'market interactions';
                ALTER TABLE `simulations` CHANGE `conversion_rate` `conversion_rate` DOUBLE(4,2)  NULL  COMMENT 'conversion index';
                ALTER TABLE `simulations` CHANGE `avp` `avp` DECIMAL(10,2)  NULL;
                ALTER TABLE `simulations` CHANGE `cogs` `cogs` DECIMAL(4,2)  NULL  COMMENT 'cost of goods %';
                ALTER TABLE `simulations` CHANGE `cpmi` `cpmi` DECIMAL(10,2)  NULL  COMMENT 'cost per marketing interaction';"
        );
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
