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
        Schema::create('edu_level', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('pid')->default(0);
            $table->string('name', 255)->default('');
            $table->text('data')->nullable();
            $table->string('file_level', 255)->default('');
            $table->string('link', 255)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_level');
    }
};
