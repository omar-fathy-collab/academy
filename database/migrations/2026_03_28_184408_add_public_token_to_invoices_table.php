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
            $table->string('public_token', 64)->nullable()->unique()->after('status');
        });

        // Generate tokens for existing invoices
        DB::table('invoices')->whereNull('public_token')->get()->each(function ($invoice) {
            DB::table('invoices')
                ->where('invoice_id', $invoice->invoice_id)
                ->update(['public_token' => \Illuminate\Support\Str::random(40)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
