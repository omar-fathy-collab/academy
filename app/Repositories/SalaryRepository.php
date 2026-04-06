<?php

namespace App\Repositories;

use App\Models\Salary;
use Illuminate\Support\Facades\DB;

class SalaryRepository
{
    public function getAllSalaries($filters = [])
    {
        $query = Salary::with(['teacher.user', 'group'])
            ->select('salaries.*');

        // Apply filters
        if (! empty($filters['start_date'])) {
            $query->whereDate('salaries.salary_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('salaries.salary_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('salaries.status', $filters['status']);
        }

        if (! empty($filters['teacher_id'])) {
            $query->where('salaries.teacher_id', $filters['teacher_id']);
        }

        return $query->orderBy('salaries.salary_date', 'desc')->get();
    }

    public function getSalariesByDateRange($startDate, $endDate)
    {
        return Salary::whereBetween('salary_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->with(['teacher.user', 'group'])
            ->get();
    }

    public function getSalariesByMonth($year, $month)
    {
        return Salary::whereYear('salary_date', $year)
            ->whereMonth('salary_date', $month)
            ->where('status', 'paid')
            ->with(['teacher.user', 'group'])
            ->get();
    }

    public function getSalariesByYear($year)
    {
        return Salary::whereYear('salary_date', $year)
            ->where('status', 'paid')
            ->with(['teacher.user', 'group'])
            ->get();
    }

    public function getTotalSalariesByPeriod($period, $date = null)
    {
        $query = Salary::where('status', 'paid');

        switch ($period) {
            case 'daily':
                if ($date) {
                    $query->whereDate('salary_date', $date);
                } else {
                    $query->whereDate('salary_date', today());
                }
                break;

            case 'weekly':
                if ($date) {
                    $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
                    $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
                    $query->whereBetween('salary_date', [$startOfWeek, $endOfWeek]);
                } else {
                    $query->whereBetween('salary_date', [now()->startOfWeek(), now()->endOfWeek()]);
                }
                break;

            case 'monthly':
                if ($date) {
                    $query->whereYear('salary_date', date('Y', strtotime($date)))
                        ->whereMonth('salary_date', date('m', strtotime($date)));
                } else {
                    $query->whereYear('salary_date', now()->year)
                        ->whereMonth('salary_date', now()->month);
                }
                break;

            case 'annual':
                if ($date) {
                    $query->whereYear('salary_date', $date);
                } else {
                    $query->whereYear('salary_date', now()->year);
                }
                break;

            case 'overall':
                // No date filter for overall
                break;
        }

        return $query->sum('amount');
    }

    public function getSalaryStatistics($startDate = null, $endDate = null)
    {
        $query = Salary::where('status', 'paid');

        if ($startDate && $endDate) {
            $query->whereBetween('salary_date', [$startDate, $endDate]);
        }

        return [
            'total_salaries' => $query->sum('amount'),
            'average_salary' => $query->avg('amount'),
            'salary_count' => $query->count(),
            'max_salary' => $query->max('amount'),
            'min_salary' => $query->min('amount'),
        ];
    }

    public function getSalariesByTeacher($startDate = null, $endDate = null)
    {
        $query = Salary::join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->join('users', 'teachers.user_id', '=', 'users.user_id')
            ->select(
                'teachers.teacher_id',
                'users.username as teacher_name',
                DB::raw('SUM(salaries.amount) as total_amount'),
                DB::raw('COUNT(salaries.id) as salary_count')
            )
            ->where('salaries.status', 'paid')
            ->groupBy('salaries.teacher_id', 'teachers.teacher_id', 'users.username');

        if ($startDate && $endDate) {
            $query->whereBetween('salaries.salary_date', [$startDate, $endDate]);
        }

        return $query->orderBy('total_amount', 'desc')->get();
    }
}
