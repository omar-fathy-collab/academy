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
        Schema::table('salary_transfers', function (Blueprint $table) {
            // Rename columns if they exist with old names
            if (Schema::hasColumn('salary_transfers', 'id')) {
                $table->renameColumn('id', 'transfer_id');
            }
            if (Schema::hasColumn('salary_transfers', 'amount')) {
                $table->renameColumn('amount', 'transfer_amount');
            }
            if (Schema::hasColumn('salary_transfers', 'reason')) {
                $table->renameColumn('reason', 'notes');
            }
            if (Schema::hasColumn('salary_transfers', 'created_by')) {
                $table->renameColumn('created_by', 'transferred_by');
            }

            // Add missing columns
            if (!Schema::hasColumn('salary_transfers', 'source_salary_id')) {
                $table->unsignedBigInteger('source_salary_id')->nullable()->after('transfer_id');
                // Optional: add foreign key
                // $table->foreign('source_salary_id')->references('salary_id')->on('salaries')->onDelete('set null');
            }
            if (!Schema::hasColumn('salary_transfers', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0)->after('transfer_amount');
            }
            if (!Schema::hasColumn('salary_transfers', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending')->after('paid_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('salary_transfers', 'transfer_id')) {
                $table->renameColumn('transfer_id', 'id');
            }
            if (Schema::hasColumn('salary_transfers', 'transfer_amount')) {
                $table->renameColumn('transfer_amount', 'amount');
            }
            if (Schema::hasColumn('salary_transfers', 'notes')) {
                $table->renameColumn('notes', 'reason');
            }
            if (Schema::hasColumn('salary_transfers', 'transferred_by')) {
                $table->renameColumn('transferred_by', 'created_by');
            }

            $table->dropColumn(['source_salary_id', 'paid_amount', 'payment_status']);
        });
    }
};
