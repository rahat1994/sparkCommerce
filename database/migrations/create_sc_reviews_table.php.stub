<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $reviewsTable = strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.product_reviews_table_name"));

        Schema::create($reviewsTable, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('title');
            $table->text('content');
            $table->decimal('rating')->default(5);
            $table->timestamps();
        });
    }

    public function down(){
        $reviewsTable = strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.product_reviews_table_name"));
        Schema::dropIfExists($reviewsTable);
    }
};
