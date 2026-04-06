@extends('layouts.authenticated')

@section('title', 'Add New Teacher')

@section('content')
    @php
        $teacherData = [
            'teacher_name' => old('teacher_name', ''),
            'email' => old('email', ''),
            'hire_date' => old('hire_date', date('Y-m-d')),
        ];
    @endphp
    <script id="teacher-data" type="application/json">
        @json($teacherData)
    </script>
    <div class="container-fluid py-4 min-vh-100" x-data="Object.assign(JSON.parse(document.getElementById('teacher-data').textContent), { password: '' })">
        <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
            <div>
                <h1 class="h3 fw-bold mb-1 text-primary">👨‍🏫 Add New Teacher</h1>
                <p class="text-muted small mb-0">Register a new instructor in the academy system</p>
            </div>
            <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary btn-sm px-4 rounded-pill shadow-sm theme-card">
                <i class="fas fa-arrow-left me-2"></i> Back to Directory
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <form action="{{ route('teachers.store') }}" method="POST" class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card">
                    @csrf
                    <div class="card-header theme-card-header p-4 border-bottom-0">
                        <h5 class="fw-bold mb-0 theme-text-main">Teacher Registration Form</h5>
                    </div>

                    <div class="card-body p-4 pt-0">
                        <div class="row g-4">
                            <!-- Full Name -->
                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase opacity-75">Full Name</label>
                                <div class="input-group theme-input-group">
                                    <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-user text-primary"></i></span>
                                    <input
                                        type="text"
                                        name="teacher_name"
                                        class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('teacher_name') is-invalid @enderror"
                                        x-model="teacher_name"
                                        placeholder="Enter full name"
                                        required
                                    >
                                </div>
                                @error('teacher_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase opacity-75">Email Address</label>
                                <div class="input-group theme-input-group">
                                    <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-envelope text-primary"></i></span>
                                    <input
                                        type="email"
                                        name="email"
                                        class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('email') is-invalid @enderror"
                                        x-model="email"
                                        placeholder="email@example.com"
                                        required
                                    >
                                </div>
                                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <!-- Password -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase opacity-75">Default Password</label>
                                <div class="input-group theme-input-group">
                                    <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-lock text-primary"></i></span>
                                    <input
                                        type="password"
                                        name="password"
                                        class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('password') is-invalid @enderror"
                                        x-model="password"
                                        placeholder="Minimum 8 characters"
                                        required
                                    >
                                </div>
                                @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <!-- Hire Date -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-uppercase opacity-75">Hire Date</label>
                                <div class="input-group theme-input-group">
                                    <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-calendar-alt text-primary"></i></span>
                                    <input
                                        type="date"
                                        name="hire_date"
                                        class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('hire_date') is-invalid @enderror"
                                        x-model="hire_date"
                                        required
                                    >
                                </div>
                                @error('hire_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="card-footer theme-card-footer border-0 p-4 d-flex justify-content-end gap-3">
                        <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary px-4 rounded-pill">
                            Cancel
                        </a>
                        <button
                            type="submit"
                            class="btn btn-primary px-5 rounded-pill fw-bold shadow-sm"
                        >
                            <i class="fas fa-plus-circle me-2"></i> Register Teacher
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card border-0 shadow-sm rounded-4 theme-card p-4 sticky-top" style="top: 2rem;">
                    <div class="text-center mb-4">
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;" x-text="teacher_name.charAt(0) || '?'">
                        </div>
                        <h5 class="fw-bold mb-1 theme-text-main" x-text="teacher_name || 'New Teacher'"></h5>
                        <p class="text-muted small">Teacher Profile Preview</p>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 small mb-0 opacity-75">
                        <i class="fas fa-info-circle me-2"></i>
                        New teachers will be assigned the <strong>Teacher</strong> role and an active status by default.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-card-header { background-color: var(--card-bg) !important; }
        .theme-card-footer { background-color: var(--bg-main) !important; opacity: 0.9; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-input-bg { background-color: var(--bg-main) !important; color: var(--text-main) !important; }
        .text-inherit { color: inherit !important; }
        
        .card { border-radius: 1.25rem; transition: transform 0.3s ease; }
        .input-group-text { border-radius: 0.75rem 0 0 0.75rem; color: #64748b; }
        .form-control { border-radius: 0 0.75rem 0.75rem 0; }
        .btn-primary { background: linear-gradient(135deg, var(--bs-primary) 0%, #6610f2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(102, 16, 242, 0.3); }
    </style>
@endsection
