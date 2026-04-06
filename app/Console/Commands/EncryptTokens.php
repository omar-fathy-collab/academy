<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class EncryptTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:encrypt {--dry : Do not write changes, just show what would change}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing remember_token values for users';

    public function handle()
    {
        $dry = $this->option('dry');

        $this->info('Scanning users for plain remember_token values...');

        $users = User::whereNotNull('remember_token')->get();

        $count = 0;

        foreach ($users as $user) {
            $token = $user->getOriginal('remember_token');

            // Skip tokens that are already encrypted (try to decrypt)
            try {
                Crypt::decryptString($token);

                // token decrypts -> already encrypted
                continue;
            } catch (\Throwable $e) {
                // not encrypted: proceed
            }

            $count++;

            $this->line("User {$user->id}: will encrypt token");

            if (! $dry) {
                $user->remember_token = $token;
                $user->save();
            }
        }

        $this->info("Processed {$users->count()} users, encrypted {$count} tokens.");

        return 0;
    }
}
