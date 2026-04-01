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
        Schema::create('edu_result', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('class_id')->default(0)->index();
            $table->string('class_month', 255)->default('');
            $table->integer('exam_id')->default(0);
            $table->integer('user_id')->default(0)->index();
            $table->string('first_name', 255)->default('');
            $table->string('last_name', 255)->default('');
            $table->string('gender', 255)->default('');
            $table->string('birthdate', 255)->default('');
            $table->string('exam_type', 255)->default('');
            $table->string('exam_name', 255)->default('');
            $table->string('exam_data', 255)->default('');
            $table->json('exam_lap_times')->nullable();
            $table->decimal('exam_fastest_lap_sec', 8, 3)->default(0);
            $table->decimal('exam_slowest_lap_sec', 8, 3)->default(0);
            $table->decimal('exam_avg_lap_sec', 8, 3)->default(0);
            $table->string('exam_date', 255)->default('');
            $table->longText('exam_history')->nullable();
            $table->string('exam_note', 30)->default('');
            $table->integer('created')->default(0);
            $table->integer('status')->default(0);
            $table->string('class_year', 32)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_result');
    }
};
