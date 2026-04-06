<?php

namespace App\Exports;

use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExpensesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Expense::with('recorder')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Category',
            'Payment Method',
            'Amount',
            'Description',
            'Expense Date',
            'Recorded By',
            'Status'
        ];
    }

    public function map($expense): array
    {
        return [
            $expense->id,
            $expense->category,
            $expense->payment_method ?: 'N/A',
            $expense->amount,
            $expense->description,
            $expense->expense_date,
            $expense->recorder->username ?? 'N/A',
            $expense->is_approved ? 'Approved' : 'Pending',
        ];
    }
}
