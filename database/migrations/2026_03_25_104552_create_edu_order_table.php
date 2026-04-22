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
            $table->id();
            $table->unsignedInteger('class_id')->nullable();
            $table->string('month', 255);
            $table->string('class_year', 16);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('last_days', 255)->nullable();
            $table->string('gateway', 255)->nullable();
            $table->string('avgfee', 255)->nullable();
            $table->unsignedInteger('order_date')->nullable();
            $table->unsignedInteger('created')->nullable();
            $table->decimal('refund_fee', 10, 2)->nullable();
            $table->string('refund_reason', 255)->nullable();
            $table->string('refund_date', 255)->nullable();
            $table->unsignedInteger('user_id');
            $table->string('type', 10)->nullable();
            $table->string('woo_status', 100)->nullable();
            $table->string('woo_class_name', 255)->nullable();
            $table->unsignedBigInteger('woo_order_id')->nullable();
            $table->string('order_source', 20)->default('manual');

            $table->index('user_id');
            $table->index('class_id');
            $table->index('month');
            $table->index('class_year');
            $table->index('order_source');
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
