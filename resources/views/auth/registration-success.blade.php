@extends('layouts.app')

@section('title', 'Registration Successful - Shefae')

@section('body')
<div class="min-vh-100 d-flex align-items-center py-5" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)">
    <div class="container" style="max-width: 800px">
        <div class="bg-white rounded-5 shadow-lg overflow-hidden border-0 text-center p-5">
            <div class="mb-4">
                <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px">
                    <i class="fas fa-check-circle fa-4x"></i>
                </div>
            </div>

            <h1 class="fw-bold text-success mb-2">Registration Successful! 🎉</h1>
            <p class="lead text-muted mb-5">Your application is being reviewed. We'll activate your account within 24 hours.</p>

            <div class="bg-light rounded-4 p-4 text-start mb-5 text-dark">
                <h5 class="fw-bold mb-4 pb-2 border-bottom"><i class="fas fa-id-card me-2 text-primary"></i> Application Summary</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted smaller d-block mb-1">Full Name</label>
                        <div class="fw-bold text-dark">{{ $studentData['student_name'] ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted smaller d-block mb-1">Username (For Login)</label>
                        <div class="fw-bold text-primary">{{ $studentData['username'] ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted smaller d-block mb-1">Interested Course</label>
                        <div class="fw-bold text-dark">{{ $studentData['course_name'] ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted smaller d-block mb-1">Email</label>
                        <div class="fw-bold text-dark">{{ $studentData['email'] ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning border-0 rounded-4 mb-4 d-flex align-items-center text-start">
                <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                <div class="small">
                    <strong>Important:</strong> You cannot login until an administrator reviews and activates your account. You will receive an email once it's ready.
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-center mt-5">
                <a href="{{ route('login') }}" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-sm">
                    Go to Login Page
                </a>
                <a href="{{ url('/') }}" class="btn btn-outline-secondary px-5 py-3 rounded-pill fw-bold">
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-subtle { background-color: #e8f5e9 !important; }
    .smaller { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .rounded-5 { border-radius: 2rem !important; }
    .rounded-4 { border-radius: 1.5rem !important; }
</style>
@endsection
