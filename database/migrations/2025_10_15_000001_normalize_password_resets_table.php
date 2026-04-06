<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $current = 'password_resets';
        $expected = env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens');

        // If the existing table has the old name, rename it to the expected name.
        if (Schema::hasTable($current) && ! Schema::hasTable($expected)) {
            Schema::rename($current, $expected);
        }

        // Ensure the expected table exists; create if missing with the standard schema.
        if (! Schema::hasTable($expected)) {
            Schema::create($expected, function (Blueprint $table) {
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Invalidate any existing tokens by truncating the table.
        // We intentionally do this to avoid keeping plain tokens; a backup should be used if needed.
        DB::table($expected)->truncate();
    }

    public function down()
    {
        $current = env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens');
        $legacy = 'password_resets';

        if (Schema::hasTable($current) && ! Schema::hasTable($legacy)) {
            Schema::rename($current, $legacy);
        }
    }
};
