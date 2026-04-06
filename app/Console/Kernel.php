<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\EncryptTokens::class,
        \App\Console\Commands\InvalidatePasswordResets::class,
        \App\Console\Commands\HashPasswordResetTokens::class,
        \App\Console\Commands\RotateRememberTokens::class,
        \App\Console\Commands\PruneExpiredPasswordResets::class,
        \App\Console\Commands\GenerateGroupCertificates::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Prune expired password reset tokens daily
        $schedule->command('passwordresets:prune-expired')->daily();

        // Optional: rotate remember tokens daily when ENABLE_TOKEN_ROTATION env is truthy
        if (env('ENABLE_TOKEN_ROTATION', false)) {
            $schedule->command('tokens:rotate-remember')->daily();
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
