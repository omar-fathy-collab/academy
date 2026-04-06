<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\Group;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StudentInvoicesController extends Controller
{
    /**
     * Display student invoices page (Admin View).
     */
    public function show(Request $request, $student_id)
    {
        $user = Auth::user();
        if (!$user || !$user->is_active || !$user->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        $studentId = $student_id;
        if (!$studentId || !is_numeric($studentId)) {
            return redirect()->route('students.index')->with('error', 'Invalid student ID');
        }

        try {
            // Get student information
            $student = Student::with('user.profile')
                ->where('student_id', $studentId)
                ->firstOrFail();

            // Get all invoices for this student
            $invoices = Invoice::with('group')
                ->where('student_id', $studentId)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($invoice) {
                    return [
                        'invoice_id' => $invoice->invoice_id,
                        'invoice_number' => $invoice->invoice_number,
                        'description' => $invoice->description,
                        'amount' => $invoice->amount,
                        'amount_paid' => $invoice->amount_paid,
                        'discount_amount' => $invoice->discount_amount,
                        'discount_percent' => $invoice->discount_percent,
                        'due_date' => $invoice->due_date,
                        'status' => $invoice->status,
                        'group' => $invoice->group ? [
                            'group_name' => $invoice->group->group_name
                        ] : null,
                        'final_amount' => $invoice->final_amount,
                        'balance_due' => $invoice->balance_due,
                    ];
                });

            // Calculate totals
            $totalAmount = $invoices->sum('final_amount');
            $totalPaid = $invoices->sum('amount_paid');
            $totalBalance = $totalAmount - $totalPaid;

            // Get groups for dropdown
            $groups = Group::orderBy('group_name')->get(['group_id', 'group_name', 'uuid']);

            return view('students.invoices', [
                'student' => $student,
                'invoices' => $invoices,
                'totalAmount' => $totalAmount,
                'totalPaid' => $totalPaid,
                'totalBalance' => $totalBalance,
                'groups' => $groups
            ]);

        } catch (\Exception $e) {
            Log::error('Error in StudentInvoicesController@show: '.$e->getMessage());
            return redirect()->route('students.index')->with('error', 'Error loading student invoices');
        }
    }

    /**
     * Create a new invoice for the student.
     */
    public function createInvoice(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->is_active || !$user->isAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access');
        }

        $request->validate([
            'student_id' => 'required|integer|exists:students,student_id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'group_id' => 'nullable|integer|exists:groups,group_id',
        ]);

        try {
            $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

            $invoice = new Invoice;
            $invoice->student_id = $request->student_id;
            $invoice->group_id = $request->group_id;
            $invoice->invoice_number = $invoiceNumber;
            $invoice->description = $request->description;
            $invoice->amount = $request->amount;
            $invoice->due_date = $request->due_date;
            $invoice->status = 'pending';
            $invoice->save();

            return redirect()->back()->with('success', 'Invoice created successfully!');

        } catch (\Exception $e) {
            Log::error('Error creating invoice: '.$e->getMessage());
            return redirect()->back()->with('error', 'Failed to create invoice: '.$e->getMessage());
        }
    }

    /**
     * Display student's own invoices page.
     */
    public function studentInvoices(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->is_active || !$user->isStudent()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        try {
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $invoices = Invoice::with('group')
                ->where('student_id', $student->student_id)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($invoice) {
                    return [
                        'invoice_id' => $invoice->invoice_id,
                        'invoice_number' => $invoice->invoice_number,
                        'description' => $invoice->description,
                        'amount' => $invoice->amount,
                        'amount_paid' => $invoice->amount_paid,
                        'discount_amount' => $invoice->discount_amount,
                        'discount_percent' => $invoice->discount_percent,
                        'due_date' => $invoice->due_date,
                        'status' => $invoice->status,
                        'group' => $invoice->group ? [
                            'group_name' => $invoice->group->group_name
                        ] : null,
                        'final_amount' => $invoice->final_amount,
                        'balance_due' => $invoice->balance_due,
                    ];
                });

            $totalAmount = $invoices->sum('final_amount');
            $totalPaid = $invoices->sum('amount_paid');
            $totalBalance = $totalAmount - $totalPaid;

            $invoiceSummary = [
                'total_count' => $invoices->count(),
                'pending_count' => $invoices->where('status', 'pending')->count() + $invoices->where('status', 'unpaid')->count(),
                'paid_count' => $invoices->where('status', 'paid')->count(),
                'partial_count' => $invoices->where('status', 'partial')->count(),
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'total_balance' => $totalBalance,
            ];

            return view('student_portal.my_invoices', [
                'student' => $student,
                'invoices' => $invoices,
                'totalAmount' => $totalAmount,
                'totalPaid' => $totalPaid,
                'totalBalance' => $totalBalance,
                'invoiceSummary' => $invoiceSummary
            ]);

        } catch (\Exception $e) {
            Log::error('Error in StudentInvoicesController@studentInvoices: '.$e->getMessage());
            return redirect()->route('student.dashboard')->with('error', 'Error loading invoices');
        }
    }
}
