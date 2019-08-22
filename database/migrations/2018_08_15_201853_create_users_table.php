<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('name')->nullable();
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->char('language', 2)->default('ja');
            $table->smallInteger('acl')->default(0);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            $table->primary('id');

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
