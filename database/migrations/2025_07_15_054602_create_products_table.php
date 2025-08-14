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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('productId')->nullable();
            $table->string('name')->nullable()->index();
            $table->longText('images')->nullable();
            $table->string('regular_price')->nullable();
            $table->string('promo_price')->nullable();
            $table->string('brand')->nullable();
            $table->string('categories')->nullable();
            $table->string('term')->nullable();
            $table->string('size')->nullable();
            $table->string('soldBy')->nullable();
            $table->string('locationId')->nullable();
            $table->string('storeName')->nullable();
            $table->string('stockLevel')->nullable();
            $table->string('countryOrigin')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
