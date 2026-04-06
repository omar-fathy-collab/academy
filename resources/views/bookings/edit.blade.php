@extends('layouts.authenticated')

@section('title', 'Edit Inquiry: ' . $booking->name)

@section('content')
<div class="container-fluid py-4 theme-text-main">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start mb-5">
                    <div>
                        <h2 class="fw-bold theme-text-main">Update Inquiry Information</h2>
                        <p class="text-muted mb-0">Modify student details and scheduling</p>
                    </div>
                    <form action="{{ route('bookings.destroy', $booking->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this inquiry?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                            <i class="far fa-trash-alt me-2"></i> Delete Inquiry
                        </button>
                    </form>
                </div>

                <form action="{{ route('bookings.update', $booking->id) }}" method="POST" class="row g-4">
                    @csrf
                    @method('PUT')
                    
                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Full Name</label>
                        <input type="text" name="name" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required value="{{ $booking->name }}">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Email Address</label>
                        <input type="email" name="email" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required value="{{ $booking->email }}">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Phone Number</label>
                        <input type="text" name="phone" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required value="{{ $booking->phone }}">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Age</label>
                        <input type="number" name="age" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required value="{{ $booking->age }}">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Booking Date</label>
                        <input type="date" name="date" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required value="{{ $booking->date }}">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Booking Time</label>
                        <input type="time" name="time" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required value="{{ $booking->time }}">
                    </div>

                    <div class="col-12 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Student Message or Additional Notes</label>
                        <textarea name="message" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" rows="4">{{ $booking->message }}</textarea>
                    </div>

                    @if(auth()->user()->isAdmin())
                        <div class="col-12 text-start">
                            <label class="form-label fw-bold smaller text-uppercase">Placement Exam Grade</label>
                            <input type="number" name="placement_exam_grade" class="form-control rounded-3 border border-primary theme-badge-bg theme-text-main py-2 fw-bold fs-5" step="0.01" min="0" max="100" value="{{ $booking->placement_exam_grade }}">
                            <p class="smaller text-muted mt-2">Only admins can modify this grade</p>
                        </div>
                    @endif

                    <div class="col-12 text-center mt-5">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm transition-hover">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="vstack gap-4">
                <!-- Status & Enrollment Info -->
                <div class="card border-0 shadow-sm rounded-4 theme-card p-4">
                    <h5 class="fw-bold mb-4">Enrollment Status</h5>
                    
                    @if($booking->student_id)
                        <div class="alert alert-success border-0 rounded-4 p-3 d-flex align-items-center mb-4">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Enrolled Student</h6>
                                <p class="smaller mb-0 opacity-75">This inquiry is now a student record</p>
                            </div>
                        </div>
                        
                        <h6 class="smaller fw-bold text-uppercase text-muted mb-3">Group Membership</h6>
                        @foreach($booking->waitingGroups as $group)
                            <div class="p-3 theme-badge-bg rounded-4 border theme-border mb-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold fs-6">{{ $group->group_name }}</div>
                                    <div class="smaller text-muted">{{ $group->course->course_name }}</div>
                                </div>
                                <a href="{{ route('waiting-groups.edit', $group->id) }}" class="btn btn-sm btn-light border-0 rounded-circle shadow-sm" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-external-link-alt text-primary smaller"></i>
                                </a>
                            </div>
                        @endforeach
                    @else
                        <div class="alert alert-warning border-0 rounded-4 p-3 d-flex align-items-center mb-4">
                            <i class="far fa-clock fa-2x me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Unassigned Inquiry</h6>
                                <p class="smaller mb-0 opacity-75">Not yet consolidated into any group</p>
                            </div>
                        </div>
                        <a href="{{ route('bookings.add-to-waiting-group-form', $booking->id) }}" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">
                            <i class="fas fa-user-plus me-2"></i> Add to Waiting Group
                        </a>
                    @endif
                </div>

                <!-- Contact & Help -->
                <div class="card border-0 shadow-sm rounded-4 theme-card p-4 theme-badge-bg">
                    <h6 class="fw-bold mb-3"><i class="fas fa-info-circle text-primary me-2"></i> Quick Help</h6>
                    <p class="smaller text-muted">Updating the placement exam grade will automatically suggest a level for the student during group distribution.</p>
                    <hr class="theme-border">
                    <div class="d-grid gap-2">
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $booking->phone) }}" target="_blank" class="btn btn-outline-success btn-sm rounded-pill py-2 fw-bold">
                            <i class="fab fa-whatsapp me-2"></i> WhatsApp Contact
                        </a>
                        <a href="mailto:{{ $booking->email }}" class="btn btn-outline-primary btn-sm rounded-pill py-2 fw-bold">
                            <i class="far fa-envelope me-2"></i> Email Student
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.72rem; }
    .transition-hover:hover { transform: translateY(-3px); }
</style>
@endsection
