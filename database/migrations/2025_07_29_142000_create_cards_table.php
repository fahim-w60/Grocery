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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_holder_name')->nullable();
            $table->string('card_number')->nullable();
            $table->string('expiration_date')->nullable();
            $table->string('cvv')->nullable(); 
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Drop the foreign key from users table first
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'card_id')) {
                try {
                    $table->dropForeign(['card_id']);
                } catch (\Exception $e) {
                    // Ignore if already dropped
                }
            }
        });
        Schema::dropIfExists('cards');
    }
};
