@extends('layouts.authenticated')

@section('title', 'Register Student')

@section('content')
    <div class="container py-4 min-vh-100" x-data="{ 
        username: '{{ old('username', $bookingData['name'] ?? '') }}',
        nickname: '{{ old('nickname', $bookingData['name'] ?? '') }}',
        email: '{{ old('email', $bookingData['email'] ?? '') }}',
        password: '',
        phone_number: '{{ old('phone_number', $bookingData['phone'] ?? '') }}',
        date_of_birth: '{{ old('date_of_birth', $bookingData['date_of_birth'] ?? '') }}',
        course_id: '{{ old('course_id', '') }}',
        booking_id: '{{ $bookingData['booking_id'] ?? '' }}'
    }">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-4 gap-3">
                    <a href="{{ route('users.index') }}" class="btn btn-light border rounded-pill px-3 shadow-sm theme-card">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                    <div>
                        <h4 class="fw-bold mb-0 theme-text-main"><i class="fas fa-user-graduate text-success me-2"></i>Register Student</h4>
                        @if($bookingData)
                            <p class="text-muted small mb-0">Pre-filled from booking #{{ $bookingData['booking_id'] }}</p>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        @if(session('error'))
                            <div class="alert alert-danger border-0 rounded-3 mb-4 shadow-sm">
                                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            </div>
                        @endif

                        <form action="{{ route('users.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="booking_id" :value="booking_id">
                            {{-- We need to tell the store method that this is a student --}}
                            <input type="hidden" name="spatie_roles[]" value="student">

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Username <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-at text-success"></i></span>
                                        <input type="text" name="username" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('username') is-invalid @enderror"
                                            x-model="username" required>
                                    </div>
                                    @error('username') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Display Name <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-id-card text-success"></i></span>
                                        <input type="text" name="nickname" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('nickname') is-invalid @enderror"
                                            x-model="nickname" required>
                                    </div>
                                    @error('nickname') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-envelope text-success"></i></span>
                                        <input type="email" name="email" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('email') is-invalid @enderror"
                                            x-model="email" required>
                                    </div>
                                    @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Password <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-lock text-success"></i></span>
                                        <input type="password" name="password" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('password') is-invalid @enderror"
                                            x-model="password" required minlength="8">
                                    </div>
                                    @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Phone Number</label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-phone text-success"></i></span>
                                        <input type="text" name="phone_number" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit"
                                            x-model="phone_number">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Date of Birth</label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-calendar-alt text-success"></i></span>
                                        <input type="date" name="date_of_birth" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit"
                                            x-model="date_of_birth">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Desired Course (Optional)</label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-book text-success"></i></span>
                                        <select name="course_id" class="form-select form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit" x-model="course_id">
                                            <option value="">No specific course</option>
                                            @foreach($courses as $course)
                                                <option value="{{ $course->course_id }}">{{ $course->course_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 mt-4 pt-4 border-top theme-border d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success rounded-pill px-5 py-2 fw-bold shadow-sm">
                                        <i class="fas fa-user-graduate me-2"></i> Register Student
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-input-bg { background-color: var(--bg-main) !important; color: var(--text-main) !important; }
        .text-inherit { color: inherit !important; }
        
        .card { border-radius: 1.25rem; }
        .input-group-text { border-radius: 0.75rem 0 0 0.75rem; background-color: var(--bg-main) !important; }
        .form-control, .form-select { border-radius: 0 0.75rem 0.75rem 0; }
        .btn-success { background: linear-gradient(135deg, #198754 0%, #20c997 100%); border: none; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(25, 135, 84, 0.3); }
    </style>
@endsection
