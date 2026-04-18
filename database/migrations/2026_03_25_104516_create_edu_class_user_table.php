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
        Schema::create('edu_class_user', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('class_id')->default(0)->index();
            $table->string('month', 255)->default('');
            $table->text('student')->nullable();
            $table->text('student_makeup')->nullable();
            $table->text('student_transfer')->nullable();
            $table->text('student_order')->nullable();
            $table->text('order_id')->nullable();
            $table->text('teacher')->nullable();
            $table->text('days')->nullable();
            $table->string('class_year', 32)->default('');
            $table->text('class_exam')->nullable();
            $table->integer('sort')->default(0);
            $table->integer('history_students_status')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_class_user');
    }
};
