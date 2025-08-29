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
        // Create order_items table if it doesn't exist
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->string('product_name');
                $table->decimal('unit_price', 8, 2);
                $table->integer('quantity');
                $table->decimal('total_price', 8, 2);
                $table->text('product_notes')->nullable();
                $table->timestamps();
            });
        }

        // Create payments table if it doesn't exist
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->string('payment_method');
                $table->decimal('amount', 8, 2);
                $table->string('currency', 3)->default('USD');
                $table->string('payment_status')->default('pending');
                $table->string('transaction_id')->nullable();
                $table->timestamp('payment_date')->nullable();
                $table->string('crypto_currency', 10)->nullable();
                $table->string('wallet_address')->nullable();
                $table->decimal('crypto_amount', 18, 8)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('payments');
    }
};
