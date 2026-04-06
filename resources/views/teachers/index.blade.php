@extends('layouts.authenticated')

@section('title', 'Teachers Directory')

@section('content')
    <div class="container-fluid py-4 min-vh-100" x-data="{ 
        teachers: [],
        loading: true,
        search: '',
        page: 1,
        totalPages: 1,
        totalRecords: 0,
        limit: 10,

        async fetchTeachers(currentPage = 1) {
            this.loading = true;
            try {
                const response = await fetch(`/teachers/fetch?page=${currentPage}&search=${this.search}`);
                const data = await response.json();
                this.teachers = data.teachers;
                this.totalRecords = data.total;
                this.totalPages = Math.ceil(data.total / data.limit);
                this.page = currentPage;
            } catch (error) {
                console.error('Error fetching teachers:', error);
            } finally {
                this.loading = false;
            }
        },

        init() {
            this.fetchTeachers();
            this.$watch('search', value => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => this.fetchTeachers(1), 300);
            });
        },

        handleDelete(id) {
            if (confirm('Are you sure you want to delete this teacher? This action will also remove their user account and profile.')) {
                fetch(`/teachers/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                }).then(response => {
                    if (response.ok) {
                        this.fetchTeachers(this.page);
                    }
                });
            }
        }
    }" x-init="init()">
        
        <div class="row mb-4 align-items-center g-3">
            <div class="col-md-6">
                <h2 class="fw-bold text-primary mb-0">👨‍🏫 Teachers Directory</h2>
                <p class="text-muted mb-0 opacity-75">Manage teaching staff, profiles, and performance</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="{{ route('teachers.create') }}" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-plus-circle me-2"></i> Add New Teacher
                </a>
            </div>
        </div>

        <div class="card shadow-sm rounded-4 border-0 overflow-hidden theme-card">
            <div class="card-header theme-card-header p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h5 class="fw-bold mb-0">
                    Teaching Staff <span class="badge bg-primary rounded-pill ms-2" x-text="totalRecords"></span>
                </h5>

                <div class="d-flex align-items-center theme-input rounded-pill px-3 py-2 border w-100" style="max-width: 400px;">
                    <i class="fas fa-search text-muted me-2"></i>
                    <input
                        type="text"
                        class="form-control border-0 bg-transparent shadow-none p-0 text-inherit"
                        placeholder="Search by name or email..."
                        x-model="search"
                    >
                    <button class="btn btn-link text-muted p-0" x-show="search" @click="search = ''">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="card-body p-0 theme-card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="theme-thead small text-uppercase fw-bold">
                            <tr>
                                <th class="px-4 py-3">Instructor</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3 text-center">Groups</th>
                                <th class="px-4 py-3">Hire Date</th>
                                <th class="px-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="loading">
                                <tr>
                                    <td colSpan="5" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <div class="mt-2 text-muted fw-bold small">Updating records...</div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="!loading && teachers.length > 0">
                                <template x-for="teacher in teachers" :key="teacher.teacher_id">
                                    <tr class="theme-tr">
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-3 bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                                     style="width: 45px; height: 45px; font-size: 1.2rem; border: 2px solid var(--card-bg);"
                                                     x-text="teacher.teacher_name ? teacher.teacher_name.charAt(0) : ''">
                                                </div>
                                                <div>
                                                    <div class="fw-bold theme-text-main" x-text="teacher.teacher_name"></div>
                                                    <div class="small text-muted opacity-75" x-text="'TID-' + teacher.teacher_id"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="small fw-medium theme-text-main" x-text="teacher.email || 'No email'"></div>
                                            <div class="small text-muted opacity-75">Official Email</div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-3 py-2 border border-primary border-opacity-25">
                                                <span x-text="teacher.group_count"></span> Active Units
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-muted small fw-medium" x-text="teacher.hire_date ? new Date(teacher.hire_date).toLocaleDateString() : 'N/A'">
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a :href="'/teachers/' + teacher.teacher_id" 
                                                   class="btn btn-sm btn-outline-info rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" 
                                                   style="width: 32px; height: 32px;" 
                                                   title="View Profile">
                                                    <i class="fas fa-eye small"></i>
                                                </a>
                                                <a :href="'/teachers/' + teacher.teacher_id + '/edit'" 
                                                   class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" 
                                                   style="width: 32px; height: 32px;" 
                                                   title="Edit Details">
                                                    <i class="fas fa-pen small"></i>
                                                </a>
                                                <button @click="handleDelete(teacher.teacher_id)"
                                                        class="btn btn-sm btn-outline-danger rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" 
                                                        style="width: 32px; height: 32px;" 
                                                        title="Delete Teacher">
                                                    <i class="fas fa-trash-alt small"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </template>
                            <template x-if="!loading && teachers.length === 0">
                                <tr>
                                    <td colSpan="5" class="text-center py-5 text-muted">
                                        <div class="fs-1 mb-3">🔍</div>
                                        <h5 class="fw-bold theme-text-main">No teachers found</h5>
                                        <p class="small">Try adjusting your search criteria</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="card-footer theme-card-footer p-4 d-flex justify-content-between align-items-center border-top" x-show="totalPages > 1">
                <span class="text-muted small fw-medium">
                    Showing <span class="fw-bold theme-text-main" x-text="(page - 1) * limit + 1"></span> to <span class="fw-bold theme-text-main" x-text="Math.min(page * limit, totalRecords)"></span> of <span class="fw-bold theme-text-main" x-text="totalRecords"></span> teachers
                </span>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0 shadow-sm align-items-center">
                        <li class="page-item" :class="page === 1 ? 'disabled' : ''">
                            <button class="page-link rounded-start-pill px-3 theme-page-link" @click="fetchTeachers(page - 1)">
                                <i class="fas fa-chevron-left small"></i>
                            </button>
                        </li>
                        <template x-for="pageNum in totalPages" :key="pageNum">
                            <li class="page-item" :class="page === pageNum ? 'active' : ''" x-show="pageNum === 1 || pageNum === totalPages || Math.abs(page - pageNum) <= 1">
                                <button class="page-link theme-page-link" :class="page === pageNum ? 'active' : ''" @click="fetchTeachers(pageNum)" x-text="pageNum"></button>
                            </li>
                        </template>
                        <li class="page-item" :class="page === totalPages ? 'disabled' : ''">
                            <button class="page-link rounded-end-pill px-3 theme-page-link" @click="fetchTeachers(page + 1)">
                                <i class="fas fa-chevron-right small"></i>
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-card-header { background-color: var(--card-bg) !important; border-bottom: 1px solid var(--border-color) !important; }
        .theme-card-body { background-color: var(--card-bg) !important; }
        .theme-card-footer { background-color: var(--card-bg) !important; border-top: 1px solid var(--border-color) !important; }
        .theme-input { background-color: var(--bg-main) !important; border: 1px solid var(--border-color) !important; }
        .theme-thead { background-color: var(--bg-main) !important; color: var(--text-muted) !important; }
        .theme-tr { border-bottom: 1px solid var(--border-color) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .text-inherit { color: inherit !important; }
        .theme-page-link { background-color: var(--card-bg) !important; border-color: var(--border-color) !important; color: var(--text-muted) !important; }
        .theme-page-link.active { background-color: var(--app-primary-color) !important; color: white !important; border-color: var(--app-primary-color) !important; }
        
        .btn-primary { background: linear-gradient(135deg, var(--bs-primary) 0%, #6610f2 100%); border: none; transition: transform 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); }
        .table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.05) !important; transition: all 0.2s ease; }
        .avatar { transition: transform 0.2s; }
        tr:hover .avatar { transform: scale(1.1); }
    </style>
@endsection
