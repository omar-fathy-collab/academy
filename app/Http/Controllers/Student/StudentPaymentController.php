<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentPaymentController extends Controller
{
    protected $notificationService;

    public function __construct(\App\Services\NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the student's invoices (Student View).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->isStudent()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        try {
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $invoices = Invoice::with('group')
                ->where('student_id', $student->student_id)
                ->orderBy('created_at', 'DESC')
                ->get();

            $totalAmount = 0;
            $totalPaid = 0;
            $pendingCount = 0;

            foreach ($invoices as $invoice) {
                $totalAmount += $invoice->final_amount;
                $totalPaid += $invoice->amount_paid;
                if ($invoice->status !== 'paid') {
                    $pendingCount++;
                }
            }

            return view('student.payments.index', [
                'invoices' => $invoices,
                'student' => $student,
                'totalAmount' => $totalAmount,
                'totalPaid' => $totalPaid,
                'totalBalance' => $totalAmount - $totalPaid,
                'invoiceSummary' => [
                    'pending_count' => $pendingCount
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in StudentPaymentController@index: '.$e->getMessage());
            return redirect()->route('student.dashboard')->with('error', 'Error loading invoices: '.$e->getMessage());
        }
    }

    /**
     * Display the student's payment history.
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        if (! $user->isStudent()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        try {
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $payments = Payment::whereHas('invoice', function($query) use ($student) {
                    $query->where('student_id', $student->student_id);
                })
                ->with('invoice.group')
                ->orderBy('payment_date', 'DESC')
                ->get();

            return view('student.payments.history', [
                'payments' => $payments,
                'student' => $student
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in StudentPaymentController@history: '.$e->getMessage());
            return redirect()->route('student.dashboard')->with('error', 'Error loading payment history');
        }
    }

    /**
     * Show the add payment form (Admin View).
     */
    public function show(Request $request, $invoice_id)
    {
        // Check if user is authenticated and is admin (role_id = 1)
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        if (! $user->is_active || ! $user->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        $invoiceId = $invoice_id;

        if (! $invoiceId || ! is_numeric($invoiceId)) {
            return redirect()->route('students.index')->with('error', 'Invalid invoice ID');
        }

        try {
            // Get invoice details with student and group information
            $invoice = Invoice::with(['student.user.profile', 'group'])
                ->where('invoice_id', $invoiceId)
                ->first();

            if (! $invoice) {
                return redirect()->route('students.index')->with('error', 'Invoice not found');
            }

            // Calculate amounts using model accessors
            $finalAmount = $invoice->final_amount;
            $balanceDue = $invoice->balance_due;
            $discountAmount = $invoice->discount_amount > 0
                ? $invoice->discount_amount
                : ($invoice->amount * ($invoice->discount_percent / 100));

            // جلب الفواتير القديمة
            $oldInvoices = Invoice::where('student_id', $invoice->student_id)
                ->where('invoice_id', '!=', $invoice->invoice_id)
                ->where(function ($query) {
                    $query->where('status', '!=', 'paid')
                        ->orWhere('amount_paid', '<', DB::raw('amount - COALESCE(discount_amount, 0)'));
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return view('students.payment', [
                'invoice' => $invoice,
                'balanceDue' => $balanceDue,
                'discountAmount' => $discountAmount,
                'finalAmount' => $finalAmount,
                'oldInvoices' => $oldInvoices
            ]);

        } catch (\Exception $e) {
            Log::error('Error in StudentPaymentController@show: '.$e->getMessage());

            return redirect()->route('students.index')->with('error', 'Error loading payment form');
        }
    }

    /**
     * Process the payment.
     */
    public function processPayment(Request $request)
    {
        // Check authentication
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required',
            ], 401);
        }

        $user = Auth::user();
        if (! $user->is_active || ! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access',
            ], 403);
        }

        // Validate input
        $request->validate([
            'invoice_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'notes' => 'nullable|string|max:255',
            'receipt_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'send_whatsapp' => 'nullable|boolean',
            'send_email' => 'nullable|boolean',
        ]);

        try {
            $invoice = Invoice::findOrFail($request->invoice_id);

            // تحديث الخصم إذا تم تقديمه
            if ($request->has('discount_amount') && $request->discount_amount >= 0) {
                // استخدام discount_amount المباشر
                $invoice->discount_amount = $request->discount_amount;
                $discountAmount = $request->discount_amount;
                $finalAmount = $invoice->amount - $discountAmount;
            } elseif ($request->has('discount_percent') && $request->discount_percent != $invoice->discount_percent) {
                // استخدام discount_percent
                $invoice->discount_percent = $request->discount_percent;
                $discountAmount = $invoice->amount * ($request->discount_percent / 100);
                $finalAmount = $invoice->amount - $discountAmount;
            } else {
                // استخدام القيم الحالية
                $discountAmount = $invoice->discount_amount > 0
                    ? $invoice->discount_amount
                    : ($invoice->amount * ($invoice->discount_percent / 100));
                $finalAmount = $invoice->amount - $discountAmount;
            }

            $balanceDue = $finalAmount - $invoice->amount_paid;

            // Validate amount
            if ($request->amount <= 0 || $request->amount > $balanceDue) {
                return redirect()->back()->with('error', 'Invalid payment amount. Must be between 0.01 and '.number_format($balanceDue, 2).' EGP');
            }

            // Handle file upload
            $receiptImage = null;
            if ($request->hasFile('receipt_image')) {
                $receiptImage = $request->file('receipt_image')->store('receipts', 'public');
            }

            // Create payment record
            $payment = new Payment;
            $payment->invoice_id = $request->invoice_id;
            $payment->amount = $request->amount;
            $payment->payment_method = $request->payment_method;
            $payment->notes = $this->buildPaymentNotes($request->notes, $discountAmount);
            $payment->receipt_image = $receiptImage;
            $payment->confirmed_by = $user->id;
            $payment->whatsapp_sent = 0;
            $payment->save();

            // Update invoice
            $newAmountPaid = $invoice->amount_paid + $request->amount;
            $newStatus = ($newAmountPaid >= $finalAmount) ? 'paid' : (($newAmountPaid > 0) ? 'partial' : 'pending');

            $invoice->amount_paid = $newAmountPaid;
            $invoice->status = $newStatus;
            $invoice->save();

            // WhatsApp notification logic
            $waUrl = null;
            if ($request->boolean('send_whatsapp')) {
                $waUrl = $this->notificationService->getWhatsAppUrl($invoice);
                if ($waUrl) {
                    $payment->whatsapp_sent = 1;
                    $payment->save();
                }
            }

            // Email notification logic (Manual Flow)
            $emailUrl = null;
            if ($request->boolean('send_email')) {
                $emailUrl = $this->notificationService->getEmailUrl($invoice);
            }

            $response = redirect()->route('student.invoice.view', ['id' => $request->invoice_id])
                ->with('message', 'Payment recorded successfully!');

            if ($waUrl) {
                $response->with('whatsapp_url', $waUrl);
            }
            
            if ($emailUrl) {
                $response->with('email_url', $emailUrl);
            }

            $response->with('invoice_url', route('invoices.public.show', $invoice->public_token));

            return $response;

        } catch (\Exception $e) {
            Log::error('Error processing payment: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to process payment: '.$e->getMessage());
        }
    }

    /**
     * Build payment notes including discount information
     */
    private function buildPaymentNotes($originalNotes, $discountAmount)
    {
        $notes = $originalNotes ?? '';

        if ($discountAmount > 0) {
            $discountInfo = 'Discount: '.number_format($discountAmount, 2).' EGP';
            if (! empty($notes)) {
                $notes .= ' | '.$discountInfo;
            } else {
                $notes = $discountInfo;
            }
        }

        return $notes;
    }

    /**
     * Build WhatsApp message including discount information
     */
    private function buildWhatsAppMessage($invoice, $payment, $discountAmount)
    {
        $message = 'تم تسجيل دفعة بقيمة '.number_format($payment->amount, 2).' EGP للفاتورة: '.$invoice->invoice_number;

        if ($discountAmount > 0) {
            $message .= ' - خصم: '.number_format($discountAmount, 2).' EGP';
        }

        $remaining = ($invoice->amount - $discountAmount) - $invoice->amount_paid;
        if ($remaining > 0) {
            $message .= ' - المتبقي: '.number_format($remaining, 2).' EGP';
        } else {
            $message .= ' - تم سداد الفاتورة بالكامل';
        }

        return $message;
    }

    /**
     * Get manual WhatsApp URL for an invoice (Admin Only).
     */
    public function getWhatsAppUrl($invoice_id)
    {
        try {
            $invoice = Invoice::findOrFail($invoice_id);
            $url = $this->notificationService->getWhatsAppUrl($invoice);
            
            return response()->json([
                'success' => true,
                'url' => $url
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get manual Email URL for an invoice (Admin Only).
     */
    public function sendManualEmail($invoice_id)
    {
        try {
            $invoice = Invoice::findOrFail($invoice_id);
            $url = $this->notificationService->getEmailUrl($invoice);
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'message' => 'Email link generated successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
