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
                    <i class="fas fa-edit text-primary me-2"></i>
                    Edit Room: {{ $room->room_name }}
                </h1>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="row g-4">
                    {{-- Main Form --}}
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h5 class="card-title mb-0 fw-bold text-dark">
                                    <i class="fas fa-door-open me-2 text-primary"></i> Room Information
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form action="{{ route('rooms.update', $room->uuid ?? $room->room_id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label for="room_name" class="form-label fw-bold small text-muted text-uppercase">Room Name</label>
                                            <input
                                                type="text"
                                                name="room_name"
                                                class="form-control form-control-lg bg-light border-0 @error('room_name') is-invalid @enderror"
                                                id="room_name"
                                                value="{{ old('room_name', $room->room_name) }}"
                                                required
                                            />
                                            @error('room_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="capacity" class="form-label fw-bold small text-muted text-uppercase">Capacity</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-0"><i class="fas fa-users"></i></span>
                                                <input
                                                    type="number"
                                                    name="capacity"
                                                    class="form-control form-control-lg bg-light border-0 @error('capacity') is-invalid @enderror"
                                                    id="capacity"
                                                    value="{{ old('capacity', $room->capacity) }}"
                                                    min="1"
                                                    required
                                                />
                                            </div>
                                            @error('capacity')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="location" class="form-label fw-bold small text-muted text-uppercase">Location</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-0"><i class="fas fa-map-marker-alt"></i></span>
                                                <input
                                                    type="text"
                                                    name="location"
                                                    class="form-control form-control-lg bg-light border-0 @error('location') is-invalid @enderror"
                                                    id="location"
                                                    value="{{ old('location', $room->location) }}"
                                                    required
                                                />
                                            </div>
                                            @error('location')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12">
                                            <label for="facilities" class="form-label fw-bold small text-muted text-uppercase">Facilities</label>
                                            <textarea
                                                name="facilities"
                                                class="form-control bg-light border-0 @error('facilities') is-invalid @enderror"
                                                id="facilities"
                                                rows="3"
                                                placeholder="Projector, AC, WiFi, etc."
                                            >{{ old('facilities', $room->facilities) }}</textarea>
                                            @error('facilities')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text small italic text-muted mt-1">Separate facilities with commas</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-check form-switch p-3 bg-light rounded-3">
                                                <input
                                                    class="form-check-input ms-0 me-3"
                                                    type="checkbox"
                                                    name="is_active"
                                                    id="is_active_check"
                                                    value="1"
                                                    {{ old('is_active', $room->is_active) ? 'checked' : '' }}
                                                    style="width: 2.5rem; height: 1.25rem;"
                                                />
                                                <label class="form-check-label fw-bold text-dark cursor-pointer" for="is_active_check">
                                                    Active & Available for Schedules
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                                            <button
                                                type="submit"
                                                class="btn btn-primary px-5 py-2 fw-bold rounded-pill shadow-sm"
                                            >
                                                Save Changes
                                            </button>
                                            <a
                                                href="{{ route('schedules.index', ['tab' => 'rooms']) }}"
                                                class="btn btn-light border px-4 py-2 fw-bold rounded-pill"
                                            >
                                                Cancel
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Sidebar Stats --}}
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
                            <div class="card-header bg-dark py-3">
                                <h6 class="card-title mb-0 text-white fw-bold">Room Statistics</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 me-3">
                                            <i class="fas fa-calendar-check fa-lg"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small fw-bold text-uppercase">Active Schedules</div>
                                            <div class="fs-4 fw-bold text-dark">{{ $activeSchedulesCount ?? 0 }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-3 me-3">
                                            <i class="fas fa-history fa-lg"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small fw-bold text-uppercase">Total History</div>
                                            <div class="fs-4 fw-bold text-dark">{{ $totalSchedulesCount ?? 0 }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info border-0 rounded-3 mb-0 small">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Room settings affect all past and future schedules linked to this room.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .form-control:focus {
            background-color: #fff !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
            border: 1px solid #0d6efd !important;
        }
        .cursor-pointer { cursor: pointer; }
    </style>
@endsection
