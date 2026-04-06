@extends('layouts.authenticated')

@section('title', 'My Groups')

@section('content')
<div class="container-fluid py-4 p-0">
    <div class="mb-4">
        <h2 class="fw-bold text-dark mb-1">My Active Groups</h2>
        <p class="text-muted">Explore your enrolled courses and upcoming sessions.</p>
    </div>

    @if(count($groups) > 0)
        <div class="row g-4">
            @foreach($groups as $group)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 transition-hover overflow-hidden">
                        <div class="card-header bg-primary bg-gradient p-3 border-0" 
                             style="background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%) !important;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold border-0">
                                    {{ $group->course->course_name ?? 'ICT Course' }}
                                </span>
                                <div class="text-white opacity-75 small">
                                    ID: #{{ $group->group_id }}
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4 bg-white">
                            <h4 class="fw-bold text-dark mb-3">{{ $group->group_name }}</h4>
                            <div class="d-flex flex-column gap-2 mb-4">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-user-tie me-2 text-primary opacity-75"></i>
                                    <span class="small">Instructor: <b>{{ $group->teacher->teacher_name ?? 'Main Instructor' }}</b></span>
                                </div>
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-clock me-2 text-primary opacity-75"></i>
                                    <span class="small">Schedule: {{ $group->schedule_type ?? 'Weekly' }}</span>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <a href="{{ route('student.group_details', $group->group_id) }}" 
                                   class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm">
                                    View Group Details <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                                <a href="{{ route('student.certificates.index', ['group_id' => $group->group_id]) }}" 
                                   class="btn btn-outline-success rounded-pill w-100 py-2 fw-bold shadow-sm">
                                    Request Certificate | طلب شهادة <i class="fas fa-award ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-5 bg-white rounded-4 shadow-sm">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 120px; height: 120px;">
                <i class="fas fa-users-slash fa-4x text-muted opacity-25"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">No Groups Found</h4>
            <p class="text-muted mx-auto" style="max-width: 400px;">
                You are not currently enrolled in any active groups. Please contact the administration if you believe this is an error.
            </p>
        </div>
    @endif
</div>

<style>
    .transition-hover {
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
    }
    .transition-hover:hover {
        transform: translateY(-8px);
        box-shadow: 0 1rem 3rem rgba(0,0,0,.1)!important;
    }
</style>
@endsection
