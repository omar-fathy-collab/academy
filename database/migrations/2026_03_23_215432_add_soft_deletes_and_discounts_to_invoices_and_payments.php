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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('invoices', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('invoices', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'discount_percent', 'deleted_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
