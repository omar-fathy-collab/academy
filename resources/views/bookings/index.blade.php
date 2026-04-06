@extends('layouts.authenticated')

@section('title', 'Academy Bookings')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="{
    ...ajaxTable(),
    filters: {{ json_encode($filters) }}
}" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">📅 Enrollment Enquiries</h2>
            <p class="text-muted mb-0">Manage and convert potential student bookings</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="{{ route('bookings.create') }}" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-plus me-2"></i> Register New Inquiry
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">Total Bookings</p>
                <h3 class="fw-bold mb-0"> {{ $stats['total_bookings'] }} </h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">New This Month</p>
                <h3 class="fw-bold mb-0 text-primary"> {{ $stats['new_this_month'] }} </h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">In Waiting Groups</p>
                <h3 class="fw-bold mb-0 text-success"> {{ $stats['in_waiting_groups'] }} </h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">Pending Contact</p>
                <h3 class="fw-bold mb-0 text-warning"> {{ $stats['pending_contact'] }} </h3>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 ajax-content" id="bookings-filters">
        <div class="card-body p-3">
            <form action="{{ route('bookings.index') }}" method="GET" class="row g-3 align-items-center ajax-form" @submit.prevent>
                <div class="col-md-5">
                    <div class="input-group border theme-border rounded-3 overflow-hidden theme-badge-bg">
                        <span class="input-group-text bg-transparent border-0 px-3">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-0 bg-transparent py-2 shadow-none theme-text-main" placeholder="Search by name, email, or phone..." value="{{ $filters['search'] ?? '' }}" @input.debounce.500ms="updateList">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="waiting_filter" class="form-select border theme-border rounded-3 theme-badge-bg theme-text-main py-2" @change="updateList">
                        <option value="">All Enrollment Statuses</option>
                        <option value="in_groups" {{ ($filters['waiting_filter'] ?? '') == 'in_groups' ? 'selected' : '' }}>Consolidated into Waiting Groups</option>
                        <option value="not_in_groups" {{ ($filters['waiting_filter'] ?? '') == 'not_in_groups' ? 'selected' : '' }}>Pending Distribution</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="button" class="btn btn-primary w-100 rounded-3 py-2 fw-bold" @click="updateList">Apply Filters</button>
                    <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary rounded-3 py-2 px-3 fw-bold"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden mb-5 position-relative">
        <!-- Loading Overlay -->
        <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div class="ajax-content" id="bookings-grid">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-start" dir="ltr">
                    <thead class="theme-badge-bg text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Basic Info</th>
                            <th class="py-3">Contact Info</th>
                            <th class="py-3">Date</th>
                            <th class="py-3">Status</th>
                            <th class="px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            <tr class="theme-border">
                                <td class="px-4">
                                    <div class="d-flex align-items-center text-start">
                                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-3">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold theme-text-main">{{ $booking->name }}</div>
                                            <div class="smaller text-muted">{{ $booking->age }} Years</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="smaller theme-text-main"><i class="fas fa-phone-alt me-1 text-muted"></i> {{ $booking->phone }}</div>
                                    <div class="smaller text-muted"><i class="far fa-envelope me-1"></i> {{ $booking->email }}</div>
                                </td>
                                <td>
                                    <div class="smaller theme-text-main">{{ Carbon\Carbon::parse($booking->date)->format('Y/m/d') }}</div>
                                    <div class="smaller text-muted">{{ $booking->time }}</div>
                                </td>
                                <td>
                                    @if($booking->student_id)
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 smaller">
                                            <i class="fas fa-check-circle me-1"></i> Added to Group
                                        </span>
                                    @else
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 smaller">
                                            <i class="far fa-clock me-1"></i> Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 text-end">
                                    <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                                        <a href="{{ route('bookings.edit', $booking->id) }}" class="btn btn-sm btn-light border-0 px-3" title="Edit">
                                            <i class="far fa-edit text-primary"></i>
                                        </a>
                                        @if(!$booking->student_id)
                                            <a href="{{ route('bookings.add-to-waiting-group-form', $booking->id) }}" class="btn btn-sm btn-light border-0 px-3 bg-primary bg-opacity-10" title="Add to Waiting Group">
                                                <i class="fas fa-user-plus text-primary"></i>
                                            </a>
                                        @endif
                                        <form action="{{ route('bookings.destroy', $booking->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this booking?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border-0 px-3" title="Delete">
                                                <i class="far fa-trash-alt text-danger"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <div class="fs-1 mb-3">🔍</div>
                                    <h5 class="fw-bold">No Bookings Found</h5>
                                    <p class="small">Try changing the search criteria or adding a new booking</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer theme-badge-bg border-top-0 p-4" @click="navigate">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.72rem; }
    .transition-hover:hover { transform: translateY(-3px); }
</style>
@endsection
