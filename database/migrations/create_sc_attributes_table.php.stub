<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.product_attributes_table_name")), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->json('values');
            $table->timestamps();
        });
    }

    public function down(){
        Schema::dropIfExists(strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.product_attributes_table_name")));
    }
};
