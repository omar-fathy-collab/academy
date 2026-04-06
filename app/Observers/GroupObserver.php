<?php

namespace App\Observers;

use App\Models\Group;
use App\Models\Invoice;
use App\Models\Salary;

class GroupObserver
{
    /**
     * Handle the Group "updated" event.
     */
    public function updated(Group $group): void
    {
        // 1. Sync Price with Unpaid Invoices
        if ($group->wasChanged('price')) {
            $newPrice = $group->price;
            
            // Update invoices that are not fully paid yet
            Invoice::where('group_id', $group->group_id)
                ->where('status', '!=', 'paid')
                ->get()
                ->each(function ($invoice) use ($newPrice) {
                    $invoice->amount = $newPrice;
                    // Recalculate status based on new price and existing amount_paid
                    $invoice->status = ($invoice->balance_due <= 0) ? 'paid' : ($invoice->amount_paid > 0 ? 'partial' : 'pending');
                    $invoice->save();
                });
        }

        // 2. Sync All Financials (Salaries) using the standardized SalaryService
        // We trigger this if either price OR teacher_percentage changes to ensure total accuracy
        if ($group->wasChanged(['price', 'teacher_percentage'])) {
            $salaryService = app(\App\Services\SalaryService::class);
            $salaryService->syncAllSalariesForGroup($group);
        }
    }
}
