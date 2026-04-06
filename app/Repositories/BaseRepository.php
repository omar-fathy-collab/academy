<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * The model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected Model $model;

    /**
     * Inject a concrete Eloquent model.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /** {@inheritdoc} */
    public function all(array $with = [])
    {
        return $this->model->with($with)->get();
    }

    /** {@inheritdoc} */
    public function findOrFail($id, array $with = [])
    {
        return $this->model->with($with)->findOrFail($id);
    }

    /** {@inheritdoc} */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /** {@inheritdoc} */
    public function update($id, array $data)
    {
        $record = $this->findOrFail($id);
        $record->update($data);

        return $record->fresh();
    }

    /** {@inheritdoc} */
    public function delete($id)
    {
        $record = $this->findOrFail($id);

        return $record->delete();
    }

    /** {@inheritdoc} */
    public function paginate(int $perPage = 15, array $with = [])
    {
        return $this->model->with($with)->paginate($perPage);
    }
}
