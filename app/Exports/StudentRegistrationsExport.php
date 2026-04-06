<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentRegistrationsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $registrations;

    public function __construct($registrations)
    {
        $this->registrations = $registrations;
    }

    public function collection()
    {
        return $this->registrations;
    }

    public function headings(): array
    {
        return [
            'ID',
            'الاسم',
            'اسم المستخدم',
            'البريد الإلكتروني',
            'رقم الهاتف',
            'الكورسات المختارة',
            'تاريخ التسجيل',
            'الحالة',
        ];
    }

    public function map($user): array
    {
        $courses = $user->student && $user->student->courseSelections 
            ? $user->student->courseSelections->map(function($selection) {
                return $selection->course ? $selection->course->course_name : 'غير معروف';
            })->implode(', ')
            : 'لا يوجد';

        return [
            $user->id,
            $user->profile->nickname ?? $user->name,
            $user->username,
            $user->email,
            $user->profile->phone_number ?? 'لا يوجد',
            $courses,
            $user->created_at ? $user->created_at->format('Y-m-d H:i') : '',
            $user->is_active ? 'نشط' : 'قيد الانتظار',
        ];
    }
}
