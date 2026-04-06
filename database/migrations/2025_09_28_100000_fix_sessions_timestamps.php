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
        if (! Schema::hasColumn('sessions', 'created_at')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('sessions', 'updated_at')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sessions', 'created_at')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropColumn('created_at');
            });
        }

        if (Schema::hasColumn('sessions', 'updated_at')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
