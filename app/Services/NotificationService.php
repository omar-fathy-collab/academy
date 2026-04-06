<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoicePaidMail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendPaymentConfirmation(Invoice $invoice)
    {
        $this->sendEmail($invoice);
        // We'll keep the automated WhatsApp sending if the API is configured,
        // but the main interactive flow is now via getWhatsAppUrl()
        $this->sendWhatsApp($invoice);
    }

    /**
     * Generate interactive WhatsApp URL for manual sharing/opening
     */
    public function getWhatsAppUrl(Invoice $invoice)
    {
        $phoneNumber = $invoice->student?->user?->profile?->phone_number ?? $invoice->student?->phone_number;
        if (!$phoneNumber) return null;

        // Strip non-numeric characters for wa.me
        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        $message = $this->formatWhatsAppMessage($invoice);
        
        return "https://wa.me/{$digits}?text=" . urlencode($message);
    }

    /**
     * Generate interactive mailto URL for manual email sending
     */
    public function getEmailUrl(Invoice $invoice)
    {
        $user = $invoice->student?->user;
        if (!$user || !$user->email) return null;

        $subject = rawurlencode('Payment Confirmation: ' . $invoice->invoice_number);
        $body = rawurlencode($this->formatEmailMessage($invoice));
        
        return "mailto:{$user->email}?subject={$subject}&body={$body}";
    }

    protected function formatEmailMessage(Invoice $invoice)
    {
        $status = $invoice->status === 'paid' ? 'has been fully paid' : 'payment received';
        $amount = number_format($invoice->amount_paid, 2);
        $link = route('invoices.public.show', $invoice->public_token);
        $name = $invoice->student ? $invoice->student->student_name : 'Valued Student';

        return "Dear {$name},\n\n" .
               "We have successfully received a payment of {$amount} EGP for your invoice #{$invoice->invoice_number}.\n" .
               "Invoice Status: {$status}.\n\n" .
               "You can view your full invoice details here:\n{$link}\n\n" .
               "Thank you for your trust in " . config('app.name') . "!";
    }

    /**
     * Send Invoice Email (Legacy - keeping for internal use if needed)
     */
    public function sendEmail(Invoice $invoice) { return false; }

    /**
     * Send Invoice WhatsApp
     */
    public function sendWhatsApp(Invoice $invoice)
    {
        try {
            $student = $invoice->student;
            $phoneNumber = $student ? ($student->phone_number ?? $student->user?->phone_number) : null;

            if (!$phoneNumber) return false;

            $message = $this->formatWhatsAppMessage($invoice);
            
            $apiUrl = config('services.whatsapp.url');
            $apiToken = config('services.whatsapp.token');

            if ($apiUrl && $apiToken) {
                Http::post($apiUrl, [
                    'token' => $apiToken,
                    'to' => $phoneNumber,
                    'body' => $message,
                ]);
                return true;
            } else {
                Log::warning("WhatsApp API not configured. Message for {$phoneNumber} would have been: " . $message);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp message: " . $e->getMessage());
        }
        return false;
    }

    protected function formatWhatsAppMessage(Invoice $invoice)
    {
        $status = $invoice->status === 'paid' ? 'تم دفع فاتورتك بالكامل' : 'تم استلام دفعة من فاتورتك';
        $amount = number_format($invoice->amount_paid, 2);
        // Ensure relative URL if needed, but route() is usually absolute
        $link = route('invoices.public.show', $invoice->public_token);

        $name = $invoice->student ? $invoice->student->student_name : 'عميلنا العزيز';

        return "مرحباً {$name}،\n\n" .
               "{$status} بنجاح.\n" .
               "رقم الفاتورة: {$invoice->invoice_number}\n" .
               "المبلغ المستلم: {$amount} ج.م\n" .
               "الوصف: {$invoice->description}\n\n" .
               "يمكنك عرض تفاصيل الفاتورة والتحميل من هنا:\n{$link}\n\n" .
               "شكراً لتعاملك معنا!";
    }

    /**
     * Send library purchase approval notification
     */
    public function sendLibraryApprovalNotification(\App\Models\LibraryPaymentRequest $request)
    {
        $this->sendLibraryEmail($request);
        $this->sendLibraryWhatsApp($request);
    }

    public function sendLibraryEmail(\App\Models\LibraryPaymentRequest $request)
    {
        try {
            $user = $request->user;
            if ($user && $user->email) {
                Mail::to($user->email)->send(new \App\Mail\LibraryPurchaseApprovedMail($request));
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Failed to send library email: " . $e->getMessage());
        }
        return false;
    }

    public function sendLibraryWhatsApp(\App\Models\LibraryPaymentRequest $request)
    {
        try {
            $user = $request->user;
            $phoneNumber = $user->phone_number ?? $user->student?->phone_number;

            if (!$phoneNumber) return false;

            $message = $this->formatLibraryWhatsAppMessage($request);
            
            $apiUrl = config('services.whatsapp.url');
            $apiToken = config('services.whatsapp.token');

            if ($apiUrl && $apiToken) {
                Http::post($apiUrl, [
                    'token' => $apiToken,
                    'to' => $phoneNumber,
                    'body' => $message,
                ]);
                return true;
            } else {
                Log::warning("WhatsApp API not configured. Message for {$phoneNumber} would have been: " . $message);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send Library WhatsApp message: " . $e->getMessage());
        }
        return false;
    }

    protected function formatLibraryWhatsAppMessage(\App\Models\LibraryPaymentRequest $request)
    {
        $itemName = $request->item?->title ?? 'العنصر';
        $type = $request->item_type === 'video' ? 'فيديو' : 'كتاب';
        
        return "مرحباً {$request->user->name}،\n\n" .
               "تمت الموافقة على طلب الشراء الخاص بك لـ {$type}: *{$itemName}*.\n" .
               "يمكنك الآن الوصول إليه في مكتبتك على المنصة.\n\n" .
               "رابط المكتبة:\n" . route('student.library') . "\n\n" .
               "تمنياتنا لك بالتوفيق!";
    }

    /**
     * SALARY NOTIFICATIONS
     */

    public function getSalaryWhatsAppUrl(\App\Models\Salary $salary)
    {
        $teacher = $salary->teacher;
        $profile = $teacher?->user?->profile;
        $phoneNumber = $profile?->phone_number;

        if (!$phoneNumber) return null;

        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);
        $message = $this->formatSalaryWhatsAppMessage($salary);
        
        return "https://wa.me/{$digits}?text=" . urlencode($message);
    }

    public function getSalaryEmailUrl(\App\Models\Salary $salary)
    {
        $user = $salary->teacher?->user;
        if (!$user || !$user->email) return null;

        $subject = rawurlencode('Salary Payment Confirmation - ' . $salary->month);
        $body = rawurlencode($this->formatSalaryEmailMessage($salary));
        
        return "mailto:{$user->email}?subject={$subject}&body={$body}";
    }

    protected function formatSalaryWhatsAppMessage(\App\Models\Salary $salary)
    {
        $teacherName = $salary->teacher ? $salary->teacher->teacher_name : 'المعلم العزيز';
        $month = $salary->month;
        $paidAmount = number_format($salary->paid_amount ?? 0, 2);
        $link = route('salaries.public_slip', $salary->public_token);

        return "مرحباً {$teacherName}،\n\n" .
               "تم تحويل راتب شهر {$month} بنجاح.\n" .
               "المبلغ المستلم: {$paidAmount} ج.م\n\n" .
               "يمكنك عرض وتحميل قسيمة الراتب من هنا:\n{$link}\n\n" .
               "شكراً لمجهوداتك مع " . config('app.name') . "!";
    }

    protected function formatSalaryEmailMessage(\App\Models\Salary $salary)
    {
        $teacherName = $salary->teacher ? $salary->teacher->teacher_name : 'Teacher';
        $month = $salary->month;
        $paidAmount = number_format($salary->paid_amount ?? 0, 2);
        $link = route('salaries.public_slip', $salary->public_token);

        return "Dear {$teacherName},\n\n" .
               "We are pleased to inform you that your salary for {$month} has been processed.\n" .
               "Amount Received: {$paidAmount} EGP.\n\n" .
               "You can view and download your salary slip here:\n{$link}\n\n" .
               "Thank you for your valuable contribution to " . config('app.name') . "!";
    }
}
