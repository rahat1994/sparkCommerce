<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.category_product_table_name")), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.category_product_table_name")));
    }
};
