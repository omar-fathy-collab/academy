<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * InvoicePolicy
 *
 * Enforces access control on Invoice resources to prevent IDOR and
 * unauthorized financial manipulation.
 */
class InvoicePolicy
{
    use HandlesAuthorization;

    /**
     * Admins and secretaries can view any invoice.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSecretary();
    }

    /**
     * A student can only view their own invoice.
     * Admins and secretaries can view any.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin() || $user->isSecretary()) {
            return true;
        }

        // Student IDOR protection
        if ($user->isStudent() && $user->student) {
            return $invoice->student_id === $user->student->student_id;
        }

        return false;
    }

    /**
     * Only admins (full or partial with finance permission) can create invoices.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSecretary();
    }

    /**
     * Only full admins can update invoices.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdminFull();
    }

    /**
     * Only full admins can delete invoices. No hard deletes allowed — only
     * soft-delete with archival via FinancialService::deleteInvoice().
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isAdminFull();
    }

    /**
     * Only super-admins can restore soft-deleted invoices.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->isAdminFull();
    }

    /**
     * Hard deletes are NEVER allowed — audit trail must be preserved.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
