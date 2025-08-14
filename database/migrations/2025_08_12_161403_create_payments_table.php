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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('payment_method', ['card', 'crypto'])->default('card');
            $table->decimal('amount', 10, 2);
            $table->string('transaction_id')->nullable();
            $table->enum('payment_status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');   
            $table->string('crypto_currency')->nullable(); 
            $table->string('wallet_address')->nullable(); 
            $table->string('transaction_hash')->nullable(); 
            $table->decimal('crypto_amount', 20, 8)->nullable(); 
            $table->decimal('exchange_rate', 15, 8)->nullable(); 
            $table->integer('confirmations')->default(0); 
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
