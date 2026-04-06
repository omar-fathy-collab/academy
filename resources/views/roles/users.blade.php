@extends('layouts.authenticated')

@section('title', 'Users with Role: ' . $role->name)

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="ajaxTable()" x-cloak>
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('roles.index') }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border transition-hover">
            <i class="fas fa-arrow-left fa-lg"></i>
        </a>
        <div>
            <h2 class="fw-bold theme-text-main mb-1">Users Assigned to: <span class="text-primary">{{ $role->name }}</span></h2>
            <p class="text-muted small mb-0">Auditing system members with this access level</p>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden ajax-content position-relative" id="role-users-grid">
        <!-- Loading Overlay -->
        <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div class="card-header theme-badge-bg border-bottom-0 p-4">
            <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-users text-primary me-2"></i> Members List</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="theme-badge-bg text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="py-3">Email</th>
                            <th class="py-3">Joined Date</th>
                            <th class="px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr class="theme-border">
                                <td class="px-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                                            <i class="fas fa-user-circle fa-lg"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold theme-text-main">{{ $user->username }}</div>
                                            <div class="smaller text-muted">ID: #{{ $user->id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $user->email }}</span>
                                </td>
                                <td>
                                    <span class="text-muted smaller">{{ $user->created_at->format('M d, Y') }}</span>
                                </td>
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-light border theme-border rounded-pill px-3 shadow-sm fw-bold">
                                            Edit User
                                        </a>
                                        <a href="{{ route('users.show', $user->id) }}" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-eye text-primary"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <div class="fs-1 mb-3">👥</div>
                                    <h5 class="fw-bold">No users found with this role</h5>
                                    <p class="small">Assign this role to users via the user management module.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        @if($users->hasPages())
            <div class="card-footer theme-badge-bg border-top-0 p-4" @click="navigate">
                {{ $users->links() }}
            </div>
        @endif
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
