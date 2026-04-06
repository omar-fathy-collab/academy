<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreInvoiceRequest
 *
 * Enforces strict validation for creating a new invoice.
 * Prevents mass assignment and ensures all financial data is
 * properly typed before reaching the controller.
 */
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admins and secretaries can create invoices
        return $this->user()->isAdmin() || $this->user()->isSecretary();
    }

    public function rules(): array
    {
        return [
            'student_id'       => ['required', 'integer', 'exists:students,student_id'],
            'group_id'         => ['nullable', 'integer', 'exists:groups,group_id'],
            'amount'           => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount'  => ['nullable', 'numeric', 'min:0'],
            'due_date'         => $this->isMethod('POST') 
                                    ? ['required', 'date', 'after_or_equal:today'] 
                                    : ['required', 'date'],
            'description'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => 'الطالب المحدد غير موجود في النظام.',
            'group_id.exists'   => 'المجموعة المحددة غير موجودة في النظام.',
            'amount.min'        => 'يجب أن يكون المبلغ أكبر من صفر.',
            'due_date.after_or_equal' => 'تاريخ الاستحقاق يجب ألا يكون في الماضي.',
        ];
    }
}
