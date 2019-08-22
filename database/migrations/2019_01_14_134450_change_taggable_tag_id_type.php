<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTaggableTagIdType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sql =
            "ALTER TABLE `taggable_taggables` CHANGE `taggable_id` `taggable_id` CHAR(36)  CHARACTER SET utf8mb4  COLLATE utf8mb4_unicode_ci  NOT NULL  DEFAULT ''";
        DB::connection()
            ->getPdo()
            ->exec($sql);
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
