<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->integer('telegram_id');
            $table->string('username');
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->boolean('completed')->default(0);
            $table->timestamps();

            $table->foreign('telegram_user_id')
                ->references('id')->on('telegram_users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_tables');
    }
};
