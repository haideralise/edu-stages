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
        Schema::create('edu_admin_log', function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->integer('admin_user_id')->default(0);
            $table->integer('created')->default(0);
            $table->integer('edu_result_id')->default(0);
            $table->string('handle', 100)->default('');
            $table->text('before')->nullable();
            $table->text('after')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_admin_log');
    }
};
