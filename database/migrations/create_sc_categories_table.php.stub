<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.categories_table_name")), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(){
        Schema::dropIfExists(strval(config("sparkcommerce.table_prefix")). strval(config("sparkcommerce.categories_table_name")));
    }
};
