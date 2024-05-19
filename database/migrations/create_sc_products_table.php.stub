<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $productsTable = strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.products_table_name"));

        Schema::create($productsTable, function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger("user_id")->nullable(); // creator
            $table->string('name');
            $table->string('product_type'); // product type ["Simple", "Variable"]
            $table->string('slug');
            $table->decimal('price', 8, 2)->nullable(); // price can be null for variable products
            $table->string('sku')->nullable();
            $table->decimal('stock_quantity', 8, 2)->nullable();
            $table->boolean('manage_product_stock')->default(0);
            $table->string('should_allow_backorders')->nullable();
            $table->integer('low_stock_threshold')->nullable();
            $table->text('description')->nullable();
            $table->decimal('weight')->nullable();
            $table->decimal('height')->nullable();
            $table->decimal('width')->nullable();
            $table->decimal('length')->nullable();
            $table->json('product_attributes')->nullable();
            $table->text('purchase_note')->nullable();
            $table->timestamps();
        });

        Schema::create(strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.product_variants_table_name")), function (Blueprint $table) use ($productsTable) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku')->nullable();
            $table->boolean('enabled')->default(1);
            $table->boolean('downloadable')->default(0);
            $table->boolean('virtual')->default(0);
            $table->string('variation_title')->nullable();
            $table->decimal('regular_price', 8, 2);
            $table->decimal('sale_price', 8, 2);
            $table->text('description')->nullable();
            $table->string('weight')->nullable();
            $table->string('height')->nullable();
            $table->string('width')->nullable();
            $table->string('length')->nullable();
            $table->timestamps();
        });
    }

    public function down(){

        Schema::dropIfExists(strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.product_variants_table_name")));
        Schema::dropIfExists(strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.products_table_name")));
    }
};
