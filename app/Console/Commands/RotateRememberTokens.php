<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RotateRememberTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:rotate-remember {--dry : Do not persist changes, just show what would change}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate remember_token values for all users. Use --dry first to preview.';

    public function handle()
    {
        $dry = $this->option('dry');

        $this->info('Scanning users and rotating remember_token values...');

        // Use a cursor to avoid loading all users into memory
        $users = User::query()->cursor();
        $count = 0;
        $rotated = 0;

        foreach ($users as $user) {
            $count++;
            $old = $user->getOriginal('remember_token');
            $new = Str::random(60);

            if ($dry) {
                $this->line("User {$user->id}: would rotate remember_token (present=".($old ? 'yes' : 'no').')');
                $rotated++;

                continue;
            }

            $user->remember_token = $new;
            $user->save();
            $rotated++;
        }

        $this->info("Processed {$count} users, rotated {$rotated} remember_token(s).");

        return 0;
    }
}
