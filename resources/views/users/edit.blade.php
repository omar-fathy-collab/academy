@extends('layouts.authenticated')

@section('title', "Edit User: " . ($user->username))

@section('content')
    @php
        $profilePictureUrl = $user->profile->profile_picture_url ?? null;
        if (!$profilePictureUrl) {
            $profilePictureUrl = asset('assets/user_image.jpg');
        } else {
            $profilePictureUrl = str_replace(['\\', ' '], ['/', ''], $profilePictureUrl);
            $profilePictureUrl = trim($profilePictureUrl, '/');
            if (!preg_match('~^(https?://|/)~', $profilePictureUrl)) {
                if (file_exists(public_path($profilePictureUrl))) {
                    $profilePictureUrl = asset($profilePictureUrl);
                } else {
                    $profilePictureUrl = asset('storage/' . $profilePictureUrl);
                }
            }
        }
        $userData = [
            'username' => old('username', $user->username),
            'nickname' => old('nickname', $user->profile->nickname ?? ''),
            'email' => old('email', $user->email),
            'phone_number' => old('phone_number', $user->profile->phone_number ?? ''),
            'date_of_birth' => old('date_of_birth', $user->profile->date_of_birth ?? ''),
            'address' => old('address', $user->profile->address ?? ''),
            'is_active' => (bool) old('is_active', $user->is_active),
            'spatie_roles' => old('spatie_roles', $user->getRoleNames()),
            'profile_picture_preview' => $profilePictureUrl,
        ];
    @endphp

    <script id="user-data" type="application/json">
        @json($userData)
    </script>

    <div class="container py-4 min-vh-100" x-data="Object.assign(JSON.parse(document.getElementById('user-data').textContent), { 
        pass: '',
        toggleRole(name) {
            if (this.spatie_roles.includes(name)) {
                this.spatie_roles = this.spatie_roles.filter(r => r !== name);
            } else {
                this.spatie_roles.push(name);
            }
        },

        previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                this.profile_picture_preview = URL.createObjectURL(file);
            }
        }
    })">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="d-flex align-items-center mb-4 gap-3">
                    <a href="{{ route('users.index') }}" class="btn btn-light border rounded-pill px-3 shadow-sm theme-card">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                    <div>
                        <h4 class="fw-bold mb-0 theme-text-main"><i class="fas fa-user-edit text-warning me-2"></i>Edit User Profile</h4>
                        <p class="text-muted small mb-0">Update account settings and personal information</p>
                    </div>
                </div>

                <form action="{{ route('users.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    
                    <div class="row g-4">
                        <!-- Sidebar: Profile Picture & Status -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 text-center sticky-top" style="top: 2rem">
                                <div class="position-relative d-inline-block mb-4">
                                    <img
                                        :src="profile_picture_preview"
                                        alt="Avatar"
                                        class="rounded-circle border border-4 border-white shadow-lg bg-white"
                                        style="width: 151px; height: 151px; object-fit: cover"
                                    >
                                    <label for="profile_picture" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-3 shadow-sm cursor-pointer" style="transform: translate(15%, 15%)">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                    <input type="file" name="profile_picture" id="profile_picture" class="d-none" accept="image/*" @change="previewImage($event)">
                                </div>
                                <h5 class="fw-bold theme-text-main mb-1" x-text="nickname || username"></h5>
                                <p class="text-muted small mb-4">User ID: #{{ $user->id }}</p>

                                <div class="form-check form-switch p-0 d-flex flex-column align-items-center gap-2 border-top theme-border pt-4">
                                    <input class="form-check-input ms-0" type="checkbox" name="is_active" id="is_active"
                                        x-model="is_active" :value="is_active ? 1 : 0" style="width: 3.5rem; height: 1.75rem; cursor: pointer">
                                    <label class="form-check-label fw-bold theme-text-main" for="is_active">Account Status: <span :class="is_active ? 'text-success' : 'text-danger'" x-text="is_active ? 'Active' : 'Inactive'"></span></label>
                                </div>
                            </div>
                        </div>

                        <!-- Main Form Area -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm rounded-4 theme-card h-100">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold text-uppercase small text-muted mb-4 pb-2 border-bottom theme-border">Account Information</h6>
                                    <div class="row g-4 mb-5">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-muted">Username <span class="text-danger">*</span></label>
                                            <div class="input-group theme-input-group">
                                                <span class="input-group-text border-0 theme-input-bg small"><i class="fas fa-at text-primary"></i></span>
                                                <input type="text" name="username" class="form-control border-0 theme-input-bg rounded-end-3 text-inherit @error('username') is-invalid @enderror"
                                                    x-model="username" required>
                                            </div>
                                            @error('username') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-muted">Email Address <span class="text-danger">*</span></label>
                                            <div class="input-group theme-input-group">
                                                <span class="input-group-text border-0 theme-input-bg small"><i class="fas fa-envelope text-primary"></i></span>
                                                <input type="email" name="email" class="form-control border-0 theme-input-bg rounded-end-3 text-inherit @error('email') is-invalid @enderror"
                                                    x-model="email" required>
                                            </div>
                                            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label fw-bold small text-muted">Change Password <small class="fw-normal">(Leave blank to keep current)</small></label>
                                            <div class="input-group theme-input-group">
                                                <span class="input-group-text border-0 theme-input-bg small"><i class="fas fa-key text-primary"></i></span>
                                                <input type="password" name="pass" class="form-control border-0 theme-input-bg rounded-end-3 text-inherit @error('pass') is-invalid @enderror"
                                                    placeholder="********">
                                            </div>
                                            <div class="small text-muted mt-1">Minimum 8 chars with at least one capital letter.</div>
                                            @error('pass') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    <h6 class="fw-bold text-uppercase small text-muted mb-4 pb-2 border-bottom theme-border">Profile Details</h6>
                                    <div class="row g-4 mb-5">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-muted">Full Display Name <span class="text-danger">*</span></label>
                                            <div class="input-group theme-input-group">
                                                <span class="input-group-text border-0 theme-input-bg small"><i class="fas fa-id-card text-primary"></i></span>
                                                <input type="text" name="nickname" class="form-control border-0 theme-input-bg rounded-end-3 text-inherit @error('nickname') is-invalid @enderror"
                                                    x-model="nickname" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-muted">Phone Number</label>
                                            <div class="input-group theme-input-group">
                                                <span class="input-group-text border-0 theme-input-bg small"><i class="fas fa-phone text-primary"></i></span>
                                                <input type="text" name="phone_number" class="form-control border-0 theme-input-bg rounded-end-3 text-inherit"
                                                    x-model="phone_number">
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-bold small text-muted">Resident Address</label>
                                            <div class="input-group theme-input-group">
                                                <span class="input-group-text border-0 theme-input-bg small"><i class="fas fa-map-marked-alt text-primary"></i></span>
                                                <textarea name="address" class="form-control border-0 theme-input-bg rounded-end-3 text-inherit" x-model="address" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <h6 class="fw-bold text-uppercase small text-muted mb-4 pb-2 border-bottom theme-border">Access Control</h6>
                                    <div class="col-12 mb-4">
                                        <label class="form-label fw-bold small text-muted mb-3">Roles & Permissions <span class="text-danger">*</span></label>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($spatieRoles as $role)
                                                <input type="checkbox" name="spatie_roles[]" value="{{ $role->name }}" 
                                                    id="role_{{ $role->id }}" class="d-none" 
                                                    x-model="spatie_roles">
                                                <label for="role_{{ $role->id }}" 
                                                    class="badge px-3 py-2 rounded-pill border fw-bold cursor-pointer transition-all"
                                                    :class="spatie_roles.includes('{{ $role->name }}') ? 'bg-primary text-white border-primary shadow-sm' : 'bg-light text-muted border-secondary opacity-75'">
                                                    {{ $role->name }}
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('spatie_roles') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-5">
                                        <a href="{{ route('users.index') }}" class="btn btn-light rounded-pill px-4 fw-bold">Cancel</a>
                                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                            <i class="fas fa-save me-2"></i> Update Profile
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-input-bg { background-color: var(--bg-main) !important; color: var(--text-main) !important; }
        .text-inherit { color: inherit !important; }
        .cursor-pointer { cursor: pointer; }
        .transition-all { transition: all 0.2s ease-in-out; }
        
        .card { border-radius: 1.25rem; }
        .input-group-text { border-radius: 0.75rem 0 0 0.75rem; color: #64748b; background-color: var(--bg-main) !important; }
        .form-control { border-radius: 0 0.75rem 0.75rem 0; font-size: 0.95rem; }
        .btn-primary { background: linear-gradient(135deg, var(--bs-primary) 0%, #6610f2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(102, 16, 242, 0.3); }
    </style>
@endsection
