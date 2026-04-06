@extends('layouts.authenticated')

@section('title', 'Role Management')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="rolesPage({
    initialRoles: {{ json_encode($roles) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">🛡️ Role Management</h2>
            <p class="text-muted mb-0">Define system access levels and permissions</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="{{ route('roles.create') }}" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-plus me-2"></i> Create New Role
            </a>
        </div>
    </div>

    <!-- Search & Stats Summary -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 theme-card">
                <div class="card-body p-3">
                    <div class="input-group border theme-border rounded-3 overflow-hidden theme-badge-bg">
                        <span class="input-group-text bg-transparent border-0 px-3">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control border-0 bg-transparent py-2 shadow-none theme-text-main"
                            placeholder="Search roles by name..."
                            x-model="searchTerm"
                        />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-info h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle me-3">
                        <i class="fas fa-user-shield fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted smaller fw-bold text-uppercase mb-0">Total Roles</p>
                        <h4 class="fw-bold mb-0" x-text="roles.length"></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roles Grid -->
    <div class="row g-4">
        <template x-for="role in filteredRoles" :key="role.id">
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 theme-card h-100 transition-hover overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 me-3">
                                    <i class="fas fa-id-badge fa-lg"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold theme-text-main mb-1" x-text="role.name"></h5>
                                    <span class="badge bg-light text-muted border px-2 py-1 smaller">web guard</span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 theme-card">
                                    <li><a class="dropdown-item smaller" :href="route('roles.edit', role.id)"><i class="fas fa-edit me-2 text-warning"></i> Edit Permissions</a></li>
                                    <li><a class="dropdown-item smaller" :href="route('roles.users', role.id)"><i class="fas fa-users me-2 text-info"></i> View Assigned Users</a></li>
                                    <li><hr class="dropdown-divider theme-border"></li>
                                    <li>
                                        <button class="dropdown-item smaller text-danger" @click="handleDelete(role)" :disabled="isProtected(role.name)">
                                            <i class="fas fa-trash-alt me-2"></i> Delete Role
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <div class="p-3 theme-badge-bg rounded-4 border theme-border text-center">
                                    <h6 class="fw-bold mb-1" x-text="role.permissions_count || 0"></h6>
                                    <p class="smaller text-muted mb-0 text-uppercase fw-bold">Permissions</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 theme-badge-bg rounded-4 border theme-border text-center">
                                    <h6 class="fw-bold mb-1" x-text="role.users_count || 0"></h6>
                                    <p class="smaller text-muted mb-0 text-uppercase fw-bold">Users</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a :href="route('roles.edit', role.id)" class="btn btn-outline-primary w-100 rounded-pill fw-bold smaller py-2">
                                <i class="fas fa-lock-open me-2"></i> Permissions
                            </a>
                            <a :href="route('roles.users', role.id)" class="btn btn-outline-info w-100 rounded-pill fw-bold smaller py-2">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                        </div>
                    </div>
                    
                    <!-- Protected Badge -->
                    <template x-if="isProtected(role.name)">
                        <div class="position-absolute top-0 end-0 mt-2 me-2">
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning rounded-pill px-2 py-1 smaller">
                                <i class="fas fa-shield-alt me-1"></i> System
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- No Results -->
        <template x-if="filteredRoles.length === 0">
            <div class="col-12 text-center py-5">
                <div class="fs-1 mb-3">🔍</div>
                <h5 class="fw-bold text-muted">No roles found matching your search</h5>
                <p class="small text-muted">Try a different keyword or create a new role</p>
            </div>
        </template>
    </div>
</div>

<script>
function rolesPage(config) {
    return {
        roles: config.initialRoles,
        searchTerm: '',
        protectedRoles: ['super-admin', 'admin', 'teacher', 'student'],
        
        get filteredRoles() {
            if (!this.searchTerm) return this.roles;
            const term = this.searchTerm.toLowerCase();
            return this.roles.filter(r => r.name.toLowerCase().includes(term));
        },
        
        isProtected(name) {
            return this.protectedRoles.includes(name.toLowerCase());
        },
        
        route(name, id) {
            return window.route(name, id);
        },
        
        handleDelete(role) {
            if (this.isProtected(role.name)) {
                return Swal.fire('Error', 'Protected roles cannot be deleted', 'error');
            }
            
            Swal.fire({
                title: 'Delete Role?',
                text: `Are you sure you want to delete the "${role.name}" role? Users with this role may lose access.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.delete(this.route('roles.destroy', role.id))
                        .then(() => window.location.reload());
                }
            });
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
    .transition-hover:hover { transform: translateY(-5px); box-shadow: 0 1rem 3rem rgba(0,0,0,0.1) !important; }
</style>
@endsection
