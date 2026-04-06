<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('groups')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table) {
            // Make fields nullable if they are not already
            foreach (['description', 'location', 'room_id'] as $col) {
                if (Schema::hasColumn('groups', $col)) {
                    $table->string($col)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        // No destructive rollback needed
    }
};
