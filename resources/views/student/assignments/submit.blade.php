@extends('layouts.authenticated')

@section('title', 'Submit Assignment: ' . $assignment->title)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4 text-ltr">
            <div class="d-flex align-items-center">
                <a href="{{ route('student.my_assignments') }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </a>
                <div>
                    <h2 class="fw-bold theme-text-main mb-1">Submit Assignment</h2>
                    <p class="text-muted small mb-0">Assignment: <span class="theme-text-main fw-bold">{{ $assignment->title }}</span> | Group: <span class="theme-text-main fw-bold">{{ $assignment->group_name }}</span></p>
                </div>
            </div>
            @php
                $dueDate = \Carbon\Carbon::parse($assignment->due_date);
                $isOverdue = $dueDate->isPast();
            @endphp
            <div class="badge {{ $isOverdue ? 'bg-danger' : 'bg-success' }} rounded-pill px-4 py-2 shadow-sm">
                Due Date: {{ $dueDate->format('M d, Y') }}
            </div>
        </div>

        <div class="row g-4">
            <!-- Assignment Details -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 text-ltr">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="fw-bold theme-text-main mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Assignment Instructions</h5>
                        <p class="text-muted mb-4">
                            {{ $assignment->description ?: 'No additional instructions added for this assignment.' }}
                        </p>
                        @if($assignment->teacher_file)
                            <a href="/{{ $assignment->teacher_file }}" target="_blank" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fas fa-file-download me-2"></i> Download Teacher Files
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Submission Form -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 theme-card text-ltr">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="fw-bold theme-text-main mb-4"><i class="fas fa-upload me-2 text-primary"></i>Upload Your Solution</h5>
                        
                        @if(session('success'))
                            <div class="alert alert-success border-0 rounded-3 mb-4">{{ session('success') }}</div>
                        @endif

                        <form action="{{ route('student.submit_assignment.post', ['assignment' => $assignment->assignment_id]) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="assignment_id" value="{{ $assignment->assignment_id }}">

                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label fw-bold theme-text-main small text-uppercase">Solution Files</label>
                                    <div class="p-5 theme-badge-bg rounded-4 border-dashed border-2 text-center clickable-file-upload position-relative">
                                        <input type="file" name="files[]" class="position-absolute w-100 h-100 top-0 start-0 opacity-0 cursor-pointer" multiple id="studentFiles">
                                        <div id="filesPreview">
                                            @if($submission && $submission->file_path)
                                                @php $files = json_decode($submission->file_path, true); @endphp
                                                <i class="fas fa-check-circle fa-3x mb-2 text-success"></i>
                                                <p class="mb-0 theme-text-main">You have a previous submission ({{ count($files) }} files)</p>
                                                <small class="text-muted">Click to replace all files with new ones</small>
                                            @else
                                                <i class="fas fa-cloud-upload-alt fa-3x mb-2 text-primary opacity-50"></i>
                                                <p class="mb-0 theme-text-main">Drag solution files here or click to choose</p>
                                                <small class="text-muted">PDF, Images, ZIP, Word... (Max 15MB)</small>
                                            @endif
                                        </div>
                                    </div>
                                    @error('files.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold theme-text-main small text-uppercase">Notes for Teacher (Optional)</label>
                                    <textarea
                                        name="message"
                                        class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main"
                                        rows="4"
                                        placeholder="Add any notes you'd like to send to the teacher with the assignment..."
                                    >{{ old('message', $submission->feedback ?? '') }}</textarea>
                                </div>

                                @if($submission && $submission->score !== null)
                                    <div class="col-12">
                                        <div class="p-4 bg-success bg-opacity-10 border border-success rounded-4 text-center">
                                            <h6 class="fw-bold mb-1 text-success">Assignment Graded</h6>
                                            <h2 class="fw-bold theme-text-main mb-2">{{ $submission->score }} / 100</h2>
                                            <p class="mb-0 small text-muted">Graded On: {{ \Carbon\Carbon::parse($submission->graded_at)->format('M d, Y') }}</p>
                                        </div>
                                    </div>
                                @endif

                                <div class="col-12 mt-5">
                                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill shadow fw-bold text-uppercase" {{ $isOverdue && !$submission ? 'disabled' : '' }}>
                                        {{ $submission ? 'Update Current Submission' : 'Submit Assignment for Review' }}
                                    </button>
                                    @if($isOverdue && !$submission)
                                        <p class="text-danger text-center small mt-3 fw-bold">The submission deadline has passed!</p>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('studentFiles').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileCount = e.target.files.length;
            document.getElementById('filesPreview').innerHTML = `
                <i class='fas fa-file-upload fa-3x mb-2 text-warning'></i>
                <p class='mb-0 theme-text-main'>Selected ${fileCount} files</p>
                <small class='text-muted'>Any previous submission will be replaced upon upload</small>
            `;
        }
    });
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .text-ltr { direction: ltr; text-align: left; }
    .cursor-pointer { cursor: pointer; }
    .border-dashed { border-style: dashed !important; border-color: var(--border-color) !important; }
</style>
@endsection
