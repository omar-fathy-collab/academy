@extends('layouts.authenticated')

@php
    $isEditing = isset($course);
@endphp

@section('title', $isEditing ? 'Edit Course' : 'Add New Course')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">{{ $isEditing ? 'Edit Course' : 'Create New Course' }}</h1>
            <p class="text-muted small mb-0">
                {{ $isEditing ? "Update details for {$course->course_name}" : "Define your new curriculum and its modules." }}
            </p>
        </div>
        <a href="{{ route('courses.index') }}" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border theme-text-main">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
    </div>

    <form action="{{ $isEditing ? route('courses.update', $course->course_id) : route('courses.store') }}" 
          method="POST" 
          class="row g-4"
          x-data="{ 
              subcourses: @js($isEditing ? $course->subcourses : [['number' => 1, 'name' => '', 'duration' => 10]]),
              addSubcourse() {
                  const nextNum = this.subcourses.length + 1;
                  this.subcourses.push({ number: nextNum, name: '', duration: 10 });
              },
              removeSubcourse(index) {
                  if (this.subcourses.length > 1) {
                      this.subcourses.splice(index, 1);
                  }
              }
          }">
        @csrf
        @if($isEditing)
            @method('PUT')
        @endif

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 theme-card mb-4">
                <h5 class="fw-bold mb-4 theme-text-main">Basic Information</h5>
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase opacity-75 theme-text-main">Course Name</label>
                    <input
                        type="text"
                        name="course_name"
                        class="form-control form-control-lg border-0 theme-badge-bg rounded-3 theme-text-main @error('course_name') is-invalid @enderror"
                        placeholder="e.g. Advanced Web Development"
                        value="{{ old('course_name', $course->course_name ?? '') }}"
                        required
                    >
                    @error('course_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold small text-uppercase opacity-75 theme-text-main">Description</label>
                    <textarea
                        name="description"
                        class="form-control border-0 theme-badge-bg rounded-3 theme-text-main @error('description') is-invalid @enderror"
                        rows="5"
                        placeholder="Describe what students will learn..."
                    >{{ old('description', $course->description ?? '') }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 p-4 theme-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 theme-text-main">Syllabus / Subcourses</h5>
                    <button type="button" @click="addSubcourse()" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i> Add Module
                    </button>
                </div>

                <div class="subcourses-list">
                    <template x-for="(sub, index) in subcourses" :key="index">
                        <div class="subcourse-item p-3 rounded-4 theme-badge-bg mb-3 position-relative border theme-border">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label smaller fw-bold opacity-50 theme-text-main"># No.</label>
                                    <input
                                        type="number"
                                        :name="`subcourses[${index}][number]`"
                                        class="form-control border-0 rounded-3 shadow-sm theme-card theme-text-main"
                                        x-model="sub.number || sub.subcourse_number"
                                        required
                                    >
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label smaller fw-bold opacity-50 theme-text-main">Module Title</label>
                                    <input
                                        type="text"
                                        :name="`subcourses[${index}][name]`"
                                        class="form-control border-0 rounded-3 shadow-sm theme-card theme-text-main"
                                        placeholder="e.g. React Hooks"
                                        x-model="sub.name || sub.subcourse_name"
                                        required
                                    >
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label smaller fw-bold opacity-50 theme-text-main">Hours</label>
                                    <input
                                        type="number"
                                        :name="`subcourses[${index}][duration]`"
                                        class="form-control border-0 rounded-3 shadow-sm theme-card theme-text-main"
                                        x-model="sub.duration || sub.duration_hours"
                                        required
                                    >
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button
                                        type="button"
                                        @click="removeSubcourse(index)"
                                        class="btn btn-link text-danger p-2 h-100"
                                        :disabled="subcourses.length === 1"
                                    >
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Hidden IDs for existing subcourses if editing -->
                            <template x-if="sub.subcourse_id">
                                <input type="hidden" :name="`subcourses[${index}][id]`" :value="sub.subcourse_id">
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 theme-card sticky-top" style="top: 100px;">
                <h5 class="fw-bold mb-4 theme-text-main">Actions</h5>
                <p class="small text-muted mb-4">
                    Review your changes carefully before saving. All subcourses will be updated or created in the database.
                </p>
                <button
                    type="submit"
                    class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm mb-3"
                >
                    <i class="fas fa-save me-2"></i> {{ $isEditing ? 'Update Course' : 'Create Course' }}
                </button>
                <a href="{{ route('courses.index') }}" class="btn theme-badge-bg w-100 py-3 rounded-pill fw-bold border theme-border theme-text-main">
                    Cancel
                </a>
            </div>
        </div>
    </form>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-badge-bg { background-color: var(--badge-bg) !important; }

        .smaller { font-size: 0.7rem; text-transform: uppercase; }
        .subcourse-item { transition: transform 0.2s; border: 1px solid transparent; }
        .subcourse-item:hover { transform: scale(1.005); border-color: var(--bs-primary); }
    </style>
@endsection
