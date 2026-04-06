<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('groups') && !Schema::hasColumn('groups', 'type')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->string('type')->nullable()->after('group_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('groups') && Schema::hasColumn('groups', 'type')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
