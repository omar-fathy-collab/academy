@extends('layouts.authenticated')

@section('content')
    <div class="container-fluid py-4 min-vh-100 bg-light">
        {{-- Header --}}
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="{{ route('schedules.index', ['tab' => 'rooms']) }}" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="fas fa-arrow-left me-2"></i> Back to Rooms
                </a>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <i class="fas fa-plus-circle text-success me-2"></i>
                    Add New Room
                </h1>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-10">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-success bg-gradient py-3">
                        <h5 class="card-title mb-0 text-white fw-bold">
                            <i class="fas fa-door-open me-2"></i> Room Information
                        </h5>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form action="{{ route('rooms.store') }}" method="POST">
                            @csrf
                            <div class="row g-4">
                                <!-- Room Name -->
                                <div class="col-md-12">
                                    <label htmlFor="room_name" class="form-label fw-bold text-dark">
                                        Room Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-tag text-muted"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="room_name"
                                            class="form-control border-start-0 ps-0 @error('room_name') is-invalid @enderror"
                                            id="room_name"
                                            value="{{ old('room_name') }}"
                                            placeholder="e.g. Lab 101, Lecture Hall A"
                                            required
                                        />
                                    </div>
                                    @error('room_name')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Capacity & Location -->
                                <div class="col-md-6">
                                    <label htmlFor="capacity" class="form-label fw-bold text-dark">
                                        Capacity (Students) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-users text-muted"></i>
                                        </span>
                                        <input
                                            type="number"
                                            name="capacity"
                                            class="form-control border-start-0 ps-0 @error('capacity') is-invalid @enderror"
                                            id="capacity"
                                            value="{{ old('capacity') }}"
                                            min="1"
                                            placeholder="20"
                                            required
                                        />
                                    </div>
                                    @error('capacity')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label htmlFor="location" class="form-label fw-bold text-dark">
                                        Location <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-map-marker-alt text-muted"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="location"
                                            class="form-control border-start-0 ps-0 @error('location') is-invalid @enderror"
                                            id="location"
                                            value="{{ old('location') }}"
                                            placeholder="First Floor, Section B"
                                            required
                                        />
                                    </div>
                                    @error('location')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Facilities -->
                                <div class="col-12">
                                    <label htmlFor="facilities" class="form-label fw-bold text-dark">Facilities</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 align-items-start pt-2">
                                            <i class="fas fa-tools text-muted"></i>
                                        </span>
                                        <textarea
                                            name="facilities"
                                            class="form-control border-start-0 ps-0 @error('facilities') is-invalid @enderror"
                                            id="facilities"
                                            rows="4"
                                            placeholder="List available equipment (e.g. Projector, AC, High-speed Wifi, Sound System...)"
                                        >{{ old('facilities') }}</textarea>
                                    </div>
                                    @error('facilities')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Status Switch -->
                                <div class="col-12">
                                    <div class="form-check form-switch p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                                        <div>
                                            <label class="form-check-label fw-bold text-dark cursor-pointer" for="is_active_check">
                                                Active Status
                                            </label>
                                            <p class="text-muted small mb-0">Inactive rooms cannot be selected for new schedules.</p>
                                        </div>
                                        <input
                                            class="form-check-input ms-0"
                                            type="checkbox"
                                            name="is_active"
                                            id="is_active_check"
                                            value="1"
                                            {{ old('is_active', '1') == '1' ? 'checked' : '' }}
                                            style="width: 3em; height: 1.5em;"
                                        />
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                                    <button
                                        type="submit"
                                        class="btn btn-success px-5 py-2 fw-bold text-uppercase shadow-sm rounded-pill"
                                    >
                                        <i class="fas fa-save me-2"></i> Create Room
                                    </button>
                                    <a
                                        href="{{ route('schedules.index', ['tab' => 'rooms']) }}"
                                        class="btn btn-outline-secondary px-4 py-2 fw-bold text-uppercase rounded-pill"
                                    >
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .form-control:focus, .form-check-input:focus {
            box-shadow: none;
            border-color: #198754;
        }
        .input-group-text { padding: 0.75rem; }
        .form-switch .form-check-input { cursor: pointer; }
        .cursor-pointer { cursor: pointer; }
    </style>
@endsection
