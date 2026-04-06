<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Salary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    public function getTransactionDetail(string $type, int $id): ?array
    {
        try {
            $model = match ($type) {
                'payment' => Payment::with(['invoice.student.user'])->find($id),
                'expense' => Expense::with('creator')->find($id),
                'salary' => Salary::with(['teacher', 'group'])->find($id),
                default => null
            };

            if (! $model) {
                return null;
            }

            return $this->normalizeTransaction($model, $type);

        } catch (\Exception $e) {
            Log::error('Transaction detail error', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateTransactionStatus(string $type, int $id, string $status): array
    {
        DB::beginTransaction();

        try {
            $model = match ($type) {
                'payment' => Payment::find($id),
                'expense' => Expense::find($id),
                'salary' => Salary::find($id),
                default => null
            };

            if (! $model) {
                throw new \Exception('Transaction not found');
            }

            $oldStatus = $model->status;
            $model->status = $status;
            $model->save();

            DB::commit();

            return [
                'success' => true,
                'model' => $model,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'updated_at' => now(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Transaction status update error', [
                'type' => $type,
                'id' => $id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getTotalCount(): int
    {
        return Payment::count() + Expense::count() + Salary::count();
    }

    private function normalizeTransaction($model, string $type): array
    {
        $baseData = [
            'id' => $model->id,
            'type' => $type,
            'amount' => $model->amount ?? $model->net_salary ?? 0,
            'status' => $model->status ?? 'pending',
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String(),
            'notes' => $model->notes ?? $model->description ?? null,
        ];

        switch ($type) {
            case 'payment':
                return array_merge($baseData, [
                    'payment_method' => $model->payment_method,
                    'reference' => $model->payment_reference,
                    'date' => $model->payment_date?->toDateString(),
                    'student' => $model->invoice->student->user->name ?? null,
                    'course' => $model->invoice->group->course->course_name ?? null,
                ]);

            case 'expense':
                return array_merge($baseData, [
                    'category' => $model->category,
                    'description' => $model->description,
                    'date' => $model->expense_date?->toDateString(),
                    'approved' => $model->is_approved,
                    'approved_by' => $model->approved_by ?? null,
                ]);

            case 'salary':
                return array_merge($baseData, [
                    'teacher' => $model->teacher->teacher_name ?? null,
                    'group' => $model->group->group_name ?? null,
                    'payment_date' => $model->payment_date?->toDateString(),
                    'net_salary' => $model->net_salary,
                    'basic_salary' => $model->basic_salary,
                    'bonuses' => $model->bonuses,
                    'deductions' => $model->deductions,
                ]);

            default:
                return $baseData;
        }
    }
}
