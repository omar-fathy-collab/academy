<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Student::with(['user.profile', 'groups'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Username',
            'Phone Number',
            'Enrollment Date',
            'Groups Count',
            'Status'
        ];
    }

    public function map($student): array
    {
        return [
            $student->student_id,
            $student->student_name,
            $student->user->email ?? 'N/A',
            $student->user->username ?? 'N/A',
            $student->user->profile->phone_number ?? 'N/A',
            $student->enrollment_date,
            $student->groups->count(),
            ($student->user->is_active ?? false) ? 'Active' : 'Inactive',
        ];
    }
}
