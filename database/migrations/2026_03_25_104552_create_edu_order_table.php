<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('edu_order', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('class_id')->nullable()->index();
            $table->string('month', 255)->default('');
            $table->string('class_year', 16)->default('');
            $table->float('amount', 10, 2)->default(0);
            $table->string('last_days', 255)->default('');
            $table->string('gateway', 255)->default('');
            $table->string('avgfee', 255)->default('');
            $table->integer('order_date')->default(0);
            $table->integer('created')->default(0);
            $table->float('refund_fee', 10, 2)->default(0);
            $table->string('refund_reason', 255)->default('');
            $table->string('refund_date', 255)->default('');
            $table->integer('user_id')->default(0)->index();
            $table->string('type', 10)->default('');
            $table->string('woo_status', 100)->default('');
            $table->string('woo_class_name', 255)->default('');
            $table->bigInteger('woo_order_id')->default(0);
            $table->string('order_source', 20)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_order');
    }
};
