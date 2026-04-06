@extends('layouts.authenticated')

@section('title', 'My Quizzes')

@section('content')
<div class="container-fluid py-4 min-vh-100">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">My Quizzes | كويزاتي</h2>
            <p class="text-muted mb-0">Manage and track all quizzes you've created.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary rounded-pill px-3 py-2 fs-6 shadow-sm">
                {{ $quizzes->total() }} Total Quizzes
            </span>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('quizzes.index') }}" method="GET" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by title or description..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-3">Search</button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light bg-opacity-50">
                        <tr>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Quiz Details</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Group / Session</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Settings</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Status</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quizzes as $quiz)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                            <i class="fas fa-brain fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $quiz->title }}</div>
                                            <div class="extra-small text-muted text-truncate" style="max-width: 250px;">{{ $quiz->description ?: 'No description' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="small fw-bold text-primary">{{ $quiz->session->group->course->course_name ?? 'N/A' }}</div>
                                    <div class="extra-small text-muted">{{ $quiz->session->group->group_name ?? 'N/A' }}</div>
                                    <div class="extra-small text-muted italic">Topic: {{ $quiz->session->topic ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="small"><i class="fas fa-clock me-1 text-muted"></i> {{ $quiz->time_limit ?: 'No limit' }}m</div>
                                    <div class="small"><i class="fas fa-redo me-1 text-muted"></i> {{ $quiz->max_attempts }} Attempts</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($quiz->is_active)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 border border-success-subtle fs-xs">ACTIVE</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 border border-secondary-subtle fs-xs">INACTIVE</span>
                                    @endif
                                    @if($quiz->is_public)
                                        <div class="extra-small text-info mt-1"><i class="fas fa-globe"></i> Public</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3">
                                            <li><a class="dropdown-item" href="{{ route('quizzes.show', $quiz->quiz_id) }}"><i class="fas fa-eye me-2"></i> Preview</a></li>
                                            <li><a class="dropdown-item" href="{{ route('quizzes.edit', $quiz->quiz_id) }}"><i class="fas fa-edit me-2 text-warning"></i> Edit Quiz</a></li>
                                            <li><a class="dropdown-item" href="{{ route('quizzes.attempts', $quiz->quiz_id) }}"><i class="fas fa-clipboard-list me-2 text-info"></i> View Attempts</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('quizzes.destroy', $quiz->quiz_id) }}" method="POST" onsubmit="return confirm('Delete this quiz? | حذف هذا الكويز؟')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash-alt me-2"></i> Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-muted">
                                    <i class="fas fa-brain fa-4x mb-3 opacity-25"></i>
                                    <h5 class="fw-bold">No quizzes found</h5>
                                    <p class="small mb-0">You haven't created any quizzes yet or no results match your search.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($quizzes->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $quizzes->links() }}
            </div>
        @endif
    </div>
</div>

<style>
    .fs-xs { font-size: 0.7rem; }
    .bg-success-subtle { background-color: rgba(25, 135, 84, 0.1); }
    .bg-secondary-subtle { background-color: rgba(108, 117, 125, 0.1); }
</style>
@endsection
