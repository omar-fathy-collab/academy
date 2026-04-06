<?php

namespace App\Repositories;

use App\Models\Invoice;

class InvoiceRepository extends BaseRepository
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all invoices for a student with eager-loaded relations.
     *
     * @param  int  $studentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function forStudent(int $studentId)
    {
        return $this->model->newQuery()
            ->with(['student', 'group', 'payments'])
            ->where('student_id', $studentId)
            ->orderBy('invoice_id', 'desc')
            ->get();
    }

    /**
     * Get all unpaid / partial invoices.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function unpaid()
    {
        return $this->model->newQuery()
            ->with(['student', 'group', 'payments'])
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get paginated invoices for admin overview without N+1.
     *
     * @param  int  $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginatedWithDetails(int $perPage = 20)
    {
        return $this->model
            ->with(['student.user', 'group.course', 'payments'])
            ->orderBy('invoice_id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find invoice by its number.
     *
     * @param  string  $invoiceNumber
     * @return \App\Models\Invoice|null
     */
    public function findByNumber(string $invoiceNumber)
    {
        return $this->model->newQuery()
            ->with(['student', 'group', 'payments'])
            ->where('invoice_number', $invoiceNumber)
            ->first();
    }
}
