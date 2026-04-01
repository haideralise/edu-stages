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
        Schema::create('usermeta', function (Blueprint $table) {
            $table->bigIncrements('umeta_id');
            $table->unsignedBigInteger('user_id')->default(0)->index();
            $table->string('meta_key', 255)->nullable()->index();
            $table->longText('meta_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usermeta');
    }
};
