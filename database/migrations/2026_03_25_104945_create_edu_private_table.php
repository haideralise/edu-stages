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
        Schema::create('edu_private', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coach_id')->default(0)->index();
            $table->unsignedBigInteger('enrollment_id')->default(0);
            $table->string('student_name', 100)->default('');
            $table->string('student_phone', 50)->default('');
            $table->string('district', 50)->default('');
            $table->string('pool', 100)->default('');
            $table->string('other_location', 200)->default('');
            $table->date('class_date')->nullable();
            $table->string('class_time', 10)->default('');
            $table->string('class_end_time', 10)->default('');
            $table->string('ratio', 10)->default('');
            $table->string('type', 50)->default('');
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('status', 20)->default('Pending');
            $table->date('payment_date')->nullable();
            $table->date('refund_date')->nullable();
            $table->string('attendance', 20)->default('');
            $table->integer('cumulative_override')->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edu_private');
    }
};
