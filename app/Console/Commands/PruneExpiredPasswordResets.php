<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PruneExpiredPasswordResets extends Command
{
    protected $signature = 'passwordresets:prune-expired {--dry : Do not persist changes, just report count}';

    protected $description = 'Delete expired password reset tokens based on config("auth.passwords.*.expire")';

    public function handle()
    {
        // Determine table and expiry
        $broker = Config::get('auth.defaults.passwords', 'users');
        $config = Config::get("auth.passwords.{$broker}", []);
        $table = $config['table'] ?? 'password_reset_tokens';
        $expireMinutes = $config['expire'] ?? 60;

        $threshold = Carbon::now()->subMinutes($expireMinutes);

        $count = DB::table($table)->where('created_at', '<', $threshold)->count();

        if ($count === 0) {
            $this->info('No expired password reset tokens found.');

            return 0;
        }

        $this->info("Found {$count} expired password reset token(s) older than {$expireMinutes} minutes.");

        if ($this->option('dry')) {
            $this->info('Dry run: no rows were deleted.');

            return 0;
        }

        DB::table($table)->where('created_at', '<', $threshold)->delete();

        $this->info("Deleted {$count} expired password reset token(s).");

        return 0;
    }
}
