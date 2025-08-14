<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Add index on categories for faster category filtering
        DB::statement('ALTER TABLE products ADD INDEX idx_categories (categories(100))');
        
        // Add index on storeName for faster store filtering
        DB::statement('ALTER TABLE products ADD INDEX idx_store_name (storeName)');
        
        // Add index on brand for faster brand filtering
        DB::statement('ALTER TABLE products ADD INDEX idx_brand (brand)');
        
        // Add composite index for category + store filtering
        DB::statement('ALTER TABLE products ADD INDEX idx_category_store (categories(50), storeName)');
        
        // Add index on created_at for sorting
        DB::statement('ALTER TABLE products ADD INDEX idx_created_at (created_at)');
        
        // Add index on regular_price for price filtering
        DB::statement('ALTER TABLE products ADD INDEX idx_regular_price (regular_price)');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $sm->listTableIndexes('products');

        if (array_key_exists('idx_categories', $indexes)) {
            DB::statement('ALTER TABLE products DROP INDEX idx_categories');
        }
        if (array_key_exists('idx_store_name', $indexes)) {
            DB::statement('ALTER TABLE products DROP INDEX idx_store_name');
        }
        if (array_key_exists('idx_brand', $indexes)) {
            DB::statement('ALTER TABLE products DROP INDEX idx_brand');
        }
        if (array_key_exists('idx_category_store', $indexes)) {
            DB::statement('ALTER TABLE products DROP INDEX idx_category_store');
        }
        if (array_key_exists('idx_created_at', $indexes)) {
            DB::statement('ALTER TABLE products DROP INDEX idx_created_at');
        }
        if (array_key_exists('idx_regular_price', $indexes)) {
            DB::statement('ALTER TABLE products DROP INDEX idx_regular_price');
        }
    }
}; 