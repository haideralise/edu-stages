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
        Schema::create('edu_attendance', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('class_id')->default(0)->index();
            $table->string('month', 255)->default('');
            $table->integer('user_id')->default(0)->index();
            $table->string('date', 12)->default('');
            $table->string('attendance', 255)->default('');
            $table->string('class_year', 32)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_attendance');
    }
};
