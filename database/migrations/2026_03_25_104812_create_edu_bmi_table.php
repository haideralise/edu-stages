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
        Schema::create('edu_bmi', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('user_id')->default(0)->index();
            $table->float('height', 10, 2)->default(0);
            $table->float('weight', 10, 2)->default(0);
            $table->float('hc', 10, 2)->default(0);
            $table->float('bmi', 10, 2)->default(0);
            $table->integer('date')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_bmi');
    }
};
