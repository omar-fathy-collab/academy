<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InvalidatePasswordResets extends Command
{
    protected $signature = 'passwordresets:invalidate {--dry : Show only, do not delete}';

    protected $description = 'Show or invalidate (truncate) password reset tokens table';

    public function handle()
    {
        $table = env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens');

        if (! \Schema::hasTable($table)) {
            $this->warn("Table {$table} does not exist.");

            return 1;
        }

        $count = DB::table($table)->count();
        $this->info("Found {$count} password reset record(s) in {$table}.");

        if ($this->option('dry')) {
            $this->line('Dry run: no changes made.');

            return 0;
        }

        if ($count > 0) {
            DB::table($table)->truncate();
            $this->info('Password reset tokens invalidated (table truncated).');
        } else {
            $this->line('No records to truncate.');
        }

        return 0;
    }
}
