<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\FinancialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FinancialController extends Controller
{
    protected $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * Handle unified transaction recording.
     */
    public function recordTransaction(Request $request)
    {
        $v = $request->validate([
            'type'            => 'required|in:student_payment,teacher_salary,expense,capital',
            'amount'          => 'required|numeric|min:0.01',
            'payment_method'  => 'required|string',
            'target_id'       => 'nullable|numeric',
            'student_id'      => 'nullable|numeric',
            'group_id'        => 'nullable|numeric',
            'category'        => 'nullable|string',
            'notes'           => 'nullable|string',
            'date'            => 'nullable|date',
        ]);

        try {
            $transaction = $this->financialService->recordUnifiedTransaction($v);

            return response()->json([
                'success' => true,
                'message' => 'Transaction recorded successfully!',
                'data'    => $transaction
            ]);

        } catch (\Exception $e) {
            Log::error('Error recording unified transaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to record transaction: ' . $e->getMessage()
            ], 422);
        }
    }
}
