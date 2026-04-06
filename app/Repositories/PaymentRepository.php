<?php

namespace App\Repositories;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentRepository
{
    public function getBetweenDates(Carbon $startDate, Carbon $endDate): Collection
    {
        return Payment::with(['invoice.student.user', 'invoice.group.course'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    public function sumBetweenDates(Carbon $startDate, Carbon $endDate): float
    {
        return Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount') ?? 0;
    }

    public function countBetweenDates(Carbon $startDate, Carbon $endDate): int
    {
        return Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->count();
    }

    public function getWithInvoiceDetails(Carbon $startDate, Carbon $endDate): Collection
    {
        return Payment::with(['invoice.group.course'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->get();
    }

    public function getTotalSum(): float
    {
        return Payment::sum('amount') ?? 0;
    }

    public function getTotalCount(): int
    {
        return Payment::count();
    }

    public function getAverageAmount(): float
    {
        $count = $this->getTotalCount();

        return $count > 0 ? $this->getTotalSum() / $count : 0;
    }

    public function getLatestDate(): ?string
    {
        return Payment::max('payment_date');
    }

    public function getEarliestDate(): ?string
    {
        return Payment::min('payment_date');
    }

    public function sumForDate(Carbon $date): float
    {
        return Payment::whereDate('payment_date', $date)
            ->sum('amount') ?? 0;
    }

    public function sumForWeek(int $week, int $year): float
    {
        $startDate = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        return $this->sumBetweenDates($startDate, $endDate);
    }

    public function getByPaymentMethod(Carbon $startDate, Carbon $endDate): Collection
    {
        return Payment::selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->groupBy('payment_method')
            ->orderBy('total', 'desc')
            ->get();
    }
}
