<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');

            $table->string('name');
            $table->string('name_katakana')->nullable();
            $table->string('email');
            $table->string('password')->nullable();
            $table->rememberToken();

            $table->string('stripe_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->json('meta');
            $table->date('birthday')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('subscriber');
            $table->string('language')->default('ja');
            $table->string('source')->default('api');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('company_id')->nullable();
            $table->boolean('is_company')->default(false);
            $table->boolean('is_vendor')->default(false);
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table
                ->foreign('client_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::create('project_customer', function (Blueprint $table) {
            $table->uuid('project_id')->index();
            $table
                ->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
            $table->uuid('customer_id')->index();
            $table
                ->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
            $table->primary(['customer_id', 'project_id']);
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
