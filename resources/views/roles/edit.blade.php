@extends('layouts.authenticated')

@section('title', 'Edit Role: ' . $role->name)

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="roleForm({
    permissions: {{ json_encode($permissions) }},
    initialPermissions: {{ json_encode($rolePermissions) }}
})" x-cloak>
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('roles.index') }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border transition-hover">
            <i class="fas fa-arrow-left fa-lg"></i>
        </a>
        <div>
            <h2 class="fw-bold theme-text-main mb-1">Edit Role: <span class="text-primary" x-text="name"></span></h2>
            <p class="text-muted small mb-0">Adjust system access and permissions for this role</p>
        </div>
    </div>

    <form action="{{ route('roles.update', $role->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 theme-card p-4 h-100">
                    <h5 class="fw-bold mb-4 theme-text-main">Role Identity</h5>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Role Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="name" 
                            class="form-control rounded-3 py-2 theme-badge-bg theme-text-main theme-border shadow-none" 
                            placeholder="e.g., Academic Auditor" 
                            required 
                            x-model="name"
                        />
                        @error('name') <div class="text-danger smaller mt-1">{{ $message }}</div> @enderror
                        <p class="smaller text-muted mt-2 text-warning">Warning: Changing the name of major roles (like 'admin' or 'teacher') may break system logic.</p>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 p-3 mb-0 smaller shadow-sm">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            <h6 class="fw-bold mb-0 small">Quick Summary</h6>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <p class="mb-0">Selected Permissions:</p>
                            <h4 class="fw-bold text-primary mb-0" x-text="selectedPermissions.length"></h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden h-100">
                    <div class="card-header theme-badge-bg border-bottom-0 p-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-key text-warning me-2"></i> Permissions Grid</h5>
                        <div class="d-flex gap-2">
                            <button type="button" @click="selectAll()" class="btn btn-sm btn-outline-info rounded-pill fw-bold smaller px-3">Select All</button>
                            <button type="button" @click="selectNone()" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold smaller px-3">Clear All</button>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <template x-for="(groupPermissions, groupName) in permissions" :key="groupName">
                                <div class="col-md-6 col-xxl-4">
                                    <div class="p-3 rounded-4 border theme-border theme-badge-bg h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-bold text-uppercase smaller mb-0 theme-text-main text-truncate" x-text="groupName"></h6>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input ms-2" type="checkbox" @change="toggleGroup(groupName, $event.target.checked)" :checked="isGroupSelected(groupName)">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column gap-2 overflow-auto" style="max-height: 200px;">
                                            <template x-for="perm in groupPermissions" :key="perm.id">
                                                <div class="form-check">
                                                    <input 
                                                        class="form-check-input ms-0 me-2" 
                                                        type="checkbox" 
                                                        name="permissions[]" 
                                                        :id="'perm-'+perm.id" 
                                                        :value="perm.name" 
                                                        x-model="selectedPermissions"
                                                    >
                                                    <label class="form-check-label smaller text-muted cursor-pointer" :for="'perm-'+perm.id" x-text="perm.name.replace(groupName + '_', '').replace('_', ' ')"></label>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-4 text-end">
                <button type="submit" class="btn btn-primary fw-bold rounded-pill px-5 shadow-sm transition-hover py-3">
                    <i class="fas fa-save me-2"></i> Update Role & Permissions
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function roleForm(config) {
    return {
        name: "{{ $role->name }}",
        permissions: config.permissions,
        selectedPermissions: config.initialPermissions,
        
        selectAll() {
            this.selectedPermissions = [];
            Object.values(this.permissions).flat().forEach(p => {
                this.selectedPermissions.push(p.name);
            });
        },
        
        selectNone() {
            this.selectedPermissions = [];
        },
        
        toggleGroup(groupName, selected) {
            const groupPermNames = this.permissions[groupName].map(p => p.name);
            if (selected) {
                groupPermNames.forEach(name => {
                    if (!this.selectedPermissions.includes(name)) {
                        this.selectedPermissions.push(name);
                    }
                });
            } else {
                this.selectedPermissions = this.selectedPermissions.filter(name => !groupPermNames.includes(name));
            }
        },
        
        isGroupSelected(groupName) {
            const groupPermNames = this.permissions[groupName].map(p => p.name);
            return groupPermNames.length > 0 && groupPermNames.every(name => this.selectedPermissions.includes(name));
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
    .transition-hover:hover { transform: translateY(-3px); }
    .cursor-pointer { cursor: pointer; }
</style>
@endsection
