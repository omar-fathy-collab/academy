<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class ValidationService
{
    public function validateReportRequest($request, string $type): \Illuminate\Validation\Validator
    {
        $rules = [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:paid,pending,cancelled',
            'payment_method' => 'nullable|in:cash,card,bank,transfer',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:10|max:100',
            'sort_by' => 'nullable|in:date,amount,reference',
            'sort_order' => 'nullable|in:asc,desc',
        ];

        switch ($type) {
            case 'daily':
                $rules['date'] = 'required|date_format:Y-m-d';
                break;

            case 'weekly':
                $rules['week'] = 'required|integer|min:1|max:53';
                $rules['year'] = 'required|integer|min:2020|max:'.(date('Y') + 1);
                break;

            case 'monthly':
                $rules['month'] = 'required|integer|min:1|max:12';
                $rules['year'] = 'required|integer|min:2020|max:'.(date('Y') + 1);
                break;

            case 'annual':
                $rules['year'] = 'required|integer|min:2020|max:'.(date('Y') + 1);
                break;
        }

        return Validator::make($request->all(), $rules);
    }

    public function validateTransactionDetailRequest($request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'type' => 'required|in:payment,expense,salary',
            'id' => 'required|integer',
        ]);
    }

    public function validateTransactionStatusRequest($request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'type' => 'required|in:payment,expense,salary',
            'id' => 'required|integer',
            'status' => 'required|in:paid,pending,cancelled',
        ]);
    }

    public function validateExportRequest($request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'type' => 'required|in:daily,weekly,monthly,annual,overall',
            'date' => 'required_if:type,daily|date_format:Y-m-d',
            'week' => 'required_if:type,weekly|integer|min:1|max:53',
            'month' => 'required_if:type,monthly|integer|min:1|max:12',
            'year' => 'required_if:type,annual|integer|min:2020|max:'.(date('Y') + 1),
            'format' => 'nullable|in:excel,pdf,csv',
        ]);
    }
}
