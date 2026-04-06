<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GroupsFinancialExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        // Combine all relevant groups into one collection for export
        $allGroups = collect([]);
        
        if (isset($this->data['expired_with_unpaid'])) {
            $allGroups = $allGroups->concat($this->data['expired_with_unpaid']);
        }
        
        if (isset($this->data['about_to_expire'])) {
            $allGroups = $allGroups->concat($this->data['about_to_expire']);
        }

        return $allGroups;
    }

    public function headings(): array
    {
        return [
            'Group Name',
            'Course',
            'Instructor',
            'End Date',
            'Students',
            'Total Invoiced',
            'Total Paid',
            'Total Due',
        ];
    }

    public function map($group): array
    {
        return [
            $group['group_name'] ?? '',
            $group['course_name'] ?? '',
            $group['teacher_name'] ?? '',
            $group['end_date_formatted'] ?? '',
            $group['student_count'] ?? 0,
            $group['total_invoices'] ?? 0,
            $group['total_paid'] ?? 0,
            $group['total_due'] ?? 0,
        ];
    }
}
