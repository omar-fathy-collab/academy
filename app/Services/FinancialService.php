<?php

namespace App\Services;

use App\Models\DeletedInvoiceArchive;
use App\Models\DeletedPaymentArchive;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class FinancialService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get total revenue from student payments (invoices).
     */
    public function getTotalRevenue($startDate = null, $endDate = null)
    {
        $query = DB::table('payments');
        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    /**
     * Get total approved academy expenses.
     */
    public function getTotalExpenses($startDate = null, $endDate = null)
    {
        $query = DB::table('expenses')->where('is_approved', 1);
        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    /**
     * Get total payments made to teachers.
     */
    public function getTotalTeacherPayments($startDate = null, $endDate = null)
    {
        $query = DB::table('teacher_payments');
        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    /**
     * Get total deductions (money taken from teachers).
     */
    public function getTotalDeductions($startDate = null, $endDate = null)
    {
        $query = DB::table('teacher_adjustments')
            ->where('type', 'deduction')
            ->where('payment_status', 'paid');
        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    /**
     * Get total bonuses (additional money given to teachers).
     */
    public function getTotalBonuses($startDate = null, $endDate = null)
    {
        $query = DB::table('teacher_adjustments')
            ->where('type', 'bonus')
            ->where('payment_status', 'paid');
        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    /**
     * Get total capital additions recorded.
     */
    public function getTotalCapital($startDate = null, $endDate = null)
    {
        $query = DB::table('capital_additions');
        
        if ($startDate && $endDate) {
            $query->whereBetween('addition_date', [$startDate, $endDate]);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Calculate Net Operating Profit.
     * Formula: Revenue + Deductions - (Expenses + TeacherPayments + Bonuses)
     */
    public function calculateNetProfit($startDate = null, $endDate = null)
    {
        $revenue = $this->getTotalRevenue($startDate, $endDate);
        $expenses = $this->getTotalExpenses($startDate, $endDate);
        $teacherPayments = $this->getTotalTeacherPayments($startDate, $endDate);
        
        $bonuses = $this->getTotalBonuses($startDate, $endDate);
        $deductions = $this->getTotalDeductions($startDate, $endDate);

        // Deductions are "income" (money not spent), Bonuses are extra expenses.
        return (floatval($revenue) + floatval($deductions))
               - (floatval($expenses) + floatval($teacherPayments) + floatval($bonuses));
    }

    /**
     * Get total approved/completed vault withdrawals.
     */
    public function getVaultWithdrawals($startDate = null, $endDate = null)
    {
        $query = DB::table('admin_withdrawals')
            ->whereIn('status', ['approved', 'completed']);
            
        if ($startDate && $endDate) {
            $query->whereBetween('approved_at', [$startDate, $endDate]);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get total outstanding student balances.
     */
    public function getTotalOutstandingBalance()
    {
        return (float) DB::table('invoices')
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(amount - amount_paid - discount_amount) as balance')
            ->value('balance') ?? 0;
    }

    /**
     * Get Gross Expenses (Operating Expenses + Salaries + Bonuses).
     */
    public function getGrossExpenses($startDate = null, $endDate = null)
    {
        $expenses = $this->getTotalExpenses($startDate, $endDate);
        $teacherPayments = $this->getTotalTeacherPayments($startDate, $endDate);
        $bonuses = $this->getTotalBonuses($startDate, $endDate);

        return (floatval($expenses) + floatval($teacherPayments) + floatval($bonuses));
    }

    /**
     * Get total profit distributions.
     */
    public function getTotalProfitDistributions($startDate = null, $endDate = null)
    {
        $query = DB::table('profit_distributions');
        
        if ($startDate && $endDate) {
            $query->whereBetween('distribution_date', [$startDate, $endDate]);
        }

        return (float) $query->sum('total_net_profit');
    }

    /**
     * Get start and end dates for a given period.
     */
    public function getDatesForPeriod(string $period): array
    {
        $start = null;
        $end = now()->toDateTimeString();

        switch ($period) {
            case 'today':
                $start = now()->startOfDay()->toDateTimeString();
                break;
            case 'month':
                $start = now()->startOfMonth()->toDateTimeString();
                break;
            case 'year':
                $start = now()->startOfYear()->toDateTimeString();
                break;
            case 'all':
            default:
                $start = null;
                $end = null;
                break;
        }

        return [$start, $end];
    }

    /**
     * Calculate Net Profit for a specific period.
     */
    public function getNetProfitForPeriod(string $period): float
    {
        [$start, $end] = $this->getDatesForPeriod($period);
        return $this->calculateNetProfit($start, $end);
    }

    /**
     * Get summary of financial metrics for a period.
     */
    public function getFinancialMetrics(string $period = 'all'): array
    {
        [$start, $end] = $this->getDatesForPeriod($period);

        return [
            'revenue' => (float) $this->getTotalRevenue($start, $end),
            'expenses' => (float) $this->getTotalExpenses($start, $end),
            'teacher_payments' => (float) $this->getTotalTeacherPayments($start, $end),
            'bonuses' => (float) $this->getTotalBonuses($start, $end),
            'deductions' => (float) $this->getTotalDeductions($start, $end),
            'withdrawals' => (float) $this->getVaultWithdrawals($start, $end),
            'net_profit' => (float) $this->calculateNetProfit($start, $end),
        ];
    }

    /**
     * Calculate Available balance for a specific admin based on their profit percentage.
     */
    public function getAvailableBalanceForAdmin($user): float
    {
        if (!$user) return 0;

        $vault = DB::table('admin_vaults')->where('user_id', $user->id)->first();
        if (!$vault) return 0;

        $netProfit = $this->calculateNetProfit(); // Total net profit all time
        $profitShare = ($netProfit * $vault->profit_percentage) / 100;
        
        $userApprovedWithdrawals = DB::table('admin_withdrawals')
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'completed'])
            ->sum('amount');

        return (float) max($profitShare - $userApprovedWithdrawals, 0);
    }

    /**
     * Calculate Current Vault Balance (Cash on Hand).
     * Formula: (Revenue + Capital + Deductions) - (Expenses + TeacherPayments + Bonuses + Withdrawals + Distributions)
     */
    public function getVaultBalance()
    {
        $revenue = $this->getTotalRevenue();
        $capital = $this->getTotalCapital();
        $deductions = $this->getTotalDeductions(); // Deductions stay in vault
        
        $expenses = $this->getTotalExpenses();
        $teacherPayments = $this->getTotalTeacherPayments();
        $bonuses = $this->getTotalBonuses(); // Bonuses leave vault
        $withdrawals = $this->getVaultWithdrawals();
        $distributions = $this->getTotalProfitDistributions();

        return (floatval($revenue) + floatval($capital) + floatval($deductions))
               - (floatval($expenses) + floatval($teacherPayments) + floatval($bonuses) + floatval($withdrawals) + floatval($distributions));
    }

    // =========================================================================
    // TRANSACTION-SAFE INVOICE OPERATIONS
    // =========================================================================

    /**
     * Create a new invoice inside a DB transaction.
     *
     * @param  array  $data  Validated invoice data.
     * @return \App\Models\Invoice
     *
     * @throws \Throwable
     */
    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create($data);

            Log::info('Invoice created', [
                'invoice_id' => $invoice->invoice_id,
                'student_id' => $invoice->student_id,
                'amount'     => $invoice->amount,
                'by'         => Auth::id(),
            ]);

            return $invoice;
        });
    }

    /**
     * Soft-delete an invoice and archive a snapshot for audit trail.
     *
     * @param  int  $invoiceId
     * @param  string  $reason
     * @return void
     *
     * @throws \Throwable
     */
    public function deleteInvoice(int $invoiceId, string $reason = ''): void
    {
        DB::transaction(function () use ($invoiceId, $reason) {
            $invoice = Invoice::with('payments')->findOrFail($invoiceId);

            // 1. Archive snapshot
            DeletedInvoiceArchive::create([
                'original_invoice_id'  => $invoice->invoice_id,
                'invoice_number'       => $invoice->invoice_number,
                'student_id'           => $invoice->student_id,
                'group_id'             => $invoice->group_id,
                'amount'               => $invoice->amount,
                'amount_paid'          => $invoice->amount_paid,
                'discount_amount'      => $invoice->discount_amount,
                'discount_percent'     => $invoice->discount_percent,
                'status_before_deletion' => $invoice->status,
                'notes'                => $invoice->description ?? '',
                'deleted_reason'       => $reason,
                'deleted_by'           => Auth::id(),
                'original_created_at'  => $invoice->created_at,
                'deleted_at'           => now(),
            ]);

            // 2. Archive each linked payment
            foreach ($invoice->payments as $payment) {
                DeletedPaymentArchive::create([
                    'original_payment_id' => $payment->payment_id,
                    'invoice_id'          => $payment->invoice_id,
                    'amount'              => $payment->amount,
                    'payment_method'      => $payment->payment_method,
                    'deleted_reason'      => 'Parent invoice deleted',
                    'deleted_by'          => Auth::id(),
                    'deleted_at'          => now(),
                ]);
            }

            // 3. Soft-delete payments then invoice
            $invoice->payments()->delete();
            $invoice->delete();

            Log::warning('Invoice soft-deleted and archived', [
                'invoice_id' => $invoiceId,
                'by'         => Auth::id(),
                'reason'     => $reason,
            ]);
        });
    }

    // =========================================================================
    // TRANSACTION-SAFE PAYMENT OPERATIONS
    // =========================================================================

    /**
     * Record a payment against an invoice and update invoice status atomically.
     *
     * @param  int  $invoiceId
     * @param  array  $data  Validated data: amount, payment_method, notes (optional)
     * @return \App\Models\Payment
     *
     * @throws \Throwable
     */
    public function recordPayment(int $invoiceId, array $data): Payment
    {
        return DB::transaction(function () use ($invoiceId, $data) {
            /** @var \App\Models\Invoice $invoice */
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);

            // Guard: prevent over-payment
            $finalAmount = $invoice->final_amount;
            $balanceDue  = $finalAmount - $invoice->amount_paid;

            if ((float) $data['amount'] > $balanceDue + 0.001) {
                throw new \InvalidArgumentException(
                    "Payment of {$data['amount']} exceeds balance due of {$balanceDue}."
                );
            }

            // 1. Record payment
            $payment = Payment::create([
                'invoice_id'     => $invoice->invoice_id,
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'],
                'notes'          => $data['notes'] ?? null,
                'confirmed_by'   => Auth::id(),
                'payment_date'   => now(),
            ]);

            // 2. Update invoice aggregates
            $newAmountPaid = $invoice->amount_paid + $payment->amount;
            $newStatus     = ($newAmountPaid >= $finalAmount) ? 'paid' : 'partial';

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status'      => $newStatus,
            ]);

            Log::info('Payment recorded', [
                'payment_id' => $payment->payment_id,
                'invoice_id' => $invoiceId,
                'amount'     => $payment->amount,
                'by'         => Auth::id(),
            ]);

            // 3. Trigger notification
            try {
                $this->notificationService->sendPaymentConfirmation($invoice);
            } catch (\Exception $e) {
                Log::error('Failed to trigger payment notification: ' . $e->getMessage());
            }

            return $payment;
        });
    }

    /**
     * Soft-delete a payment, reverse its effect on the invoice, and archive it.
     *
     * @param  int  $paymentId
     * @return void
     *
     * @throws \Throwable
     */
    public function deletePayment(int $paymentId): void
    {
        DB::transaction(function () use ($paymentId) {
            /** @var \App\Models\Payment $payment */
            $payment = Payment::with('invoice')->lockForUpdate()->findOrFail($paymentId);
            $invoice = $payment->invoice;

            // 1. Archive payment snapshot
            DeletedPaymentArchive::create([
                'original_payment_id' => $payment->payment_id,
                'invoice_id'          => $payment->invoice_id,
                'amount'              => $payment->amount,
                'payment_method'      => $payment->payment_method,
                'payment_date'        => $payment->payment_date,
                'deleted_reason'      => 'Manual deletion',
                'deleted_by'          => Auth::id(),
                'deleted_at'          => now(),
            ]);

            // 2. Adjust invoiced amount
            $newAmountPaid = max($invoice->amount_paid - $payment->amount, 0);
            $newStatus     = $newAmountPaid <= 0 ? 'pending' : 'partial';

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status'      => $newStatus,
            ]);

            // 3. Soft-delete the payment
            $payment->delete();

            Log::warning('Payment soft-deleted and reversed', [
                'payment_id' => $paymentId,
                'by'         => Auth::id(),
            ]);
        });
    }

    /**
     * Record a unified transaction (Student Payment, Teacher Salary, or Expense)
     * This centralizes all financial flows to ensure consistency.
     *
     * @param array $data {
     *   type: 'student_payment'|'teacher_salary'|'expense'|'capital',
     *   amount: float,
     *   payment_method: string,
     *   notes: string,
     *   target_id: int (Invoice ID, Salary ID, etc.),
     *   date: string (optional)
     * }
     * @return mixed
     * @throws \Throwable
     */
    public function recordUnifiedTransaction(array $data)
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $amount = floatval($data['amount']);
            $method = $data['payment_method'] ?? 'cash';
            $notes = $data['notes'] ?? '';
            $date = $data['date'] ?? now()->toDateTimeString();

            switch ($type) {
                case 'student_payment':
                    $invoiceId = $data['target_id'] ?? null;
                    
                    if (!$invoiceId && isset($data['student_id'], $data['group_id'])) {
                        $invoice = \App\Models\Invoice::firstOrCreate(
                            ['student_id' => $data['student_id'], 'group_id' => $data['group_id']],
                            [
                                'amount' => \App\Models\Group::find($data['group_id'])->price ?? 0,
                                'discount_amount' => 0,
                                'final_amount' => \App\Models\Group::find($data['group_id'])->price ?? 0,
                                'amount_paid' => 0,
                                'status' => 'unpaid',
                                'due_date' => now()->addDays(7),
                                'description' => 'Automatic Invoice'
                            ]
                        );
                        $invoiceId = $invoice->invoice_id;
                    }

                    if (!$invoiceId) throw new \InvalidArgumentException("Invoice ID required.");

                    return $this->recordPayment($invoiceId, [
                        'amount' => $amount,
                        'payment_method' => $method,
                        'notes' => $notes
                    ]);

                case 'teacher_salary':
                    $salary = \App\Models\Salary::find($data['target_id']);
                    if (!$salary) throw new \InvalidArgumentException("Salary record not found.");

                    $payment = \App\Models\TeacherPayment::create([
                        'teacher_id'     => $salary->teacher_id,
                        'salary_id'      => $salary->salary_id,
                        'amount'         => $amount,
                        'payment_method' => $method,
                        'notes'          => $notes,
                        'payment_date'   => $date,
                        'confirmed_by'   => Auth::id(),
                    ]);
                    
                    $paid = \App\Models\TeacherPayment::where('salary_id', $salary->salary_id)->sum('amount');
                    $status = ($paid >= $salary->net_salary) ? 'paid' : 'partial';
                    $salary->update(['status' => $status]);

                    return $payment;

                case 'expense':
                    return \App\Models\Expense::create([
                        'category'       => $data['category'] ?? 'General',
                        'amount'         => $amount,
                        'payment_method' => $method,
                        'description'    => $notes,
                        'expense_date'   => $date,
                        'recorded_by'    => Auth::id(),
                        'is_approved'    => 1
                    ]);

                default:
                    throw new \InvalidArgumentException("Unknown transaction type: {$type}");
            }
        });
    }
}
