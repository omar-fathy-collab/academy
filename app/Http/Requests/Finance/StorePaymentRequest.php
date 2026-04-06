<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StorePaymentRequest
 *
 * Validates payment creation data. The amount ceiling is computed
 * dynamically in the controller / service layer once the invoice is
 * locked — this request only validates structure and types.
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isSecretary();
    }

    public function rules(): array
    {
        return [
            'invoice_id'     => ['required', 'integer', 'exists:invoices,invoice_id'],
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,vodafone_cash,instapay,check,other'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'receipt_image'  => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096', // 4MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.exists'      => 'الفاتورة المحددة غير موجودة.',
            'payment_method.in'      => 'طريقة الدفع غير صالحة.',
            'receipt_image.mimes'    => 'يجب أن يكون الإيصال صورة (JPG/PNG) أو ملف PDF.',
            'receipt_image.max'      => 'حجم الإيصال يجب ألا يتجاوز 4 ميغابايت.',
        ];
    }
}
