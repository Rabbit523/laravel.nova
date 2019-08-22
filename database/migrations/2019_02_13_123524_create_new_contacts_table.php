<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNewContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->uuid('company_id')->nullable();

            $table->string('name')->nullable();
            $table->string('name_katakana')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('email')->nullable();

            $table->unsignedInteger('industry_id')->nullable();
            $table->unsignedInteger('size')->nullable();

            $table->json('meta')->nullable();

            $table->date('birthday')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('subscriber');
            $table->string('language')->default('ja');
            $table->string('source')->default('api');
            $table->boolean('is_company')->default(false);
            $table->boolean('is_vendor')->default(false);
            $table->boolean('accepts_marketing')->default(false);
            $table->text('notes')->nullable();

            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::create('mailchimp_list_contacts', function (Blueprint $table) {
            $table->uuid('mailchimp_list_id');
            $table->uuid('contact_id');
            $table
                ->foreign('mailchimp_list_id')
                ->references('id')
                ->on('mailchimp_lists')
                ->onDelete('cascade');

            $table
                ->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');
            $table->primary(['mailchimp_list_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mailchimp_list_contacts');
        Schema::dropIfExists('contacts');
    }
}
