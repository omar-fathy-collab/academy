<?php

namespace App\Listeners;

use App\Models\Activity;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogout
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
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if ($user) {
            Activity::create([
                'user_id' => $user->getAuthIdentifier(),
                'subject_type' => get_class($user),
                'subject_id' => $user->getAuthIdentifier(),
                'action' => 'logout',
                'description' => 'User logged out of the system.',
                'ip' => Request::ip(),
                'user_agent' => Request::header('User-Agent'),
                'created_at' => now(),
            ]);
        }
    }
}
