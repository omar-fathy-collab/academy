@extends('layouts.authenticated')

@section('title', ($group->group_name ?? 'Group Details'))

@section('content')
<div class="container-fluid py-4 p-0" x-data="{ activeTab: 'sessions' }">
    <!-- Header Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
        <div class="card-header bg-primary p-0" style="height: 6px; background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%) !important;"></div>
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <a href="{{ route('student.my_groups') }}" class="btn btn-sm btn-light mb-3 text-primary fw-bold rounded-pill px-3">
                        <i class="fas fa-arrow-left me-2"></i> Back to My Groups
                    </a>
                    <h2 class="display-6 fw-bold text-dark mb-1">{{ $group->group_name }}</h2>
                    <p class="lead text-muted mb-4">{{ $group->course->course_name ?? 'Advanced Course Content' }}</p>
                    
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-circle p-2 me-3 text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Instructor</div>
                                <div class="fw-bold text-dark">{{ $group->teacher->teacher_name ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center me-4">
                            <div class="bg-light rounded-circle p-2 me-3 text-info d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Status</div>
                                <div class="fw-bold text-success">Active</div>
                            </div>
                        </div>
                        <a href="{{ route('student.certificates.index', ['group_id' => $group->group_id]) }}" 
                           class="btn btn-outline-success rounded-pill fw-bold shadow-sm px-4">
                            <i class="fas fa-award me-2"></i> Request Certificate | طلب شهادة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Content -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white p-0 border-bottom">
            <div class="nav nav-tabs border-0 px-4 pt-3 gap-4" style="margin-bottom: -1px;">
                <button 
                    @click="activeTab = 'sessions'"
                    class="nav-link border-0 fw-bold px-0 py-3 position-relative"
                    :class="activeTab === 'sessions' ? 'text-primary active-tab-indicator' : 'text-muted'"
                    style="background: transparent;"
                >
                    <i class="fas fa-list-ol me-2"></i> Session History
                </button>
                <button 
                    @click="activeTab = 'materials'"
                    class="nav-link border-0 fw-bold px-0 py-3 position-relative"
                    :class="activeTab === 'materials' ? 'text-primary active-tab-indicator' : 'text-muted'"
                    style="background: transparent;"
                >
                    <i class="fas fa-folder-open me-2"></i> All Materials
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Sessions Tab -->
            <div x-show="activeTab === 'sessions'" x-cloak>
                @if(count($sessions) > 0)
                    <div class="list-group list-group-flush">
                        @foreach($sessions as $session)
                            <div class="list-group-item list-group-item-action p-4 border-0 border-bottom bg-transparent hover-bg-light">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-3 p-3 me-4 text-center shadow-sm" style="min-width: 80px;">
                                            <div class="fw-bold text-primary small text-uppercase" style="font-size: 0.7rem;">{{ \Carbon\Carbon::parse($session->session_date)->format('M') }}</div>
                                            <div class="fs-4 fw-bold text-dark">{{ \Carbon\Carbon::parse($session->session_date)->format('d') }}</div>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold text-dark mb-1">{{ $session->topic ?? 'Regular Session' }}</h5>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1 text-primary"></i> {{ $session->start_time }} - {{ $session->end_time }}
                                                <span class="mx-2 opacity-50">|</span>
                                                <i class="fas fa-file-alt me-1 text-info"></i> {{ count($session->materials) }} Materials
                                            </div>
                                        </div>
                                    </div>
                                    <a href="{{ route('student.session_details', $session->uuid ?? $session->session_id) }}" 
                                       class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm">
                                        Review Session <i class="fas fa-chevron-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-calendar-times fa-3x mb-3 opacity-25"></i>
                        <p class="mb-0">No sessions recorded yet for this group.</p>
                    </div>
                @endif
            </div>

            <!-- Materials Tab -->
            <div x-show="activeTab === 'materials'" x-cloak>
                @if(count($allMaterials) > 0)
                    <div class="list-group list-group-flush">
                        @foreach($allMaterials as $material)
                            @php
                                $ext = pathinfo($material->file_name, PATHINFO_EXTENSION);
                                $iconClass = match(strtolower($ext)) {
                                    'pdf' => 'fa-file-pdf text-danger',
                                    'doc', 'docx' => 'fa-file-word text-primary',
                                    'xls', 'xlsx' => 'fa-file-excel text-success',
                                    'zip', 'rar' => 'fa-file-archive text-warning',
                                    default => 'fa-file text-muted'
                                };
                            @endphp
                            <div class="list-group-item p-4 border-0 border-bottom bg-transparent hover-bg-light">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-3 p-3 me-4 shadow-sm d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                            <i class="fas {{ $iconClass }} fa-2x"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1">{{ $material->original_name }}</h6>
                                            <div class="text-muted small">
                                                <span><i class="far fa-calendar-alt me-1"></i> Added: {{ \Carbon\Carbon::parse($material->created_at)->format('M d, Y') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="{{ route('student.session.material.download', ['session_id' => $material->session_id, 'file_name' => $material->original_name]) }}" 
                                       class="btn btn-light rounded-pill px-4 fw-bold shadow-sm border">
                                        <i class="fas fa-download me-2 text-primary"></i> Download
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-5 text-center">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                            <i class="fas fa-folder-open fa-3x text-muted opacity-25"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">No Materials Yet</h5>
                        <p class="text-muted mb-0">No documents have been uploaded for this group yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .active-tab-indicator::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: #0d6efd;
        border-radius: 3px 3px 0 0;
    }
    .hover-bg-light:hover {
        background-color: #f8fafc !important;
    }
</style>
@endsection
