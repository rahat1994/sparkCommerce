<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $couponUserTable = strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.coupon_user_table_name"));

        Schema::create($couponUserTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('coupon_id');
            $table->integer('usage_count')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        $couponUserTable = strval(config("sparkcommerce.table_prefix")) . strval(config("sparkcommerce.coupon_user_table_name"));
        Schema::dropIfExists($couponUserTable);
    }
};
