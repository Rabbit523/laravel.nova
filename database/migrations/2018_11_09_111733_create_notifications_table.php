<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by')->nullable(); // sender: system or admin?
            $table->uuid('user_id');
            $table->string('type'); // e.g. TeamJoined
            $table->text('body'); // content
            $table->string('channel')->default('database');
            $table->timestamp('read_at')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('action_text')->nullable();
            $table->text('action_url')->nullable();
            $table->boolean('failed')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
