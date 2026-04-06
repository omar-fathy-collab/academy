<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Invoice::with(['student', 'group'])->get();
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Student',
            'Group',
            'Description',
            'Amount',
            'Discount',
            'Amount Paid',
            'Balance Due',
            'Due Date',
            'Status'
        ];
    }

    public function map($invoice): array
    {
        $discountAmount = 0;
        if ($invoice->discount_amount > 0) {
            $discountAmount = $invoice->discount_amount;
        } elseif ($invoice->discount_percent > 0) {
            $discountAmount = $invoice->amount * ($invoice->discount_percent / 100);
        }

        $finalAmount = $invoice->amount - $discountAmount;
        $balanceDue = max(0, $finalAmount - $invoice->amount_paid);

        return [
            $invoice->invoice_number,
            $invoice->student->student_name ?? 'N/A',
            $invoice->group->group_name ?? 'N/A',
            $invoice->description,
            $invoice->amount,
            $discountAmount,
            $invoice->amount_paid,
            $balanceDue,
            $invoice->due_date,
            $invoice->status,
        ];
    }
}
