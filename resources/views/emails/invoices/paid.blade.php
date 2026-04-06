<x-mail::message>
# Payment Received

Dear {{ $invoice->student ? $invoice->student->student_name : 'Valued Student' }},

We have successfully received a payment of **{{ number_format($invoice->amount_paid, 2) }} EGP** for your invoice **#{{ $invoice->invoice_number }}**.

**Invoice Details:**
- Description: {{ $invoice->description }}
- Status: {{ ucfirst($invoice->status) }}
- Remaining Amount: {{ number_format($invoice->balance_due, 2) }} EGP

<x-mail::button :url="route('invoices.public.show', $invoice->public_token)">
View Invoice Details
</x-mail::button>

If you have any questions, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
