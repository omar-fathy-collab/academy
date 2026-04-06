@extends('layouts.authenticated')

@section('title', 'Edit Subcourse')

@section('content')
<div class="container py-4 min-vh-100 theme-bg-main">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="d-flex align-items-center mb-4 gap-3">
                <button onclick="window.history.back()" class="btn theme-card border theme-border rounded-pill px-3 theme-text-main shadow-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </button>
                <h4 class="fw-bold mb-0 theme-text-main">
                    <i class="fas fa-layer-group text-warning me-2"></i>Edit Subcourse
                </h4>
            </div>

            @if($errors->any())
                <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
                    <ul class="mb-0 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <div class="card-body p-4">
                    <form action="{{ route('subcourses.update', $subcourse->subcourse_id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <label class="form-label fw-bold theme-text-main">Course <span className="text-danger">*</span></label>
                            <select name="course_id" class="form-select rounded-3 theme-badge-bg theme-text-main theme-border px-3 py-2 @error('course_id') is-invalid @enderror" required>
                                @foreach($courses as $c)
                                    <option value="{{ $c->course_id }}" {{ (old('course_id', $subcourse->course_id) == $c->course_id) ? 'selected' : '' }}>
                                        {{ $c->course_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-8">
                                <label class="form-label fw-bold theme-text-main">Subcourse Name <span className="text-danger">*</span></label>
                                <input type="text" name="subcourse_name" value="{{ old('subcourse_name', $subcourse->subcourse_name) }}"
                                    class="form-control rounded-3 theme-badge-bg theme-text-main theme-border px-3 py-2 @error('subcourse_name') is-invalid @enderror" required />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold theme-text-main">Order # <span className="text-danger">*</span></label>
                                <input type="number" name="subcourse_number" value="{{ old('subcourse_number', $subcourse->subcourse_number) }}"
                                    class="form-control rounded-3 theme-badge-bg theme-text-main theme-border px-3 py-2 @error('subcourse_number') is-invalid @enderror" required min="1" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold theme-text-main">Duration (hours)</label>
                                <input type="number" name="duration_hours" value="{{ old('duration_hours', $subcourse->duration_hours) }}"
                                    class="form-control rounded-3 theme-badge-bg theme-text-main theme-border px-3 py-2" min="0" />
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold theme-text-main">Description</label>
                                <textarea name="description" class="form-control rounded-3 theme-badge-bg theme-text-main theme-border px-3 py-2" rows="3">{{ old('description', $subcourse->description) }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-5 pt-3 border-top theme-border">
                            <button type="submit" class="btn btn-warning rounded-pill px-5 py-2 fw-bold shadow-sm">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    :root {
        --bg-main: #f8fafc;
        --text-main: #1e293b;
        --card-bg: #ffffff;
        --border-color: #e2e8f0;
        --badge-bg: #f1f5f9;
    }

    [data-bs-theme='dark'] {
        --bg-main: #0b1120;
        --text-main: #f8fafc;
        --card-bg: #0f172a;
        --border-color: rgba(255, 255, 255, 0.05);
        --badge-bg: rgba(255, 255, 255, 0.03);
    }

    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--badge-bg) !important; }
    .theme-bg-main { background-color: var(--bg-main) !important; }

    .form-select, .form-control { color: inherit; }
</style>
@endpush
@endsection
