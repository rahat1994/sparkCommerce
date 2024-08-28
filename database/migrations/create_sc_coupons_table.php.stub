<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $couponsTable = strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.coupons_table_name"));

        Schema::create($couponsTable, function (Blueprint $table) {
            $table->id();
            $table->string('coupon_code');
            $table->string('coupon_type');
            $table->string('coupon_amount');
            $table->date('end_date');
            $table->date('start_date');
            $table->boolean('number_of_uses')->comment('0: unlimited, 1: one time, If cart is eligible or conditions are met, coupon applies once. ie: If you set the coupon to offer Buy 2 Get 1, you get one free product. Moving more items to the cart will not make it eligible to get more free products.');
            $table->string('max_spend');
            $table->string('min_spend');
            $table->boolean('individual_use')->default(0);
            $table->boolean('exclude_sale_items')->default(0);
            $table->json('included_products');
            $table->json('excluded_products');
            $table->json('included_categories');
            $table->json('excluded_categories');
            $table->json('included_customers');
            $table->string('usage_limit')->comment('how many times the coupon can be used');
            $table->string('usage_limit_per_user')->comment('how many times the coupon can be used by a single user');
            $table->timestamps();
        });
    }

    public function down()
    {
        $couponsTable = strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.coupons_table_name"));
        Schema::dropIfExists($couponsTable);
    }
};
