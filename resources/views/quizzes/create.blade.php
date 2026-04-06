@extends('layouts.authenticated')

@section('title', 'Create Quiz')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4 text-ltr">
            <div class="d-flex align-items-center">
                <a href="{{ route('sessions.show', $session->uuid ?? $session->session_id) }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </a>
                <div>
                    <h2 class="fw-bold theme-text-main mb-1">Add New Quiz</h2>
                    <p class="text-muted small mb-0">Group: <span class="theme-text-main">{{ $session->group->group_name }}</span> | Session: <span class="theme-text-main">{{ $session->topic }}</span></p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-ltr theme-card">
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('quizzes.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ $session->session_id }}">

                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Quiz Title</label>
                            <input
                                type="text"
                                name="title"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('title') is-invalid @enderror"
                                placeholder="example: Unit 1 Quiz"
                                value="{{ old('title') }}"
                                required
                            >
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Quiz Duration (in minutes)</label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    name="time_limit"
                                    class="form-control theme-badge-bg border-0 py-3 px-4 rounded-start-3 shadow-none theme-text-main @error('time_limit') is-invalid @enderror"
                                    placeholder="Leave empty for unlimited time"
                                    value="{{ old('time_limit') }}"
                                    min="0"
                                >
                                <span class="input-group-text theme-badge-bg border-0 text-muted">mins</span>
                            </div>
                            @error('time_limit') <div class="text-danger smaller mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Maximum Attempts</label>
                            <input
                                type="number"
                                name="max_attempts"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('max_attempts') is-invalid @enderror"
                                value="{{ old('max_attempts', 1) }}"
                                min="1"
                            >
                            @error('max_attempts') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold theme-text-main small text-uppercase">Quiz Description or Instructions for Students</label>
                            <textarea
                                name="description"
                                class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('description') is-invalid @enderror"
                                rows="4"
                                placeholder="Describe the quiz content or add specific instructions for students..."
                            >{{ old('description') }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch p-3 theme-badge-bg rounded-3 border-0">
                                <input 
                                    class="form-check-input ms-0 me-3 mt-1" 
                                    style="float: left; margin-right: 1rem !important;" 
                                    type="checkbox" 
                                    name="is_public" 
                                    role="switch" 
                                    id="isPublicSwitch" 
                                    value="1"
                                    {{ old('is_public') ? 'checked' : '' }}
                                >
                                <div style="margin-left: 3.5rem;">
                                    <label class="form-check-label fw-bold theme-text-main d-block" for="isPublicSwitch">
                                        Make Quiz Public
                                    </label>
                                    <small class="text-muted d-block mt-1">
                                        When enabled, other teachers will be able to see this quiz and use it as a template (Clone) in their classes.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-5">
                            <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 shadow fw-bold text-uppercase">
                                Create Quiz and Start Adding Questions
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .text-ltr { direction: ltr; }
    .smaller { font-size: 0.75rem; }
</style>
@endsection
