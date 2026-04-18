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
        Schema::create('edu_user', function (Blueprint $table) {
            $table->integer('user_id')->primary();
            $table->text('note')->nullable();
            $table->float('hourly_wage', 10, 2)->default(0);
            $table->float('class_fee', 10, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_user');
    }
};
