<?php

namespace App\Repositories;

use App\Models\Group;

class GroupRepository extends BaseRepository
{
    public function __construct(Group $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all active groups with their teacher and course.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function activeWithDetails()
    {
        return $this->model->newQuery()
            ->with(['teacher.user.profile', 'course', 'subcourse'])
            ->where('status', 'active')
            ->orderBy('group_name')
            ->get();
    }

    /**
     * Get groups taught by a specific teacher.
     *
     * @param  int  $teacherId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function forTeacher(int $teacherId)
    {
        return $this->model->newQuery()
            ->with(['course', 'subcourse', 'sessions'])
            ->where('teacher_id', $teacherId)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Retrieve a group with all enrolled students (no N+1).
     *
     * @param  mixed  $groupId
     * @return \App\Models\Group
     */
    public function withStudents($groupId)
    {
        return $this->model
            ->with(['studentGroups.student.user.profile', 'teacher.user', 'course', 'sessions'])
            ->findOrFail($groupId);
    }

    /**
     * Paginated admin view of groups.
     *
     * @param  int  $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginatedForAdmin(int $perPage = 20)
    {
        return $this->model
            ->with(['teacher.user', 'course', 'subcourse'])
            ->withCount('studentGroups')
            ->orderBy('group_id', 'desc')
            ->paginate($perPage);
    }
}
