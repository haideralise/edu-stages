<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a composite unique index on (user_id, class_id, month, class_year)
     * to prevent duplicate renewal orders created under concurrency. See
     * EduOrderService::orderAddForRenew() — the check-then-insert pattern
     * there is racy; this constraint is the backstop.
     *
     * NOTE: If the production database contains legacy duplicates (imported
     * from WordPress), this migration will fail until those rows are
     * reconciled. Run the following query first to check:
     *
     *   SELECT user_id, class_id, month, class_year, COUNT(*) c
     *   FROM edu_order
     *   GROUP BY user_id, class_id, month, class_year
     *   HAVING c > 1;
     */
    public function up(): void
    {
        Schema::table('edu_order', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'class_id', 'month', 'class_year'],
                'edu_order_user_class_month_year_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edu_order', function (Blueprint $table) {
            $table->dropUnique('edu_order_user_class_month_year_unique');
        });
    }
};
