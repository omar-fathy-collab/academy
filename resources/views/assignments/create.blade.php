@extends('layouts.authenticated')

@section('title', 'Create Assignment')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4 text-ltr">
            <div class="d-flex align-items-center">
                <a href="{{ route('sessions.show', $session->uuid ?? $session->session_id) }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </a>
                <div>
                    <h2 class="fw-bold theme-text-main mb-1">Add New Assignment</h2>
                    <p class="text-muted small mb-0">Group: <span class="theme-text-main">{{ $group->group_name }}</span> | Session: <span class="theme-text-main">{{ $session->topic }}</span></p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-ltr theme-card">
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('assignments.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="group_id" value="{{ $group->group_id }}">
                    <input type="hidden" name="session_id" value="{{ $session->session_id }}">

                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Assignment Title</label>
                            <input
                                type="text"
                                name="title"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('title') is-invalid @enderror"
                                placeholder="e.g: Lesson 1 Assignment - Intro"
                                value="{{ old('title') }}"
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
                                value="{{ old('due_date') }}"
                                required
                            >
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Assignment Description</label>
                            <textarea
                                name="description"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('description') is-invalid @enderror"
                                rows="4"
                                placeholder="Add any details or instructions for students to do the assignment..."
                            >{{ old('description') }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Assignment File (Optional)</label>
                            <div class="p-4 theme-badge-bg rounded-3 border-dashed border-2 text-center clickable-file-upload position-relative">
                                <input type="file" name="teacher_file" class="position-absolute w-100 h-100 top-0 start-0 opacity-0 cursor-pointer" id="teacherFile">
                                <div id="filePreview">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-2 text-primary opacity-50"></i>
                                    <p class="mb-0 theme-text-main">Drag file here or click to choose</p>
                                    <small class="text-muted">PDF, DOCX, TXT, Images (Max 10MB)</small>
                                </div>
                            </div>
                            @error('teacher_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 mt-5">
                            <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 shadow fw-bold text-uppercase">
                                Create Assignment and Add to Session
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('teacherFile').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            document.getElementById('filePreview').innerHTML = `
                <i class='fas fa-file-alt fa-3x mb-2 text-success'></i>
                <p class='mb-0 theme-text-main'>${fileName}</p>
                <small class='text-muted'>File Selected</small>
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
