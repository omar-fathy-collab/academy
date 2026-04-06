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
        if (!Schema::hasTable('certificates')) { return; }
        if (! Schema::hasColumn('certificates', 'certificate_type')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->enum('certificate_type', ['individual', 'group_completion'])->default('individual')->after('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('certificate_type');
        });
    }
};
