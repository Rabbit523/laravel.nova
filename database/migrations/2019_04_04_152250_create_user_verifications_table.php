<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserVerificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_verifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->unique();

            $table->json('currently_due')->nullable();
            $table->json('past_due')->nullable();
            $table->json('eventually_due')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->unsignedInteger('current_deadline')->nullable();

            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);

            $table->string('verification_details_code')->nullable();
            $table->string('verification_status')->default('unverified');

            $table->unsignedBigInteger('last_webhook_id')->nullable();

            $table->timestamps();

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_verifications');
    }
}
