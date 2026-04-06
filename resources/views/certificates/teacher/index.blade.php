@extends('layouts.authenticated')

@section('title', 'Awarded Certificates & Badges')

@section('content')
<div class="container-fluid py-4 min-vh-100 bg-light">
    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark">
                <i class="fas fa-award text-primary me-2"></i> Awarded Badges
            </h1>
            <p class="text-muted small mb-0">Record of all academic credentials you have issued to your students.</p>
        </div>
        <a href="{{ route('teacher.certificates.create') }}" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="fas fa-plus-circle me-1"></i> Award New Badge
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-body p-0">
            @if($certificates->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light border-0">
                            <tr>
                                <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Student</th>
                                <th class="py-3 border-0 small text-uppercase fw-bold text-muted">Course / Group</th>
                                <th class="py-3 border-0 small text-uppercase fw-bold text-muted">Serial Number</th>
                                <th class="py-3 border-0 small text-uppercase fw-bold text-muted">Issue Date</th>
                                <th class="py-3 border-0 small text-uppercase fw-bold text-muted text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($certificates as $cert)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                {{ strtoupper(substr($cert->user->name ?? 'S', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">{{ $cert->user->name ?? 'N/A' }}</div>
                                                <div class="text-muted extra-small">{{ $cert->user->email ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-bold text-primary small">{{ $cert->course->course_name ?? 'Individual' }}</div>
                                        <div class="text-muted extra-small">{{ $cert->group->group_name ?? 'Individual Track' }}</div>
                                    </td>
                                    <td class="py-3">
                                        <code class="bg-light px-2 py-1 rounded text-dark small border">{{ $cert->certificate_number }}</code>
                                    </td>
                                    <td class="py-3 text-muted small">
                                        {{ \Carbon\Carbon::parse($cert->issue_date)->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 text-center">
                                        <a href="{{ route('student.certificates.view', $cert->id) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm hover-elevate" target="_blank">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-top">
                    {{ $certificates->links() }}
                </div>
            @else
                <div class="p-5 text-center text-muted">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                        <i class="fas fa-certificate fa-3x opacity-25"></i>
                    </div>
                    <h4 class="fw-bold text-dark">No badges awarded yet</h4>
                    <p class="mb-4">Encourage your students by awarding them badges for their performance.</p>
                    <a href="{{ route('teacher.certificates.create') }}" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        Award Your First Badge
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.72rem; }
    .hover-elevate:hover { transform: translateY(-2px); transition: all 0.2s ease; }
</style>
@endsection
