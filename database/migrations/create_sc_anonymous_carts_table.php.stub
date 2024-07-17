<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $anonymousCartsTableName = strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.anonymous_carts_table_name"));

        Schema::create($anonymousCartsTableName, function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();
            $table->json('cart_content');
            $table->timestamps();
        });
    }

    public function down(){
        $anonymousCartsTableName = strval(config("sparkcommerce.table_prefix")).strval(config("sparkcommerce.anonymous_carts_table_name"));
        Schema::dropIfExists($anonymousCartsTableName);
    }
};
