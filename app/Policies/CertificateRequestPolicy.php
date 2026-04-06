<?php

namespace App\Policies;

use App\Models\CertificateRequest;
use App\Models\User;

class CertificateRequestPolicy
{
    /**
     * Determine whether the user can approve the certificate request.
     */
    public function approve(User $user, CertificateRequest $request)
    {
        // Admins (role_id == 1) can always approve
        if ($user->role_id == 1) {
            return true;
        }

        // Instructors (role_id == 2) can approve requests for groups they teach
        if ($user->role_id == 2) {
            $teacher = $user->teacher ?? null;
            if (! $teacher) {
                return false;
            }
            $group = $request->group;
            if (! $group) {
                return false;
            }

            return $group->teacher_id == $teacher->teacher_id;
        }

        return false;
    }
}
