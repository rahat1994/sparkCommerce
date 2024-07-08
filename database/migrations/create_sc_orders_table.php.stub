<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $ordersTableName = strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.orders_table_name"));

        Schema::create($ordersTableName, function (Blueprint $table) {
            $table->id();
            $table->json("items")->nullable();
            $table->json("shipping_address")->nullable();
            $table->json("billing_address")->nullable();
            $table->json("shipping_method")->nullable();
            $table->decimal("total_amount")->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json("discount")->nullable();
            $table->unsignedInteger("user_id")->nullable(); // creator
            $table->string("order_number")->nullable();
            $table->string("status")->nullable();
            $table->string("payment_status")->nullable();
            $table->string("shipping_status")->nullable();
            $table->string("payment_method")->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {

        Schema::dropIfExists(strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.orders_table_name")));
    }
};
