<?php

namespace App\Observers;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityObserver
{
    protected function record(Model $model, string $action, array $extra = []): void
    {
        // Check global setting: Is action monitoring enabled?
        if (setting('enable_action_monitoring', '1') !== '1') {
            return;
        }

        // Avoid infinite loop (don’t log the Activity model itself)
        if ($model instanceof Activity) {
            return;
        }

        $modelName = class_basename(get_class($model));
        $description = $extra['description'] ?? "{$action} {$modelName} #{$model->getKey()}";

        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'action' => $action,
            'description' => $description,
            'changes' => $extra['changes'] ?? null,
            'extra' => $extra['extra'] ?? null,
            'ip' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'created_at' => now(),
        ]);
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created', [
            'changes' => [
                'before' => [],
                'after' => $model->getAttributes(),
            ],
        ]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at'], $changes['created_at']);

        if (! empty($changes)) {
            $original = array_intersect_key($model->getOriginal(), $changes);
            $this->record($model, 'updated', [
                'changes' => ['before' => $original, 'after' => $changes],
            ]);
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', [
            'changes' => [
                'before' => $model->getAttributes(),
                'after' => [],
            ],
            'extra' => ['attributes' => $model->getAttributes()],
        ]);
    }
}
