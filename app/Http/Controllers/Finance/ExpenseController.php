<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\ExpensesExport;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseController extends Controller
{
    /**
     * Show the add expense form.
     */
    /**
     * Show the add expense form.
     */
    public function add()
    {
        // استخدام القيم من الموديل
        $categories = Expense::getCategories();
        $payment_methods = Expense::getPaymentMethods();

        return view('expenses.create', [
            'categories' => $categories,
            'payment_methods' => $payment_methods
        ]);
    }

    /**
     * Store a new expense.
     */
    /**
     * Store a new expense.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|string|max:50',
            'payment_method' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:500',
            'expense_date' => 'required|date',
        ]);

        Expense::create([
            'category' => $request->category,
            'payment_method' => $request->payment_method,
            'amount' => $request->amount,
            'description' => $request->description,
            'expense_date' => $request->expense_date,
            'recorded_by' => Auth::id(),
            'is_approved' => 0, // Always unapproved by default
        ]);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded successfully and is currently pending approval.');
    }

    /**
     * Display a listing of expenses.
     */
    /**
     * Display a listing of expenses.
     */
    public function index()
    {
        // Get all expenses with user information
        $expenses = DB::table('expenses')
            ->select('expenses.*', 'users.username as recorded_by_name')
            ->leftJoin('users', 'expenses.recorded_by', '=', 'users.id')
            ->orderBy('expenses.expense_date', 'desc')
            ->orderBy('expenses.created_at', 'desc')
            ->get();

        // Calculate totals for APPROVED expenses only
        $approved_expenses = $expenses->where('is_approved', 1);

        $total_expenses = $approved_expenses->sum('amount');
        $monthly_expenses = $approved_expenses->filter(function ($expense) {
            return date('Y-m', strtotime($expense->expense_date)) == date('Y-m');
        })->sum('amount');

        // Get expense categories for filter
        $expense_categories = [];
        foreach ($expenses as $expense) {
            if ($expense->category) {
                $expense_categories[$expense->category] = $expense->category;
            }
        }
        $expense_categories = array_unique($expense_categories);

        // Get payment methods for filter
        $payment_methods = [];
        foreach ($expenses as $expense) {
            if ($expense->payment_method) {
                $payment_methods[$expense->payment_method] = $expense->payment_method;
            }
        }
        $payment_methods = array_unique($payment_methods);

        // Get expenses by category (approved only)
        $expenses_by_category = [];
        foreach ($approved_expenses as $expense) {
            $category = $expense->category;
            if (! isset($expenses_by_category[$category])) {
                $expenses_by_category[$category] = 0;
            }
            $expenses_by_category[$category] += $expense->amount;
        }

        // Get expenses by payment method (approved only)
        $expenses_by_payment = [];
        foreach ($approved_expenses as $expense) {
            $method = $expense->payment_method ?: 'Not Specified';
            if (! isset($expenses_by_payment[$method])) {
                $expenses_by_payment[$method] = 0;
            }
            $expenses_by_payment[$method] += $expense->amount;
        }

        return view('expenses.index', [
            'expenses' => $expenses,
            'total_expenses' => (float)$total_expenses,
            'monthly_expenses' => (float)$monthly_expenses,
            'expense_categories' => array_values($expense_categories),
            'payment_methods' => array_values($payment_methods),
            'expenses_by_category' => $expenses_by_category,
            'expenses_by_payment' => $expenses_by_payment
        ]);
    }

    /**
     * Approve an expense.
     *
     * @param  Expense $expense
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Expense $expense)
    {
        try {
            $expense->update(['is_approved' => 1]);

            return redirect()->route('expenses.index')->with('success', 'Expense approved successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to approve expense: ' . $e->getMessage());
        }
    }

    /**
     * Reject an expense.
     *
     * @param  Expense $expense
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Expense $expense)
    {
        try {
            $expense->update(['is_approved' => 0]);

            return redirect()->route('expenses.index')->with('success', 'Expense rejected successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to reject expense: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified expense from storage.
     *
     * @param  Expense $expense
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Expense $expense)
    {
        try {
            $expense->delete();

            return redirect()->route('expenses.index')->with('success', 'Expense deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete expense: ' . $e->getMessage());
        }
    }

    public function export()
    {
        // Permission check is handled by middleware but we can be explicit
        return Excel::download(new ExpensesExport, 'expenses_'.now()->format('Y-m-d').'.xlsx');
    }
}
