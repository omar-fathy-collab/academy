@extends('layouts.authenticated')

@section('title', 'Students Management')

@section('content')
    <div x-data="{ 
        ...ajaxTable(),
        showWaitingModal: false,
        waitingStudent: null,
        waitingGroups: [],
        waitingGroupId: '',
        waitingNotes: '',
        submittingWaiting: false,
        
        async openWaitingModal(student) {
            this.waitingStudent = student;
            this.showWaitingModal = true;
            try {
                const response = await fetch('/api/waiting-groups');
                const data = await response.json();
                this.waitingGroups = data;
            } catch (error) {
                console.error('Error loading waiting groups:', error);
            }
        },

        async submitWaiting() {
            this.submittingWaiting = true;
            try {
                const response = await fetch('/students/add-to-waiting-group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        student_id: this.waitingStudent.id,
                        waiting_group_id: this.waitingGroupId,
                        notes: this.waitingNotes
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.showWaitingModal = false;
                    this.waitingGroupId = '';
                    this.waitingNotes = '';
                    this.updateList();
                } else {
                    alert(data.message || 'Failed to add to waiting group');
                }
            } catch (error) {
                console.error('Error submitting waiting group:', error);
            } finally {
                this.submittingWaiting = false;
            }
        }
    }">
        
        <div class="d-flex justify-content-between align-items-center mb-4 pt-3 pb-2 border-bottom border-theme">
            <h1 class="h3 fw-bold mb-0 text-main">
                <i class="fas fa-users me-2 text-primary"></i>Students Management
            </h1>
        </div>

        <div class="card shadow-sm rounded-4 bg-theme border-theme mb-4">
            <div class="card-body p-4 ajax-content" id="students-filters">
                <form class="row g-3 align-items-end mb-4 ajax-form" action="{{ route('students.index') }}" method="GET" @submit.prevent>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-main">Filter Students</label>
                        <select
                            name="filter"
                            class="form-select rounded-pill border-theme bg-light-theme text-main"
                            @change="updateList"
                        >
                            <option value="all" {{ $filter == 'all' ? 'selected' : '' }}>All Students</option>
                            <option value="no_groups" {{ $filter == 'no_groups' ? 'selected' : '' }}>No Group Enrollments</option>
                            <option value="no_active_groups" {{ $filter == 'no_active_groups' ? 'selected' : '' }}>No Active/Current Groups</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-main">Search</label>
                        <div class="input-group rounded-pill overflow-hidden border-theme">
                            <span class="input-group-text bg-theme border-0"><i class="fas fa-search text-muted"></i></span>
                            <input
                                type="text"
                                name="search"
                                class="form-control border-0 px-0 bg-theme text-main"
                                placeholder="Search by name, email, phone..."
                                value="{{ $search }}"
                                @input.debounce.500ms="updateList"
                            >
                        </div>
                    </div>
                    <div class="col-md-3 text-end d-flex align-items-center justify-content-end gap-2">
                        <a href="{{ route('students.export') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" title="Export current list to Excel">
                            <i class="fas fa-file-excel me-1"></i> Export
                        </a>
                        <span class="badge bg-primary rounded-pill px-3 py-2">Total: {{ number_format($students->total()) }}</span>
                    </div>
                </form>

                <div class="position-relative">
                    <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    
                    <div class="table-responsive ajax-content" id="students-table">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 rounded-start">ID</th>
                                    <th class="border-0">Name</th>
                                    <th class="border-0">Email</th>
                                    <th class="border-0">Groups</th>
                                    <th class="border-0">Phone</th>
                                    <th class="border-0">Age</th>
                                    <th class="border-0">Status</th>
                                    <th class="border-0 rounded-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($students as $student)
                                    <tr class="border-theme">
                                        <td><span class="text-muted small">#{{ $student->student_id }}</span></td>
                                        <td class="fw-bold text-main">
                                            <div>{{ $student->student_name }}</div>
                                            <div class="text-muted small fw-normal">{{ $student->user->username ?? 'N/A' }}</div>
                                        </td>
                                        <td class="small text-muted">{{ $student->user->email ?? 'N/A' }}</td>
                                        <td><span class="badge bg-info-subtle text-info rounded-pill">{{ $student->groups_count }}</span></td>
                                        <td class="small text-nowrap text-muted">{{ $student->user->profile->phone_number ?? 'N/A' }}</td>
                                        <td class="text-main">{{ $student->user->profile->date_of_birth ? \Carbon\Carbon::parse($student->user->profile->date_of_birth)->age : 'N/A' }}</td>
                                        <td>
                                            <span class="badge rounded-pill {{ ($student->user->is_active ?? false) ? 'bg-success' : 'bg-secondary' }}">
                                                {{ ($student->user->is_active ?? false) ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('student.info.show', ['id' => $student->student_id]) }}" class="btn btn-sm btn-outline-primary rounded-pill" title="View Info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button 
                                                    @click="openWaitingModal({ 
                                                        id: {{ $student->student_id }}, 
                                                        username: '{{ addslashes($student->student_name) }}' 
                                                    })" 
                                                    class="btn btn-sm btn-outline-info rounded-pill" 
                                                    title="Add to Waiting Group"
                                                >
                                                    <i class="fas fa-user-clock"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">No students found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pagination --}}
                <div class="mt-4 d-flex justify-content-center ajax-content" id="students-pagination" @click="navigate">
                    @if($students->hasPages())
                        {{ $students->links() }}
                    @endif
                </div>
            </div>
        </div>

        {{-- Waiting Group Modal --}}
        <div class="modal fade" :class="showWaitingModal ? 'show d-block' : ''" style="background-color: rgba(0,0,0,0.5)" x-show="showWaitingModal" x-cloak>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header bg-info text-white border-0 rounded-top-4">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-user-clock me-2"></i>Add to Waiting Group
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="showWaitingModal = false"></button>
                    </div>
                    <form @submit.prevent="submitWaiting">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Student</label>
                                <input type="text" class="form-control bg-light border-0 px-3 py-2 rounded-3" :value="waitingStudent?.username || ''" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Waiting Group</label>
                                <select class="form-select bg-light border-0 px-3 py-2 rounded-3" x-model="waitingGroupId" required>
                                    <option value="">Select a group</option>
                                    <template x-for="group in waitingGroups" :key="group.id">
                                        <option :value="group.id" x-text="group.name + ' (' + (group.course_name || 'General') + ')'"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Notes</label>
                                <textarea class="form-control bg-light border-0 px-3 py-2 rounded-3" rows="3" x-model="waitingNotes" placeholder="Special requirements, available times..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light rounded-pill px-4" @click="showWaitingModal = false">Cancel</button>
                            <button type="submit" class="btn btn-info rounded-pill px-4 text-white fw-bold" :disabled="submittingWaiting">
                                <span x-show="submittingWaiting" class="spinner-border spinner-border-sm me-2"></span>
                                <span x-text="submittingWaiting ? 'Processing...' : 'Add Student'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .text-main { color: var(--text-main); }
        .text-muted { color: var(--text-muted); }
        .bg-theme { background-color: var(--card-bg) !important; }
        .bg-light-theme { background-color: var(--input-bg) !important; }
        .border-theme { border-color: var(--card-border) !important; }
        .bg-info-subtle { background-color: rgba(0, 188, 212, 0.1); }
        .pagination .page-link { border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-muted); padding: 10px 15px; }
        .pagination .page-item.active .page-link { background-color: var(--app-primary-color); color: white; border-color: var(--app-primary-color); }
        .table-hover tbody tr:hover { background-color: var(--input-bg); transition: all 0.2s ease; }
        [x-cloak] { display: none !important; }
    </style>
@endpush
