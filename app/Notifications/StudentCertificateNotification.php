<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Str;

class StudentCertificateNotification extends BaseNotification
{
    use Queueable;

    public $certificate;

    /**
     * Create a new notification instance.
     */
    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Get the notification's delivery channels.
     * We'll use database by default and mail if the user has an email.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $cert = $this->certificate->load(['course', 'group']);

        $url = url('/certificates/'.$this->certificate->id.'/download');

        $isBadge = Str::startsWith($this->certificate->certificate_number, 'BADGE-');

        $subject = $isBadge ? 'تم منحك بادج جديد' : 'تم إصدار شهادتك';
        $intro = $isBadge ? 'لقد مُنحت بادج جديد من قبل مدرسك.' : 'تم إصدار شهادة جديدة لك.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('مرحبًا '.$notifiable->name)
            ->line($intro)
            ->line('رقم الشهادة: '.$this->certificate->certificate_number)
            ->action('عرض الشهادة', $url)
            ->line('شكرًا لاستخدامك منصتنا.');
    }

    /**
     * Get the array / database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        $isBadge = Str::startsWith($this->certificate->certificate_number, 'BADGE-');

        return [
            'certificate_id' => $this->certificate->id,
            'certificate_number' => $this->certificate->certificate_number,
            'course' => $this->certificate->course ? $this->certificate->course->title : null,
            'group' => $this->certificate->group ? ($this->certificate->group->title ?? null) : null,
            'type' => $isBadge ? 'badge' : 'certificate',
            'message' => $isBadge ? 'تم منحك بادج جديد' : 'تم إصدار شهادة جديدة',
            'issued_by' => $this->certificate->issued_by,
        ];
    }
}
