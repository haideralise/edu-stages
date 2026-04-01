<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tables matching the existing WordPress/edu2 schema.
 * The wp_3x_ prefix is applied automatically via config/database.php.
 * Uses Schema::hasTable() guards so this can run safely against
 * a production DB that already has these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // WordPress core tables (minimal — just what we need for auth)

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->bigIncrements('ID');
                $table->string('user_login', 60)->default('')->index();
                $table->string('user_pass', 255)->default('');
                $table->string('user_nicename', 50)->default('');
                $table->string('user_email', 100)->default('')->index();
                $table->string('user_url', 100)->default('');
                $table->dateTime('user_registered')->useCurrent();
                $table->string('user_activation_key', 255)->default('');
                $table->integer('user_status')->default(0);
                $table->string('display_name', 250)->default('');
            });
        }

        if (! Schema::hasTable('usermeta')) {
            Schema::create('usermeta', function (Blueprint $table) {
                $table->bigIncrements('umeta_id');
                $table->unsignedBigInteger('user_id')->default(0)->index();
                $table->string('meta_key', 255)->nullable()->index();
                $table->longText('meta_value')->nullable();
            });
        }

        // ── edu tables (doc 07 schema) ────────────────────────────

        if (! Schema::hasTable('edu_class')) {
            Schema::create('edu_class', function (Blueprint $table) {
                $table->integerIncrements('class_id');
                $table->text('class_name')->nullable();
                $table->integer('district_id')->default(0)->index();
                $table->integer('product_id')->default(0);
                $table->string('product_name', 255)->default('');
                $table->string('date_time', 255)->default('');
                $table->string('date_month', 255)->default('');   // JSON
                $table->text('class_date')->nullable();            // JSON
                $table->text('class_exam')->nullable();            // JSON
                $table->string('lv3', 255)->default('');
                $table->string('class_year', 32)->default('')->index();
            });
        }

        if (! Schema::hasTable('edu_class_user')) {
            Schema::create('edu_class_user', function (Blueprint $table) {
                $table->integerIncrements('id');
                $table->integer('class_id')->default(0)->index();
                $table->string('month', 255)->default('');
                $table->text('student')->nullable();           // JSON
                $table->text('student_makeup')->nullable();     // JSON
                $table->text('student_transfer')->nullable();   // JSON
                $table->text('student_order')->nullable();       // JSON
                $table->text('order_id')->nullable();            // JSON
                $table->text('teacher')->nullable();             // JSON
                $table->text('days')->nullable();
                $table->string('class_year', 32)->default('');
                $table->text('class_exam')->nullable();          // JSON
                $table->integer('sort')->default(0);
                $table->integer('history_students_status')->default(0);
            });
        }

        if (! Schema::hasTable('edu_user')) {
            Schema::create('edu_user', function (Blueprint $table) {
                $table->integer('user_id')->primary();
                $table->text('note')->nullable();
                $table->float('hourly_wage', 10, 2)->default(0);
                $table->float('class_fee', 10, 2)->default(0);
            });
        }

        if (! Schema::hasTable('edu_order')) {
            Schema::create('edu_order', function (Blueprint $table) {
                $table->integerIncrements('id');
                $table->integer('class_id')->nullable()->index();
                $table->string('month', 255)->default('');
                $table->string('class_year', 16)->default('');
                $table->float('amount', 10, 2)->default(0);
                $table->string('last_days', 255)->default('');
                $table->string('gateway', 255)->default('');
                $table->string('avgfee', 255)->default('');
                $table->integer('order_date')->default(0);
                $table->integer('created')->default(0);
                $table->float('refund_fee', 10, 2)->default(0);
                $table->string('refund_reason', 255)->default('');
                $table->string('refund_date', 255)->default('');
                $table->integer('user_id')->default(0)->index();
                $table->string('type', 10)->default('');
                $table->string('woo_status', 100)->default('');
                $table->string('woo_class_name', 255)->default('');
                $table->bigInteger('woo_order_id')->default(0);
                $table->string('order_source', 20)->default('');
            });
        }

        if (! Schema::hasTable('edu_bmi')) {
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

        if (! Schema::hasTable('edu_attendance')) {
            Schema::create('edu_attendance', function (Blueprint $table) {
                $table->integerIncrements('id');
                $table->integer('class_id')->default(0)->index();
                $table->string('month', 255)->default('');
                $table->integer('user_id')->default(0)->index();
                $table->string('date', 12)->default('');
                $table->string('attendance', 255)->default('');  // present / leave / cancelled
                $table->string('class_year', 32)->default('');
            });
        }

        if (! Schema::hasTable('edu_admin_log')) {
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

        if (! Schema::hasTable('edu_result')) {
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

        if (! Schema::hasTable('edu_class_user_days')) {
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

        if (! Schema::hasTable('edu_level')) {
            Schema::create('edu_level', function (Blueprint $table) {
                $table->integerIncrements('id');
                $table->integer('pid')->default(0);
                $table->string('name', 255)->default('');
                $table->text('data')->nullable();
                $table->string('file_level', 255)->default('');
                $table->string('link', 255)->default('');
            });
        }

        if (! Schema::hasTable('edu_order_status')) {
            Schema::create('edu_order_status', function (Blueprint $table) {
                $table->string('order_id', 100)->primary();
                $table->integer('user_id')->default(0);
                $table->string('type', 25)->default('');
                $table->integer('status')->default(0);
            });
        }

        if (! Schema::hasTable('edu_private')) {
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
    }

    public function down(): void
    {
        $tables = [
            'edu_private',
            'edu_order_status',
            'edu_level',
            'edu_class_user_days',
            'edu_result',
            'edu_admin_log',
            'edu_attendance',
            'edu_bmi',
            'edu_order',
            'edu_user',
            'edu_class_user',
            'edu_class',
            'usermeta',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
