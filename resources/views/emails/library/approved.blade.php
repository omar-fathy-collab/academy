<x-mail::message>
# Purchase Approved

Dear {{ $paymentRequest->user->name }},

We are pleased to inform you that your purchase request for **{{ $paymentRequest->item?->title }}** has been approved!

You can now access this item in your library.

<x-mail::button :url="route('student.library')">
Go to My Library
</x-mail::button>

If you have any issues accessing the content, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
