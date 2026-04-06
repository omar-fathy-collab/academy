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
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'is_approved')) {
                $table->boolean('is_approved')->default(0)->after('amount');
            }
            if (!Schema::hasColumn('expenses', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('is_approved');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['is_approved', 'payment_method']);
        });
    }
};
