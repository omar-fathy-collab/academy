@extends('layouts.authenticated')

@section('title', 'Create User')

@section('content')
    <script id="user-booking-data" type="application/json">
        {!! Js::from([
            'username' => old('username', $bookingData['name'] ?? ''),
            'nickname' => old('nickname', $bookingData['name'] ?? ''),
            'email' => old('email', $bookingData['email'] ?? ''),
            'phone_number' => old('phone_number', $bookingData['phone'] ?? ''),
            'date_of_birth' => old('date_of_birth', $bookingData['date_of_birth'] ?? ''),
            'address' => old('address', ''),
            'is_active' => (bool) old('is_active', 1),
            'spatie_roles' => old('spatie_roles', []),
        ]) !!}
    </script>
    <div class="container py-4 min-vh-100" x-data="Object.assign(JSON.parse(document.getElementById('user-booking-data').textContent), { 
        password: '',
        toggleRole(name) {
            if (this.spatie_roles.includes(name)) {
                this.spatie_roles = this.spatie_roles.filter(r => r !== name);
            } else {
                this.spatie_roles.push(name);
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
                        <h4 class="fw-bold mb-0 theme-text-main"><i class="fas fa-user-plus text-primary me-2"></i>Create New User</h4>
                        <p class="text-muted small mb-0">System access control and profile registration</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-body p-4">
                        @if($bookingData)
                            <div class="alert alert-info border-0 rounded-3 mb-4 shadow-sm">
                                <i class="fas fa-bookmark me-2"></i>
                                Pre-filled from booking #{{ $bookingData['booking_id'] }}
                            </div>
                        @endif

                        <form action="{{ route('users.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="booking_id" value="{{ $bookingData['booking_id'] ?? '' }}">
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Username <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-at text-primary"></i></span>
                                        <input type="text" name="username" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('username') is-invalid @enderror"
                                            x-model="username" required>
                                    </div>
                                    @error('username') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Display Name <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-user text-primary"></i></span>
                                        <input type="text" name="nickname" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('nickname') is-invalid @enderror"
                                            x-model="nickname" required>
                                    </div>
                                    @error('nickname') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-envelope text-primary"></i></span>
                                        <input type="email" name="email" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('email') is-invalid @enderror"
                                            x-model="email" required>
                                    </div>
                                    @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Password <span class="text-danger">*</span></label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-lock text-primary"></i></span>
                                        <input type="password" name="password" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit @error('password') is-invalid @enderror"
                                            x-model="password" required minlength="8">
                                    </div>
                                    @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Phone Number</label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-phone text-primary"></i></span>
                                        <input type="text" name="phone_number" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit"
                                            x-model="phone_number">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Date of Birth</label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-calendar-alt text-primary"></i></span>
                                        <input type="date" name="date_of_birth" class="form-control form-control-lg border-0 theme-input-bg rounded-end-3 text-inherit"
                                            x-model="date_of_birth">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Address</label>
                                    <div class="input-group theme-input-group">
                                        <span class="input-group-text border-0 theme-input-bg"><i class="fas fa-map-marker-alt text-primary"></i></span>
                                        <textarea name="address" class="form-control border-0 theme-input-bg rounded-end-3 text-inherit" x-model="address" rows="2"></textarea>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold small text-uppercase opacity-75">Assign System Roles <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach($spatieRoles as $role)
                                            <input type="checkbox" name="spatie_roles[]" value="{{ $role->name }}" 
                                                id="role_{{ $role->id }}" class="d-none" 
                                                x-model="spatie_roles">
                                            <label for="role_{{ $role->id }}" 
                                                class="badge px-4 py-2 rounded-pill border fw-bold cursor-pointer transition-all"
                                                :class="spatie_roles.includes('{{ $role->name }}') ? 'bg-primary text-white border-primary shadow-sm' : 'bg-light text-muted border-secondary opacity-75'"
                                                style="font-size: 0.9rem">
                                                {{ $role->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('spatie_roles') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch p-0 d-flex align-items-center gap-3">
                                        <input class="form-check-input ms-0" type="checkbox" name="is_active" id="is_active"
                                            x-model="is_active" style="width: 3rem; height: 1.5rem; cursor: pointer">
                                        <label class="form-check-label fw-bold theme-text-main" for="is_active">Active Account (User can login immediately)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-5 pt-4 border-top theme-border">
                                <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">
                                    <i class="fas fa-save me-2"></i> Create System User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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
        .form-control { border-radius: 0 0.75rem 0.75rem 0; }
        .btn-primary { background: linear-gradient(135deg, var(--bs-primary) 0%, #6610f2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(102, 16, 242, 0.3); }
    </style>
@endsection
