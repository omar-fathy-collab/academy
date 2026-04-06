<?php

namespace App\Observers;

use App\Models\Certificate;
use App\Notifications\StudentCertificateNotification;

class CertificateObserver
{
    /**
     * Handle the Certificate "created" event.
     */
    public function created(Certificate $certificate)
    {
        // Only notify when certificate is already issued
        if ($certificate->status !== 'issued') {
            return;
        }

        $certificate->load(['user']);

        if ($certificate->user) {
            try {
                $certificate->user->notify(new StudentCertificateNotification($certificate));
            } catch (\Exception $e) {
                \Log::error('Failed to send certificate notification for certificate '.$certificate->id.': '.$e->getMessage());

                // Fallback: insert into the legacy notifications table used by the app
                try {
                    \DB::table('notifications')->insert([
                        'user_id' => $certificate->user->id,
                        'title' => 'تم إصدار شهادة جديدة',
                        'message' => 'تم إصدار شهادة جديدة برقم: '.($certificate->certificate_number ?? ''),
                        'type' => 'certificate',
                        'related_id' => $certificate->id,
                        'is_read' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $ex) {
                    \Log::error('Failed to persist fallback notification for certificate '.$certificate->id.': '.$ex->getMessage());
                }
            }
        }
    }

    /**
     * Handle the Certificate "updated" event.
     */
    public function updated(Certificate $certificate)
    {
        // If status transitioned to 'issued', notify
        $original = $certificate->getOriginal('status');
        $current = $certificate->status;

        if ($original !== 'issued' && $current === 'issued') {
            $certificate->load(['user']);

            if ($certificate->user) {
                try {
                    $certificate->user->notify(new StudentCertificateNotification($certificate));
                } catch (\Exception $e) {
                    \Log::error('Failed to send certificate notification on status change for certificate '.$certificate->id.': '.$e->getMessage());
                }
            }
        }
    }
}
