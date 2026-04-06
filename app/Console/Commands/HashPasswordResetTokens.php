<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HashPasswordResetTokens extends Command
{
    protected $signature = 'passwordresets:hash-existing {--dry : Do not write changes, just show count}';

    protected $description = 'Hash existing plain password reset tokens in the configured table';

    public function handle()
    {
        $table = env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens');

        if (! \Schema::hasTable($table)) {
            $this->warn("Table {$table} does not exist.");

            return 1;
        }

        $rows = DB::table($table)->get();
        $toHash = [];

        foreach ($rows as $row) {
            $token = $row->token;

            // Detect common hashed formats (bcrypt/argon) by prefix
            if (is_string($token) && (str_starts_with($token, '$2y$') || str_starts_with($token, '$2a$') || str_starts_with($token, '$argon') || str_starts_with($token, '$argon2'))) {
                continue; // already hashed
            }

            // If token contains non-alphanumeric chars we still re-hash; otherwise assume plain and re-hash
            $toHash[] = $row;
        }

        $count = count($toHash);
        $this->info("Found {$count} token(s) that look like plain tokens.");

        if ($this->option('dry')) {
            $this->line('Dry run: no changes will be made.');

            return 0;
        }

        $converted = 0;

        foreach ($toHash as $r) {
            try {
                $new = Hash::make($r->token);
                DB::table($table)->where('email', $r->email)->where('created_at', $r->created_at)->update(['token' => $new]);
                $converted++;
            } catch (\Throwable $e) {
                $this->error("Failed to hash token for email {$r->email}: {$e->getMessage()}");
            }
        }

        $this->info("Converted {$converted} token(s) to hashed values.");

        return 0;
    }
}
