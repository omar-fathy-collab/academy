@extends('layouts.authenticated')

@section('title', 'User Management')

@section('content')
    <div class="container-fluid py-4 min-vh-100" x-data='{ 
        ...ajaxTable(),
        search: "{{ request("search") }}",
        roleFilter: "{{ request("role_id") }}",
        
        handleDelete(id) {
            if (confirm("Are you sure you want to delete this user?")) {
                const form = document.createElement("form");
                form.method = "POST";
                form.action = `/users/${id}`;
                form.innerHTML = `
                    @csrf
                    @method("DELETE")
                `;
                document.body.appendChild(form);
                form.submit();
            }
        },

        handleImpersonate(id) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = `/users/${id}/impersonate`;
            form.innerHTML = `
                @csrf
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }'>
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1">👥 User Management</h2>
                <p class="text-muted mb-0 opacity-75">Manage system access, roles, and user profiles</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                <i class="fas fa-plus-circle me-2"></i> Create New User
            </a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 theme-card mb-4 ajax-content" id="users-filters">
            <div class="card-body p-4">
                <form class="row g-3 ajax-form" action="{{ route('users.index') }}" method="GET" @submit.prevent>
                    <div class="col-md-5">
                        <div class="input-group theme-input rounded-pill px-3 py-2 border shadow-none">
                            <span class="input-group-text border-0 bg-transparent text-muted"><i class="fas fa-search"></i></span>
                            <input
                                type="text"
                                name="search"
                                class="form-control border-0 bg-transparent shadow-none text-inherit"
                                placeholder="Search by name, email, phone..."
                                value="{{ request('search') }}"
                                @input.debounce.500ms="updateList"
                            >
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select
                            name="role_id"
                            class="form-select rounded-pill px-4 py-2 theme-input border shadow-none text-inherit"
                            @change="updateList"
                        >
                            <option value="" {{ request('role_id') == '' ? 'selected' : '' }}>All Roles</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-secondary w-100 rounded-pill py-2 fw-bold shadow-sm" @click="updateList">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 theme-card overflow-hidden position-relative">
            <!-- Loading Overlay -->
            <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
                <div class="spinner-border text-primary" role="status"></div>
            </div>

            <div class="ajax-content" id="users-grid">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="theme-thead small text-uppercase fw-bold">
                                <tr>
                                    <th class="px-4 py-3">User Details</th>
                                    <th class="px-4 py-3">Primary Role</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3">Joined Date</th>
                                    <th class="px-4 py-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    <tr class="theme-tr">
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-3 position-relative">
                                                    <img
                                                        src="{{ $user->profile->profile_picture_url ?? asset('assets/user_image.jpg') }}"
                                                        alt="Profile"
                                                        class="rounded-circle border border-2 border-white shadow-sm"
                                                        width="48"
                                                        height="48"
                                                        style="object-fit: cover"
                                                    >
                                                    @if($user->is_active)
                                                        <span class="position-absolute bottom-0 end-0 bg-success border border-white border-2 rounded-circle" style="width: 12px; height: 12px"></span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <div class="fw-bold theme-text-main">{{ $user->profile->nickname ?? $user->username }}</div>
                                                    <div class="small text-muted opacity-75">{{ $user->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge rounded-pill bg-light text-dark border px-3 py-2 fw-medium shadow-none">
                                                <i class="fas fa-user-tag me-1 opacity-50"></i> {{ $user->role->role_name ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="badge rounded-pill {{ $user->is_active ? 'bg-success' : 'bg-danger' }} px-3 py-2 shadow-sm">
                                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-muted small fw-medium">
                                            {{ $user->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                @if(Auth::user()->isAdminFull && Auth::id() !== $user->id)
                                                    <button
                                                        @click="handleImpersonate({{ $user->id }})"
                                                        class="btn btn-sm btn-outline-warning rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm"
                                                        style="width: 32px; height: 32px"
                                                        title="Impersonate User"
                                                    >
                                                        <i class="fas fa-user-secret small"></i>
                                                    </button>
                                                @endif
                                                <a href="{{ route('users.edit', $user->id) }}"
                                                class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm"
                                                style="width: 32px; height: 32px"
                                                title="Edit User">
                                                    <i class="fas fa-pen small"></i>
                                                </a>
                                                @if(Auth::id() !== $user->id)
                                                    <button
                                                        @click="handleDelete({{ $user->id }})"
                                                        class="btn btn-sm btn-outline-danger rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm"
                                                        style="width: 32px; height: 32px"
                                                        title="Delete User"
                                                    >
                                                        <i class="fas fa-trash-alt small"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <div class="fs-1 mb-3 opacity-25">🔍</div>
                                            <h5 class="fw-bold">No users found</h5>
                                            <p class="small">Try adjusting your filters or search term</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($users->hasPages())
                    <div class="card-footer theme-card-footer p-4 border-top" @click="navigate">
                        {{ $users->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-card-footer { background-color: var(--card-bg) !important; }
        .theme-input { background-color: var(--bg-main) !important; border-color: var(--border-color) !important; }
        .theme-thead { background-color: var(--bg-main) !important; color: var(--text-muted) !important; }
        .theme-tr { border-bottom: 1px solid var(--border-color) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .text-inherit { color: inherit !important; }
        
        .btn-primary { background: linear-gradient(135deg, var(--bs-primary) 0%, #6610f2 100%); border: none; transition: transform 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); }
        .table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.05) !important; transition: all 0.2s ease; }
        .avatar { transition: transform 0.2s; }
        tr:hover .avatar { transform: scale(1.1); }
    </style>
@endsection
