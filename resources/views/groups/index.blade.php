@extends('layouts.authenticated')

@section('title', 'Academy Groups')

@section('content')
<div x-data="{...ajaxTable(), showEnrollModal: false, selectedGroup: null}">
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">Academy Groups</h1>
            <p class="text-muted small mb-0">Manage and monitor student learning groups</p>
        </div>
        <div>
            @if($is_admin)
                <a href="{{ route('groups.create') }}" class="btn btn-primary px-4 rounded-pill shadow-sm">
                    <i class="fas fa-plus me-2"></i> Create New Group
                </a>
            @endif
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 ajax-content" id="groups-filters">
        <div class="card-body p-4">
            <form class="row g-3 ajax-form" action="{{ route('groups.index') }}" method="GET" @submit.prevent>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 theme-border">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input 
                            type="text" 
                            name="search"
                            class="form-control border-start-0 theme-card theme-border theme-text-main" 
                            placeholder="Search groups, courses, teachers..."
                            value="{{ request('search') }}"
                            @input.debounce.500ms="updateList"
                        >
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select theme-card theme-border theme-text-main" name="status" @change="updateList">
                        <option value="" {{ request('status') == '' ? 'selected' : '' }}>All Statuses</option>
                        <option value="Not Started" {{ request('status') == 'Not Started' ? 'selected' : '' }}>Not Started</option>
                        <option value="In Progress" {{ request('status') == 'In Progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="Almost Done" {{ request('status') == 'Almost Done' ? 'selected' : '' }}>Almost Done</option>
                        <option value="Completed" {{ request('status') == 'Completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="position-relative">
        <!-- Loading Overlay -->
        <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div class="ajax-content" id="groups-grid">
            @if($groups->isEmpty())
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-users fa-4x text-muted opacity-25"></i>
                    </div>
                    <h4 class="fw-bold theme-text-main">No groups found</h4>
                    <p class="text-muted">Try adjusting your filters or create a new group.</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach($groups as $group)
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden theme-card transition-hover">
                                <div class="card-header border-0 bg-primary bg-opacity-10 p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-white text-primary rounded-pill px-3 py-2 shadow-sm">
                                            {{ $group->course->course_name ?? 'N/A' }}
                                        </span>
                                        @php
                                            $today = \Carbon\Carbon::today();
                                            $start = \Carbon\Carbon::parse($group->start_date);
                                            $end = \Carbon\Carbon::parse($group->end_date);
                                            
                                            if ($today->lt($start)) {
                                                $status = 'Not Started';
                                                $badgeClass = 'bg-secondary';
                                            } elseif ($today->gt($end)) {
                                                $status = 'Completed';
                                                $badgeClass = 'bg-success';
                                            } else {
                                                $daysRemaining = $today->diffInDays($end, false);
                                                if ($daysRemaining <= 7) {
                                                    $status = 'Almost Done';
                                                    $badgeClass = 'bg-warning text-dark';
                                                } else {
                                                    $status = 'In Progress';
                                                    $badgeClass = 'bg-primary';
                                                }
                                            }
                                        @endphp
                                        <span class="badge {{ $badgeClass }} rounded-pill px-3 py-2 shadow-sm">
                                            {{ $status }}
                                        </span>
                                    </div>
                                    <h5 class="fw-bold mb-0 theme-text-main">{{ $group->group_name }}</h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light p-2 rounded-circle me-3">
                                            <i class="fas fa-chalkboard-teacher text-primary"></i>
                                        </div>
                                        <div>
                                            <p class="text-muted small mb-0">Teacher</p>
                                            <h6 class="mb-0 theme-text-main">{{ $group->teacher->teacher_name ?? 'Not Assigned' }}</h6>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-4">
                                        <div class="col-6">
                                            <div class="p-2 border rounded-3 theme-border bg-light bg-opacity-50">
                                                <p class="text-muted small mb-0">Students</p>
                                                <h6 class="mb-0 theme-text-main fw-bold">{{ $group->students->count() }} Enrolled</h6>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded-3 theme-border bg-light bg-opacity-50">
                                                <p class="text-muted small mb-0">Price</p>
                                                <h6 class="mb-0 text-success fw-bold">{{ $group->price }} EGP</h6>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <p class="text-muted small mb-1"><i class="fas fa-calendar-alt me-2"></i>Schedule</p>
                                        <p class="mb-0 theme-text-main small fw-medium">{{ $group->schedule }}</p>
                                    </div>

                                    <div class="d-flex gap-2 mt-auto pt-3 border-top theme-border">
                                        @if($is_admin)
                                            <a href="{{ route('groups.show', $group->uuid) }}" class="btn btn-outline-primary btn-sm flex-grow-1 rounded-pill">
                                                Manage
                                            </a>
                                            <a href="{{ route('groups.edit', $group->uuid) }}" class="btn btn-light btn-sm rounded-circle border theme-border">
                                                <i class="fas fa-edit text-warning"></i>
                                            </a>
                                            <form action="{{ route('groups.destroy', $group->uuid) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-light btn-sm rounded-circle border theme-border">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </button>
                                            </form>
                                        @elseif($is_student ?? false)
                                            @if($group->is_member ?? false)
                                                <a href="{{ route('groups.show', $group->uuid) }}" class="btn btn-success btn-sm w-100 rounded-pill">
                                                    Go to Group
                                                </a>
                                            @elseif(isset($group->enrollment_status) && $group->enrollment_status === 'pending')
                                                <button class="btn btn-secondary btn-sm w-100 rounded-pill" disabled>
                                                    Request Pending | قيد المراجعة
                                                </button>

                                            @elseif(isset($group->enrollment_status) && $group->enrollment_status === 'approved')
                                                <a href="{{ route('groups.show', $group->uuid) }}" class="btn btn-success btn-sm w-100 rounded-pill">
                                                    Enrolled - View
                                                </a>
                                            @else
                                                <button 
                                                    class="btn btn-primary btn-sm w-100 rounded-pill"
                                                    @click="selectedGroup = {{ json_encode($group) }}; showEnrollModal = true"
                                                >
                                                    Request Enrollment | طلب انضمام
                                                </button>

                                            @endif
                                        @else
                                            <a href="{{ route('groups.show', $group->uuid) }}" class="btn btn-outline-primary btn-sm w-100 rounded-pill">
                                                View Details
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 d-flex justify-content-center ajax-content" id="groups-pagination" @click="navigate">
                    {{ $groups->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Enrollment Request Modal (For Students) -->
    <div 
        x-show="showEnrollModal" 
        class="modal fade" 
        :class="{ 'show d-block': showEnrollModal }"
        style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(8px);" 
        x-cloak
    >
        <div class="modal-dialog modal-dialog-centered">
            <template x-if="showEnrollModal && selectedGroup">
                <div class="modal-content border-0 shadow-lg rounded-4 theme-card overflow-hidden">
                    <div class="modal-header border-0 p-4 bg-primary bg-opacity-10">
                        <h5 class="modal-title fw-bold theme-text-main">
                            <i class="fas fa-user-plus me-2 text-primary"></i> Enrollment Request
                        </h5>
                        <button type="button" class="btn-close" @click="showEnrollModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small mb-3">You are requesting to join:</p>
                        <div class="p-3 bg-light rounded-3 mb-4 theme-border border">
                            <h6 class="fw-bold mb-1 theme-text-main" x-text="selectedGroup.group_name"></h6>
                            <p class="small mb-0 text-primary fw-medium" x-text="selectedGroup.course?.course_name || 'ICT Academy Course'"></p>
                        </div>
                        <form :action="'{{ route('student.groups.request_join', ['id' => ':id']) }}'.replace(':id', selectedGroup.group_id)" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="group_id" :value="selectedGroup.group_id">
                            
                            <div class="mb-3" x-show="selectedGroup.price > 0 && !selectedGroup.is_free">
                                <label class="form-label fw-bold small text-muted">Payment Screenshot (Required for paid groups)</label>
                                <input type="file" name="screenshot" class="form-control theme-card theme-border theme-text-main rounded-3" :required="selectedGroup.price > 0 && !selectedGroup.is_free">
                                <div class="form-text extra-small">Please upload a screenshot of your payment transfer.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted">Notes (Optional)</label>
                                <textarea name="notes" class="form-control theme-card theme-border theme-text-main rounded-3" rows="3" placeholder="Any additional notes..."></textarea>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold shadow-sm transition-all hover-scale">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Join Request
                                </button>
                                <button type="button" class="btn btn-link text-muted mt-1 small" @click="showEnrollModal = false">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<style>
    .transition-hover {
        transition: all 0.3s ease;
    }
    .transition-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 3rem rgba(0,0,0,0.1) !important;
    }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    [x-cloak] { display: none !important; }
</style>
@endsection
