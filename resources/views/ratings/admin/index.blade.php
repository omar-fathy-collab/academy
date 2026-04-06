@extends('layouts.authenticated')

@section('title', 'Academic Evaluation Audit')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="ratingsManager({
    groups: {{ json_encode($groups) }},
    students: {{ json_encode($students) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">⭐ Evaluation Audit</h2>
            <p class="text-muted mb-0">Management and review of student academic ratings</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 mt-2">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end" dir="ltr">
                <div class="col-md-4 text-start">
                    <label class="form-label smaller fw-bold opacity-50">Filter by Group</label>
                    <select class="form-select border theme-border theme-badge-bg theme-text-main rounded-3 py-2" x-model="filters.group_id" @change="fetchRatings(1)">
                        <option value="">All Groups</option>
                        <template x-for="group in groups" :key="group.group_id">
                            <option :value="group.group_id" x-text="group.group_display_name"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-4 text-start">
                    <label class="form-label smaller fw-bold opacity-50">Filter by Student</label>
                    <select class="form-select border theme-border theme-badge-bg theme-text-main rounded-3 py-2" x-model="filters.student_id" @change="fetchRatings(1)">
                        <option value="">All Students</option>
                        <template x-for="student in students" :key="student.student_id">
                            <option :value="student.student_id" x-text="student.student_name"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 text-start">
                    <label class="form-label smaller fw-bold opacity-50">Rating Type</label>
                    <select class="form-select border theme-border theme-badge-bg theme-text-main rounded-3 py-2" x-model="filters.type" @change="fetchRatings(1)">
                        <option value="">All</option>
                        <option value="assignment">Assignment</option>
                        <option value="session">Session</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100 rounded-3 py-2 fw-bold" @click="resetFilters()">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ratings Table -->
    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-start" dir="ltr">
                <thead class="theme-badge-bg text-muted small text-uppercase">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="py-3">Course Track</th>
                        <th class="py-3">Rating</th>
                        <th class="py-3">Comments</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status"></div>
                                <div class="mt-2 smaller text-muted">Loading data...</div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && ratings.length === 0">
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-2x mb-3 opacity-25"></i>
                                <h6>No ratings match the criteria</h6>
                            </td>
                        </tr>
                    </template>
                    <template x-for="rating in ratings" :key="rating.rating_id">
                        <tr class="theme-border">
                            <td class="px-4">
                                <div class="fw-bold theme-text-main" x-text="rating.student_name"></div>
                                <div class="smaller text-muted" x-text="rating.group_name"></div>
                            </td>
                            <td>
                                <div class="smaller theme-text-main" x-text="rating.full_course_name"></div>
                                <div class="smaller text-muted">By: <span x-text="rating.rated_by"></span></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center flex-row">
                                    <div class="me-2 fw-bold" x-text="rating.rating_value"></div>
                                    <div class="d-flex text-warning">
                                        <template x-for="i in 5">
                                            <i class="fas fa-star smaller" :class="i <= rating.rating_value ? 'text-warning' : 'text-light'"></i>
                                        </template>
                                    </div>
                                </div>
                                <div class="badge rounded-pill bg-primary bg-opacity-10 text-primary smaller mt-1" x-text="rating.rating_type"></div>
                            </td>
                            <td class="small opacity-75" style="max-width: 250px;" x-text="rating.comments || '---'"></td>
                            <td class="px-4 text-end">
                                <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                                    <button @click="editRating(rating)" class="btn btn-sm btn-light border-0 px-3">
                                        <i class="far fa-edit text-primary"></i>
                                    </button>
                                    <button @click="deleteRating(rating.rating_id)" class="btn btn-sm btn-light border-0 px-3">
                                        <i class="far fa-trash-alt text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="card-footer theme-badge-bg border-top-0 p-4 d-flex justify-content-between align-items-center">
            <div class="smaller text-muted" x-text="'Showing ' + ratings.length + ' of ' + totalResults + ' entries'"></div>
            <nav x-if="totalPages > 1">
                <ul class="pagination pagination-sm mb-0 gap-2">
                    <li class="page-item" :class="currentPage === 1 ? 'disabled' : ''">
                        <button class="page-link rounded-circle" @click="fetchRatings(currentPage - 1)"><i class="fas fa-chevron-left"></i></button>
                    </li>
                    <li class="page-item" :class="currentPage === totalPages ? 'disabled' : ''">
                        <button class="page-link rounded-circle" @click="fetchRatings(currentPage + 1)"><i class="fas fa-chevron-right"></i></button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editRatingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow theme-card">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold theme-text-main">Edit Rating</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 text-start" dir="ltr">
                    <div class="mb-4">
                        <label class="form-label smaller fw-bold opacity-50">Rating Value (0-5)</label>
                        <input type="number" step="0.5" min="0" max="5" class="form-control rounded-3 border theme-border" x-model="editingRating.rating_value">
                    </div>
                    <div class="mb-4">
                        <label class="form-label smaller fw-bold opacity-50">Rating Type</label>
                        <select class="form-select rounded-3 border theme-border" x-model="editingRating.rating_type">
                            <option value="assignment">Assignment</option>
                            <option value="session">Session</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label smaller fw-bold opacity-50">Additional Comments</label>
                        <textarea class="form-control rounded-3 border theme-border" rows="3" x-model="editingRating.comments"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" @click="saveRating()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function ratingsManager(config) {
    return {
        groups: config.groups,
        students: config.students,
        ratings: [],
        totalResults: 0,
        currentPage: 1,
        totalPages: 1,
        loading: false,
        filters: {
            group_id: '',
            student_id: '',
            type: ''
        },
        editingRating: {
            rating_id: null,
            rating_value: 0,
            rating_type: '',
            comments: ''
        },
        
        init() {
            this.fetchRatings(1);
        },
        
        fetchRatings(page) {
            this.loading = true;
            this.currentPage = page;
            
            axios.get('/admin/evaluations/fetch', {
                params: {
                    page: this.currentPage,
                    ...this.filters
                }
            }).then(resp => {
                this.ratings = resp.data.ratings;
                this.totalResults = resp.data.total;
                this.totalPages = Math.ceil(this.totalResults / resp.data.limit);
                this.loading = false;
            });
        },
        
        resetFilters() {
            this.filters = { group_id: '', student_id: '', type: '' };
            this.fetchRatings(1);
        },
        
        editRating(rating) {
            this.editingRating = {
                rating_id: rating.rating_id,
                rating_value: rating.rating_value,
                rating_type: rating.rating_type,
                comments: rating.comments
            };
            new bootstrap.Modal(document.getElementById('editRatingModal')).show();
        },
        
        saveRating() {
            axios.put(`/admin/evaluations/${this.editingRating.rating_id}`, this.editingRating)
                .then(resp => {
                    if(resp.data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('editRatingModal')).hide();
                        Toast.fire({ icon: 'success', title: 'Rating updated' });
                        this.fetchRatings(this.currentPage);
                    }
                });
        },
        
        deleteRating(id) {
            if(confirm('Are you sure you want to delete this rating?')) {
                axios.delete(`/admin/evaluations/${id}`)
                    .then(() => {
                        Toast.fire({ icon: 'success', title: 'Rating deleted' });
                        this.fetchRatings(this.currentPage);
                    });
            }
        }
    };
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.72rem; }
    .page-link { color: var(--text-main); background: var(--bg-main); border: 1px solid var(--border-color); }
    .page-item.disabled .page-link { opacity: 0.5; }
</style>
@endsection
