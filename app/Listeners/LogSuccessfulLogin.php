<?php

namespace App\Listeners;

use App\Models\Activity;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        Activity::create([
            'user_id' => $user->getAuthIdentifier(),
            'subject_type' => get_class($user),
            'subject_id' => $user->getAuthIdentifier(),
            'action' => 'login',
            'description' => 'User logged in to the system.',
            'ip' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'created_at' => now(),
        ]);
    }
}
