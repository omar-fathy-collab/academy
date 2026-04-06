@extends('layouts.authenticated')

@section('title', 'Edit Assignment: ' . $assignment->title)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4 text-ltr">
            <div class="d-flex align-items-center">
                <a href="{{ route('assignments.show', $assignment->assignment_id) }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </a>
                <div>
                    <h2 class="fw-bold theme-text-main mb-1">Edit Assignment Details</h2>
                    <p class="text-muted small mb-0">Assignment: <span class="theme-text-main">{{ $assignment->title }}</span></p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-ltr theme-card">
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('assignments.update', $assignment->assignment_id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Assignment Title</label>
                            <input
                                type="text"
                                name="title"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('title') is-invalid @enderror"
                                value="{{ old('title', $assignment->title) }}"
                                required
                            >
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Due Date</label>
                            <input
                                type="date"
                                name="due_date"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('due_date') is-invalid @enderror"
                                value="{{ old('due_date', \Carbon\Carbon::parse($assignment->due_date)->format('Y-m-d')) }}"
                                required
                            >
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Assignment Description</label>
                            <textarea
                                name="description"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('description') is-invalid @enderror"
                                rows="6"
                            >{{ old('description', $assignment->description) }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Replace Assignment File (Optional)</label>
                            <div class="p-4 theme-badge-bg rounded-3 border-dashed border-2 text-center clickable-file-upload position-relative">
                                <input type="file" name="teacher_file" class="position-absolute w-100 h-100 top-0 start-0 opacity-0 cursor-pointer" id="teacherFileEdit">
                                <div id="filePreviewEdit">
                                    @if($assignment->teacher_file)
                                        <i class="fas fa-file-alt fa-3x mb-2 text-success opacity-75"></i>
                                        <p class="mb-0 theme-text-main">{{ basename($assignment->teacher_file) }}</p>
                                        <small class="text-muted">Click to replace this file</small>
                                    @else
                                        <i class="fas fa-cloud-upload-alt fa-3x mb-2 text-primary opacity-50"></i>
                                        <p class="mb-0 theme-text-main">Click to choose a new file</p>
                                        <small class="text-muted">PDF, DOCX, TXT, Images (Max 10MB)</small>
                                    @endif
                                </div>
                            </div>
                            @error('teacher_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 mt-5">
                            <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 shadow fw-bold text-uppercase">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('teacherFileEdit').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            document.getElementById('filePreviewEdit').innerHTML = `
                <i class='fas fa-file-alt fa-3x mb-2 text-warning'></i>
                <p class='mb-0 theme-text-main'>${fileName}</p>
                <small class='text-muted'>The old file will be replaced with this file</small>
            `;
        }
    });
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .text-ltr { direction: ltr; }
    .cursor-pointer { cursor: pointer; }
    .border-dashed { border-style: dashed !important; border-color: var(--border-color) !important; }
</style>
@endsection
