<?php

namespace App\DTOs;

use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportFilterDTO
{
    public string $type;

    public ?string $date = null;

    public ?int $week = null;

    public ?int $month = null;

    public ?int $year = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $search = null;

    public ?string $status = null;

    public ?string $paymentMethod = null;

    public int $page = 1;

    public int $perPage = 20;

    public ?string $sortBy = null;

    public ?string $sortOrder = null;

    public static function createFromRequest(Request $request, string $type): self
    {
        $dto = new self;
        $dto->type = $type;

        switch ($type) {
            case 'daily':
                $dto->date = $request->get('date', now()->format('Y-m-d'));
                break;

            case 'weekly':
                $dto->week = (int) $request->get('week', now()->weekOfYear);
                $dto->year = (int) $request->get('year', now()->year);
                break;

            case 'monthly':
                $dto->month = (int) $request->get('month', now()->month);
                $dto->year = (int) $request->get('year', now()->year);
                break;

            case 'annual':
                $dto->year = (int) $request->get('year', now()->year);
                break;

            case 'overall':
                // No specific date filters for overall report
                break;
        }

        $dto->search = $request->get('search');
        $dto->status = $request->get('status');
        $dto->paymentMethod = $request->get('payment_method');
        $dto->page = max(1, (int) $request->get('page', 1));
        $dto->perPage = min(100, max(10, (int) $request->get('per_page', 20)));
        $dto->sortBy = $request->get('sort_by');
        $dto->sortOrder = $request->get('sort_order', 'desc');

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'date' => $this->date,
            'week' => $this->week,
            'month' => $this->month,
            'year' => $this->year,
            'search' => $this->search,
            'status' => $this->status,
            'payment_method' => $this->paymentMethod,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort_by' => $this->sortBy,
            'sort_order' => $this->sortOrder,
        ];
    }

    public function getStartDate(): ?Carbon
    {
        return match ($this->type) {
            'daily' => $this->date ? Carbon::parse($this->date)->startOfDay() : null,
            'weekly' => Carbon::now()->setISODate($this->year, $this->week)->startOfWeek(),
            'monthly' => Carbon::create($this->year, $this->month, 1)->startOfMonth(),
            'annual' => Carbon::create($this->year, 1, 1)->startOfYear(),
            default => null
        };
    }

    public function getEndDate(): ?Carbon
    {
        return match ($this->type) {
            'daily' => $this->date ? Carbon::parse($this->date)->endOfDay() : null,
            'weekly' => Carbon::now()->setISODate($this->year, $this->week)->endOfWeek(),
            'monthly' => Carbon::create($this->year, $this->month, 1)->endOfMonth(),
            'annual' => Carbon::create($this->year, 12, 31)->endOfYear(),
            default => null
        };
    }
}
