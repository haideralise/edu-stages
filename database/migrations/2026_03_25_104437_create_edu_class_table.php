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
        Schema::create('edu_class', function (Blueprint $table) {
            $table->integerIncrements('class_id');
            $table->text('class_name')->nullable();
            $table->integer('district_id')->default(0)->index();
            $table->integer('product_id')->default(0);
            $table->string('product_name', 255)->default('');
            $table->string('date_time', 255)->default('');
            $table->string('date_month', 255)->default('');
            $table->text('class_date')->nullable();
            $table->text('class_exam')->nullable();
            $table->string('lv3', 255)->default('');
            $table->string('class_year', 32)->default('')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_class');
    }
};
