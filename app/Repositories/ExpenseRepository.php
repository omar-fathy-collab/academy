<?php

namespace App\Repositories;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseRepository
{
    public function getAllExpenses($filters = [])
    {
        $query = Expense::with(['category', 'approvedBy', 'user'])
            ->select('expenses.*');

        // Apply filters
        if (! empty($filters['start_date'])) {
            $query->whereDate('expenses.expense_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('expenses.expense_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('expenses.status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('expenses.category_id', $filters['category_id']);
        }

        return $query->orderBy('expenses.expense_date', 'desc')->get();
    }

    public function getExpensesByDateRange($startDate, $endDate)
    {
        return Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->with(['category'])
            ->get();
    }

    public function getExpensesByMonth($year, $month)
    {
        return Expense::whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->where('status', 'approved')
            ->with(['category'])
            ->get();
    }

    public function getExpensesByYear($year)
    {
        return Expense::whereYear('expense_date', $year)
            ->where('status', 'approved')
            ->with(['category'])
            ->get();
    }

    public function getTotalExpensesByPeriod($period, $date = null)
    {
        $query = Expense::where('status', 'approved');

        switch ($period) {
            case 'daily':
                if ($date) {
                    $query->whereDate('expense_date', $date);
                } else {
                    $query->whereDate('expense_date', today());
                }
                break;

            case 'weekly':
                if ($date) {
                    $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
                    $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
                    $query->whereBetween('expense_date', [$startOfWeek, $endOfWeek]);
                } else {
                    $query->whereBetween('expense_date', [now()->startOfWeek(), now()->endOfWeek()]);
                }
                break;

            case 'monthly':
                if ($date) {
                    $query->whereYear('expense_date', date('Y', strtotime($date)))
                        ->whereMonth('expense_date', date('m', strtotime($date)));
                } else {
                    $query->whereYear('expense_date', now()->year)
                        ->whereMonth('expense_date', now()->month);
                }
                break;

            case 'annual':
                if ($date) {
                    $query->whereYear('expense_date', $date);
                } else {
                    $query->whereYear('expense_date', now()->year);
                }
                break;

            case 'overall':
                // No date filter for overall
                break;
        }

        return $query->sum('amount');
    }

    public function getExpensesByCategory($startDate = null, $endDate = null)
    {
        $query = Expense::join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.name as category_name',
                DB::raw('SUM(expenses.amount) as total_amount'),
                DB::raw('COUNT(expenses.id) as expense_count')
            )
            ->where('expenses.status', 'approved')
            ->groupBy('expenses.category_id', 'expense_categories.name');

        if ($startDate && $endDate) {
            $query->whereBetween('expenses.expense_date', [$startDate, $endDate]);
        }

        return $query->orderBy('total_amount', 'desc')->get();
    }

    public function getExpenseStatistics($startDate = null, $endDate = null)
    {
        $query = Expense::where('status', 'approved');

        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        return [
            'total_expenses' => $query->sum('amount'),
            'average_expense' => $query->avg('amount'),
            'expense_count' => $query->count(),
            'max_expense' => $query->max('amount'),
            'min_expense' => $query->min('amount'),
        ];
    }
}
