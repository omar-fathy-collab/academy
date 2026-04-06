<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StudentInvoiceViewController extends Controller
{
    /**
     * Display the invoice details.
     */
    public function show(Request $request, $id)
    {
        // Check if user is authenticated
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        try {
            $invoiceQuery = Invoice::with(['student.user.profile', 'group', 'payments.user']);
            
            if (is_numeric($id)) {
                $invoice = $invoiceQuery->where('invoice_id', $id)->first();
            } else {
                $invoice = $invoiceQuery->where('uuid', $id)->first();
            }

            if (! $invoice) {
                return redirect()->route('students.index')->with('error', 'Invoice not found');
            }

            // Check if student is viewing their own invoice
            if (Auth::user()->isStudent()) {
                $student = Student::where('user_id', Auth::id())->first();
                if ($student && $student->student_id != $invoice->student_id) {
                    return redirect()->route('dashboard')->with('error', 'Unauthorized access');
                }
            }

            // ✅ **الحل: استخدم discount_amount إذا كان موجوداً، وإلا احسب من discount_percent**
            if ($invoice->discount_amount > 0) {
                // استخدام القيمة المطلقة للخصم
                $discountAmount = $invoice->discount_amount;
                $discountPercent = ($invoice->amount > 0) ? ($discountAmount / $invoice->amount * 100) : 0;
            } else {
                // استخدام النسبة المئوية للخصم
                $discountAmount = $invoice->amount * ($invoice->discount_percent / 100);
                $discountPercent = $invoice->discount_percent;
            }

            $amountAfterDiscount = $invoice->amount - $discountAmount;

            // الرصيد المتبقي بناءً على المبلغ بعد الخصم
            $remainingBalance = max(0, $amountAfterDiscount - $invoice->amount_paid);

            // تحديد الحالة
            if ($invoice->amount_paid >= $amountAfterDiscount) {
                $status = 'paid';
                $statusText = 'Paid';
            } elseif ($invoice->amount_paid == 0) {
                $status = 'unpaid';
                $statusText = 'Unpaid';
            } else {
                $status = 'partial';
                $statusText = 'Partial';
            }

            // Get payment history
            $payments = $invoice->payments()->with('user')->orderBy('payment_date', 'DESC')->get();

            // Pass to view
            return view('students.view_invoice', [
                'invoice' => $invoice,
                'remainingBalance' => $remainingBalance,
                'payments' => $payments,
                'discountAmount' => $discountAmount,
                'amountAfterDiscount' => $amountAfterDiscount,
                'discountPercent' => $discountPercent,
                'status' => $status,
                'statusText' => $statusText
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in StudentInvoiceViewController@show: '.$e->getMessage());

            return redirect()->route('students.index')->with('error', 'Error loading invoice');
        }

    }
}
