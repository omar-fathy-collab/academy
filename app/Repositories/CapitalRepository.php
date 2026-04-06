<?php

namespace App\Repositories;

use App\Models\Capital;

class CapitalRepository
{
    protected $model;

    public function __construct(Capital $capital)
    {
        $this->model = $capital;
    }

    /**
     * Get all capital transactions
     */
    public function all()
    {
        return $this->model->orderBy('created_at', 'desc')->get();
    }

    /**
     * Find a capital transaction by ID
     */
    public function find($id)
    {
        return $this->model->find($id);
    }

    /**
     * Create a new capital transaction
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update a capital transaction
     */
    public function update($id, array $data)
    {
        $capital = $this->find($id);
        if ($capital) {
            $capital->update($data);
        }

        return $capital;
    }

    /**
     * Delete a capital transaction
     */
    public function delete($id)
    {
        $capital = $this->find($id);
        if ($capital) {
            return $capital->delete();
        }

        return false;
    }

    /**
     * Get capital transactions with filters
     */
    public function getWithFilters(array $filters = [])
    {
        $query = $this->model->query();

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->orderBy('transaction_date', 'desc')->get();
    }

    /**
     * Get capital summary
     */
    public function getSummary(array $filters = [])
    {
        $query = $this->model->query();

        if (! empty($filters['from_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['to_date']);
        }

        return [
            'total_additions' => (clone $query)->where('type', 'addition')->sum('amount'),
            'total_withdrawals' => (clone $query)->where('type', 'withdrawal')->sum('amount'),
            'net_capital' => (clone $query)->where('type', 'addition')->sum('amount') -
                            (clone $query)->where('type', 'withdrawal')->sum('amount'),
            'count' => $query->count(),
            'transactions' => $query->get(),
        ];
    }

    /**
     * Get total capital balance
     */
    public function getTotalBalance()
    {
        $additions = $this->model->where('type', 'addition')->sum('amount');
        $withdrawals = $this->model->where('type', 'withdrawal')->sum('amount');

        return $additions - $withdrawals;
    }

    /**
     * Get recent capital transactions
     */
    public function getRecent($limit = 10)
    {
        return $this->model->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Count capital transactions
     */
    public function count()
    {
        return $this->model->count();
    }

    /**
     * Check if a capital transaction exists
     */
    public function exists($id)
    {
        return $this->model->where('id', $id)->exists();
    }
}
