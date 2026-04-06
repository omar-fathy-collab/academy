<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    public function index()
    {
        $search = request()->input('search');

        $query = Activity::query()->with(['user', 'user.profile']);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'LIKE', "%{$search}%")
                    ->orWhere('subject_id', 'LIKE', "%{$search}%")
                    ->orWhere('id', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('username', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%")
                            ->orWhereHas('profile', function ($q) use ($search) {
                                $q->where('nickname', 'LIKE', "%{$search}%");
                            });
                    });
            });
        }

        $activities = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('activities.index', [
            'activities' => $activities,
            'filters' => ['search' => $search]
        ]);
    }

    public function search(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $page = $request->input('page', 1);

            $query = Activity::query()->with(['user', 'user.profile']);

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'LIKE', "%{$search}%")
                        ->orWhere('subject_id', 'LIKE', "%{$search}%")
                        ->orWhere('id', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('username', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%")
                                ->orWhereHas('profile', function ($q) use ($search) {
                                    $q->where('nickname', 'LIKE', "%{$search}%");
                                });
                        });
                });
            }

            $activities = $query->orderBy('created_at', 'desc')->paginate(15, ['*'], 'page', $page);

            return response()->json([
                'error' => false,
                'activities' => $activities->items(),
                'pagination' => [
                    'total' => $activities->total(),
                    'per_page' => $activities->perPage(),
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Activity search error: '.$e->getMessage());

            return response()->json([
                'error' => true,
                'message' => 'Search failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function details($id)
    {
        $activity = Activity::with(['user', 'user.profile'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'activity' => $activity
        ]);
    }

    public function rollback($id)
    {
        try {
            $activity = Activity::findOrFail($id);
            $subjectType = $activity->subject_type;
            $subjectId = $activity->subject_id;
            $oldData = $activity->old_data;

            if (empty($oldData) && $activity->action !== 'created') {
                return response()->json([
                    'success' => false,
                    'message' => 'No historical data found for this action.'
                ], 422);
            }

            if (! class_exists($subjectType)) {
                return response()->json([
                    'success' => false,
                    'message' => "Model class {$subjectType} not found."
                ], 422);
            }

            $model = $subjectType::find($subjectId);

            switch ($activity->action) {
                case 'updated':
                    if ($model) {
                        $model->update($oldData);
                    } else {
                        return response()->json(['success' => false, 'message' => 'Subject model not found for update rollback.'], 404);
                    }
                    break;

                case 'created':
                    if ($model) {
                        $model->delete();
                    } else {
                        return response()->json(['success' => false, 'message' => 'Subject model already deleted.'], 404);
                    }
                    break;

                case 'deleted':
                    if (! $model) {
                        $model = new $subjectType();
                        foreach ($oldData as $key => $value) {
                            $model->{$key} = $value;
                        }
                        $model->save();
                    } else {
                        return response()->json(['success' => false, 'message' => 'Subject model already exists.'], 422);
                    }
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Rollback not supported for this action type.'], 422);
            }

            // Create a record of the rollback itself
            Activity::create([
                'user_id' => auth()->id(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action' => 'rollback',
                'description' => "Rolled back action #{$id} ({$activity->action})",
                'changes' => [
                    'before' => $activity->new_data,
                    'after' => $activity->old_data
                ],
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return redirect()->back()->with('success', 'Action successfully rolled back.');

        } catch (\Exception $e) {
            Log::error("Rollback error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
