@extends('layouts.authenticated')

@section('title', 'My Materials')

@section('content')
<div class="container-fluid py-4 min-vh-100">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">My Materials | ماتريالاتي</h2>
            <p class="text-muted mb-0">Browse and manage all session materials you've uploaded.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary rounded-pill px-3 py-2 fs-6 shadow-sm">
                {{ $materials->total() }} Total Files
            </span>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('sessions.materials.index') }}" method="GET" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by filenames..." value="{{ $filters['search'] ?? '' }}">
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
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">File Details</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Group / Session</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Size / Type</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Date</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materials as $material)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                            @php
                                                $icon = match(explode('/', $material->mime_type)[0]) {
                                                    'image' => 'fa-file-image',
                                                    'application' => (str_contains($material->mime_type, 'pdf') ? 'fa-file-pdf' : 'fa-file-archive'),
                                                    default => 'fa-file-alt'
                                                };
                                            @endphp
                                            <i class="fas {{ $icon }} fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $material->original_name }}</div>
                                            <div class="extra-small text-muted">{{ $material->mime_type }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="small fw-bold text-primary">{{ $material->session->group->course->course_name ?? 'N/A' }}</div>
                                    <div class="extra-small text-muted">{{ $material->session->group->group_name ?? 'N/A' }}</div>
                                    <div class="extra-small text-muted italic">Topic: {{ $material->session->topic ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="small fw-bold text-dark">{{ round($material->size / 1024, 1) }} KB</div>
                                    <span class="badge bg-light text-dark border extra-small text-uppercase">{{ last(explode('/', $material->mime_type)) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-muted small">
                                    {{ $material->created_at->format('M d, Y') }}
                                    <div class="extra-small opacity-75">{{ $material->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('sessions.materials.download', $material->id) }}" class="btn btn-outline-primary btn-sm rounded-circle shadow-sm" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="{{ route('sessions.materials.preview', $material->id) }}" target="_blank" class="btn btn-outline-info btn-sm rounded-circle shadow-sm" title="Preview">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form action="{{ route('sessions.materials.destroy', $material->id) }}" method="POST" onsubmit="return confirm('Delete this material? | حذف هذا الملف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle shadow-sm" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-muted">
                                    <i class="fas fa-file-invoice fa-4x mb-3 opacity-25"></i>
                                    <h5 class="fw-bold">No materials found</h5>
                                    <p class="small mb-0">All uploaded session and group materials will appear here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($materials->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $materials->links() }}
            </div>
        @endif
    </div>
</div>

<style>
    .fs-xs { font-size: 0.7rem; }
</style>
@endsection
