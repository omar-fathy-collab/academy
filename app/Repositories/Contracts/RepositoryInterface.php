<?php

namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    /**
     * Get all records, optionally with eager-loaded relationships.
     *
     * @param  array  $with
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all(array $with = []);

    /**
     * Find a record by primary key and throw 404 if not found.
     *
     * @param  mixed  $id
     * @param  array  $with
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function findOrFail($id, array $with = []);

    /**
     * Create a new record.
     *
     * @param  array  $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $data);

    /**
     * Update an existing record.
     *
     * @param  mixed  $id
     * @param  array  $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function update($id, array $data);

    /**
     * Soft-delete a record.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function delete($id);

    /**
     * Return a paginated list.
     *
     * @param  int  $perPage
     * @param  array  $with
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $with = []);
}
