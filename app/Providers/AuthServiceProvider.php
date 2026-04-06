<?php

namespace App\Providers;

use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\Group;
use App\Models\Invoice;
use App\Policies\CertificateRequestPolicy;
use App\Policies\CoursePolicy;
use App\Policies\GroupPolicy;
use App\Policies\InvoicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Register model policies
        Gate::policy(CertificateRequest::class, CertificateRequestPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        // Global ability to impersonate
        Gate::define('impersonate', function (\App\Models\User $user) {
            return $user->isAdminFull();
        });
    }
}
