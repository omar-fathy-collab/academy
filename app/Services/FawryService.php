<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FawryService
{
    protected $merchantGuid;
    protected $merchantCode;
    protected $securityKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->merchantCode = config('services.fawry.merchant_code');
        $this->securityKey = config('services.fawry.security_key');
        $this->baseUrl = config('services.fawry.debug') 
            ? 'https://atfawry.fawrystaging.com/fawrypay-api/api/payments' 
            : 'https://www.atfawry.com/fawrypay-api/api/payments';
    }

    /**
     * Generate Payment URL for Fawry
     */
    public function generatePaymentUrl($enrollmentRequest)
    {
        $merchantCode = $this->merchantCode;
        $merchantRefNum = $enrollmentRequest->id;
        $customerMobile = $enrollmentRequest->user->phone ?? '01000000000';
        $customerEmail = $enrollmentRequest->user->email;
        $customerName = $enrollmentRequest->user->username;
        $amount = number_format($enrollmentRequest->amount, 2, '.', '');
        $securityKey = $this->securityKey;

        // Fawry Signature for V2
        $signature = hash('sha256', $merchantCode . $merchantRefNum . $enrollmentRequest->user->id . $amount . $securityKey);

        $payload = [
            'merchantCode' => $merchantCode,
            'merchantRefNum' => $merchantRefNum,
            'customerMobile' => $customerMobile,
            'customerEmail' => $customerEmail,
            'customerName' => $customerName,
            'chargeItems' => [
                [
                    'itemId' => $enrollmentRequest->course_id,
                    'description' => $enrollmentRequest->course->course_name,
                    'price' => $amount,
                    'quantity' => 1
                ]
            ],
            'returnUrl' => route('fawry.callback'),
            'signature' => $signature,
            'amount' => $amount,
            'currencyCode' => 'EGP',
            'language' => 'ar-eg',
        ];

        // For now, return a staging/mock URL or just the payload for debugging
        return $payload;
    }

    /**
     * Verify Callback Signature
     */
    public function verifySignature($data)
    {
        // Implement Fawry callback signature verification logic
        return true; 
    }
}
