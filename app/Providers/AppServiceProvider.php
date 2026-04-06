<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Observers\ActivityObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Force HTTPS in production unless on localhost or 127.0.0.1
        $host = app()->bound('request') ? request()->getHost() : 'localhost';
        $isLocalHost = in_array($host, ['127.0.0.1', 'localhost', '::1']);

        // Force HTTPS in production or when using ngrok tunnels
        if ((config('app.env') === 'production' && ! $isLocalHost) || str_contains($host, 'ngrok-free.app')) {
            URL::forceScheme('https');
        }

        // Define global impersonate Gate
        Gate::define('impersonate', function (\App\Models\User $user) {
            return $user->isAdminFull();
        });

        // ✅ Register Auth Event Listeners for Activity Log
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);

        // ✅ Register Financial Observers
        \App\Models\Group::observe(\App\Observers\GroupObserver::class);

        // ✅ Automatically register ActivityObserver for all models in app/Models
        $modelsPath = app_path('Models');

        if (File::exists($modelsPath)) {
            $files = File::allFiles($modelsPath);

            foreach ($files as $file) {
                $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $fullClass = "App\\Models\\{$modelName}";

                // ✅ Skip deprecated or unwanted models
                $skip = ['Activity', 'AdminPermission'];
                if (in_array($modelName, $skip)) {
                    continue;
                }

                if (class_exists($fullClass) && is_subclass_of($fullClass, \Illuminate\Database\Eloquent\Model::class)) {
                    $fullClass::observe(\App\Observers\ActivityObserver::class);
                }
            }

        }
 
        // ✅ Register View Composer for Global Settings
        \Illuminate\Support\Facades\View::composer('*', \App\Http\View\Composers\GlobalSettingsComposer::class);
    }
}
