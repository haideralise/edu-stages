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
        Schema::create('edu_class_user_days', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('class_id')->default(0)->index();
            $table->string('month', 255)->default('');
            $table->integer('user_id')->default(0)->index();
            $table->string('role', 32)->default('');
            $table->text('days')->nullable();
            $table->string('class_year', 32)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_class_user_days');
    }
};
