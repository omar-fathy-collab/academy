<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\Invoice;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Repositories\InvoiceRepository;
use App\Services\FinancialService;
use App\Http\Requests\Finance\StoreInvoiceRequest;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Exports\InvoicesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoicesController extends Controller
{
    protected $invoiceRepository;
    protected $financialService;
    protected $notificationService;

    public function __construct(
        InvoiceRepository $invoiceRepository, 
        FinancialService $financialService,
        \App\Services\NotificationService $notificationService
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->financialService = $financialService;
        $this->notificationService = $notificationService;
    }

    /**
     * Resend WhatsApp notification manually.
     */
    public function resendWhatsApp($id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('view', $invoice);
        
        $url = $this->notificationService->getWhatsAppUrl($invoice);
        
        if ($url) {
            return response()->json([
                'success' => true, 
                'whatsapp_url' => $url,
                'message' => 'WhatsApp link generated!'
            ]);
        }
        
        return response()->json(['success' => false, 'message' => 'Failed to generate WhatsApp link. Check student phone number.'], 422);
    }

    /**
     * Get manual Email URL for an invoice (Admin Only).
     */
    public function resendEmail($id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('view', $invoice);
        
        $url = $this->notificationService->getEmailUrl($invoice);
        
        if ($url) {
            return response()->json([
                'success' => true, 
                'email_url' => $url,
                'message' => 'Email link generated!'
            ]);
        }
        
        return response()->json(['success' => false, 'message' => 'Failed to generate email link. Check student email address.'], 422);
    }

    /**
     * Display all invoices with search and pagination.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceRepository->paginatedWithDetails($request->input('per_page', 15));
        
        // Calculate totals for summary (can be moved to a repository method later)
        $totalAmount = Invoice::sum('amount');
        $totalPaid = Invoice::sum('amount_paid');
        $totalBalance = $totalAmount - $totalPaid;

        // The repository should handle the eager loading and calculation as much as possible
        // But for now we kept the manual balance calculation loop if repository doesn't have it.
        // Actually, our repository already eager loads.

        return view('invoices.index', [
            'invoices' => $invoices,
            'groups' => Group::active()->orderBy('group_name')->get(),
            'students' => Student::with('user')->orderBy('student_name')->limit(100)->get(),
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'totalBalance' => $totalBalance
        ]);
    }

    /**
     * Fetch students by group for AJAX requests.
     */
    // في InvoicesController.php
    /**
     * Fetch students by group for AJAX requests.
     */
    /**
     * Fetch students by group for AJAX requests.
     */
    public function getStudentsByGroup(Request $request)
    {
        try {
            $groupId = $request->input('group_id');
            Log::info('getStudentsByGroup called', ['group_id' => $groupId]);

            if (! $groupId || $groupId === 'null') {
                return response()->json([]);
            }

            $group = Group::with(['students.user'])->findOrFail($groupId);

            $students = $group->students->map(function ($student) {
                return [
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name ?? 'N/A',
                    'user' => [
                        'username' => $student->user->username ?? 'N/A',
                        'email' => $student->user->email ?? 'N/A',
                    ],
                ];
            })->values();

            return response()->json($students);

        } catch (\Exception $e) {
            Log::error('Error in InvoicesController@getStudentsByGroup: '.$e->getMessage());
            return response()->json([], 200);
        }
    }

    /**
     * Fetch invoices for AJAX requests.
     */
    /**
     * Fetch invoices for AJAX requests.
     */
    public function fetchInvoices(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        try {
            $invoices = $this->invoiceRepository->paginatedWithDetails($request->input('per_page', 15));

            return response()->json([
                'invoices' => $invoices->items(),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in InvoicesController@fetchInvoices: '.$e->getMessage());
            return response()->json(['error' => 'Error loading invoices'], 500);
        }
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorize('create', Invoice::class);

        try {
            // Generate invoice number (could be moved to a helper or service)
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
            
            $data = $request->validated();
            $data['invoice_number'] = $invoiceNumber;
            $data['status'] = 'pending';
            $data['amount_paid'] = 0;
            $data['discount_percent'] = $data['discount_percent'] ?? 0;
            $data['discount_amount'] = $data['discount_amount'] ?? 0;

            $invoice = $this->financialService->createInvoice($data);

            Log::info('Invoice permanently created', ['id' => $invoice->invoice_id, 'number' => $invoice->invoice_number]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء الفاتورة بنجاح!',
                    'invoice' => $invoice
                ]);
            }

            return redirect()->route('invoices.index')->with('success', 'تم إنشاء الفاتورة بنجاح!');

        } catch (\Exception $e) {
            Log::error('Error creating invoice: ' . $e->getMessage());
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنشاء الفاتورة: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'فشل في إنشاء الفاتورة: ' . $e->getMessage());
        }
    }

    /**
     * Mark all unpaid invoices (or selected invoice IDs) as paid.
     */
    /**
     * Update invoice discount.
     */
    public function updateDiscount(Request $request, $invoice_id)
    {
        $invoice = Invoice::findOrFail($invoice_id);
        $this->authorize('update', $invoice);

        $request->validate([
            'discount_percent' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $invoice->update(['discount_percent' => $request->discount_percent]);

            // Recalculate based on model accessors
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الخصم بنجاح',
                'final_amount' => $invoice->final_amount,
                'balance_due' => $invoice->balance_due,
                'status' => $invoice->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating discount: '.$e->getMessage());
            return response()->json(['error' => 'فشل في تحديث الخصم'], 500);
        }
    }

    public function show($id)
    {
        $invoice = Invoice::with(['student.user', 'group', 'payments.user'])->findOrFail($id);
        $this->authorize('view', $invoice);

        // Same logic as student view to keep it consistent
        $discountAmount = $invoice->discount_amount > 0 
            ? $invoice->discount_amount 
            : ($invoice->amount * ($invoice->discount_percent / 100));
            
        $amountAfterDiscount = $invoice->amount - $discountAmount;
        $remainingBalance = max(0, $amountAfterDiscount - $invoice->amount_paid);

        return view('students.view_invoice', [
            'invoice' => $invoice,
            'remainingBalance' => $remainingBalance,
            'payments' => $invoice->payments,
            'discountAmount' => $discountAmount,
            'amountAfterDiscount' => $amountAfterDiscount,
            'discountPercent' => $invoice->discount_percent,
        ]);
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        // Fetch all groups, but ensure the current group is included even if inactive
        $groups = Group::active()->orWhere('group_id', $invoice->group_id)->orderBy('group_name')->get();
        
        // Fetch students, prioritizing the current one if not in top list
        $students = Student::with('user')->orderBy('student_name')->limit(200)->get();
        if ($invoice->student_id && !$students->pluck('student_id')->contains($invoice->student_id)) {
            $currentStudent = Student::with('user')->find($invoice->student_id);
            if ($currentStudent) {
                $students->prepend($currentStudent);
            }
        }

        return view('invoices.edit', [
            'invoice' => $invoice,
            'groups' => $groups,
            'students' => $students
        ]);
    }

    /**
     * Update invoice.
     */
    public function update(StoreInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        try {
            // Check if amount changed and there are payments
            if ($invoice->amount_paid > 0 && $request->amount != $invoice->amount) {
                // If new amount is less than paid amount, we can't just update
                $discountAmount = $request->amount * ($request->discount_percent / 100);
                $finalAmount = $request->amount - $discountAmount;
                
                if ($finalAmount < $invoice->amount_paid) {
                    return redirect()->back()->with('error', 'المبلغ الجديد بعد الخصم أقل من المبلغ المدفوع بالفعل.');
                }
            }

            $invoice->update($request->validated());

            return redirect()->route('invoices.index')->with('success', 'تم تحديث الفاتورة بنجاح!');

        } catch (\Exception $e) {
            Log::error('Error updating invoice: '.$e->getMessage());
            return redirect()->back()->with('error', 'فشل في تحديث الفاتورة: '.$e->getMessage());
        }
    }

    public function resetPayments($invoice_id)
    {
        $invoice = Invoice::findOrFail($invoice_id);
        $this->authorize('update', $invoice); // Resetting requires full update permissions

        try {
            // Using service for transaction-safe delete and reversal
            foreach ($invoice->payments as $payment) {
                $this->financialService->deletePayment($payment->payment_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'تمت إعادة تعيين مدفوعات الفاتورة بنجاح',
                'amount_paid' => 0,
                'new_status' => 'pending'
            ]);

        } catch (\Exception $e) {
            Log::error('Error resetting invoice payments: '.$e->getMessage());
            return response()->json(['success' => false, 'error' => 'فشل في إعادة تعيين المدفوعات'], 500);
        }
    }

    /**
     * Delete invoice.
     */
    public function destroy(Request $request, $invoice_id)
    {
        $invoice = Invoice::findOrFail($invoice_id);
        $this->authorize('delete', $invoice);

        try {
            $reason = $request->input('reason', 'تم الحذف بواسطة المسؤول');
            $this->financialService->deleteInvoice($invoice_id, $reason);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الفاتورة وأرشفتها بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting invoice: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'فشل في حذف الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force delete invoice with payments (for admin only).
     */
    public function forceDestroy(Request $request, $invoice_id)
    {
        $this->authorize('forceDelete', Invoice::class);

        try {
            $reason = $request->input('reason', 'تم الحذف النهائي بواسطة المسؤول');
            $this->financialService->deleteInvoice($invoice_id, $reason);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الفاتورة والمدفوعات المرتبطة بها نهائياً مع الأرشفة'
            ]);

        } catch (\Exception $e) {
            Log::error('Error force deleting invoice: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'فشل في حذف الفاتورة: '.$e->getMessage(),
            ], 500);
        }
    }

    public function markAllAsPaid(Request $request)
    {
        $this->authorize('update', Invoice::class);

        $invoiceIds = $request->input('invoice_ids', []);

        try {
            $invoices = Invoice::where('status', '!=', 'paid')
                ->when(! empty($invoiceIds), fn($q) => $q->whereIn('invoice_id', $invoiceIds))
                ->get();

            foreach ($invoices as $invoice) {
                $balance = $invoice->balance_due;
                if ($balance > 0) {
                    $this->financialService->recordPayment($invoice->invoice_id, [
                        'amount' => $balance,
                        'payment_method' => 'admin_marked_paid',
                        'notes' => 'تم التأشير كمدفوع بالكامل بواسطة المسؤول'
                    ]);
                }
            }

            return response()->json(['success' => true, 'message' => 'تم دفع الفواتير المحددة بالكامل']);

        } catch (\Exception $e) {
            Log::error('Error marking invoices paid: '.$e->getMessage());
            return response()->json(['error' => 'فشل في دفع الفواتير'], 500);
        }
    }

    public function publicShow($token)
    {
        $invoice = Invoice::with(['student.user', 'group', 'payments'])
            ->where('public_token', $token)
            ->firstOrFail();

        return view('invoices.public-show', [
            'invoice' => $invoice,
            'site_settings' => [
                'site_name' => config('app.name', 'ICT Academy'),
                'logo' => asset('img/ictlogo1.png')
            ]
        ]);
    }

    public function share(Request $request, $id)
    {
        $invoice = Invoice::with('student')->findOrFail($id);
        $this->authorize('view', $invoice);

        $type = $request->get('type', 'whatsapp');
        $url = route('invoices.public.show', $invoice->public_token);
        
        $message = "فاتورة جديدة من " . config('app.name') . "\n";
        $message .= "رقم الفاتورة: " . $invoice->invoice_number . "\n";
        $message .= "المبلغ المطلوب: " . $invoice->balance_due . " ج.م\n";
        $message .= "يمكنك عرض الفاتورة والتفاصيل من الرابط التالي:\n" . $url;

        if ($type === 'whatsapp') {
            $phone = $invoice->student->phone ?? '';
            // Clean phone number (remove non-digits)
            $phone = preg_replace('/\D/', '', $phone);
            if (str_starts_with($phone, '0')) {
                $phone = '2' . $phone; // Assume Egypt if starts with 0
            }
            
            $whatsappUrl = "https://wa.me/" . $phone . "?text=" . urlencode($message);
            return response()->json(['url' => $whatsappUrl]);
        }

        return response()->json(['url' => $url, 'message' => $message]);
    }

    public function export()
    {
        $this->authorize('viewAny', Invoice::class);
        return Excel::download(new InvoicesExport, 'invoices_'.now()->format('Y-m-d').'.xlsx');
    }
}
