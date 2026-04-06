@extends('layouts.authenticated')

@section('title', 'Account Settings')

@section('content')
<div class="container-fluid py-5">
    <!-- Header Section -->
    <div class="row mb-5" data-aos="fade-down">
        <div class="col-12 px-4">
            <div class="d-flex align-items-center justify-content-between p-4 rounded-5 shadow-lg border border-white border-opacity-10" 
                 style="background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%); backdrop-filter: blur(20px);">
                <div>
                    <h1 class="h2 fw-black mb-1 theme-text-main" style="letter-spacing: -0.5px;">Account Settings</h1>
                    <p class="text-muted small mb-0">Manage your persona, security and preferences</p>
                </div>
                <div class="d-none d-md-flex gap-3">
                    <div class="text-end">
                        <div class="small fw-bold text-primary">Status</div>
                        <div class="small badge bg-success bg-opacity-10 text-success rounded-pill px-3 mt-1 border border-success border-opacity-25">Online & Active</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="container px-4 mb-4" data-aos="zoom-in">
        <div class="alert alert-success border-0 shadow-lg rounded-4 p-4 d-flex align-items-center bg-success bg-opacity-10 text-success border border-success border-opacity-20">
            <div class="rounded-circle bg-success bg-opacity-20 p-3 me-3">
                <i class="fas fa-check-circle fs-4"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0">Updated Successfully</h6>
                <span class="small opacity-75">{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif

    @php
        $profilePictureUrl = $user->profile->profile_picture_url ?? '/img/user_image.jpg';
        $profilePictureUrl = str_replace(['\\', ' '], ['/', ''], $profilePictureUrl); 
        $profilePictureUrl = trim($profilePictureUrl, '/');
        
        if (!empty($profilePictureUrl) && !preg_match('~^(https?://|/)~', $profilePictureUrl)) {
            if (file_exists(public_path($profilePictureUrl))) {
                $profilePictureUrl = asset($profilePictureUrl);
            } else {
                $profilePictureUrl = asset('storage/' . $profilePictureUrl);
            }
        } elseif (empty($profilePictureUrl)) {
            $profilePictureUrl = asset('img/user_image.jpg');
        }
    @endphp

    <script id="profile-data" type="application/json">
        @json([
            'profile_picture_preview' => $profilePictureUrl,
            'previewLoaded' => false
        ])
    </script>

    <div class="row g-5 px-4" x-data="Object.assign(JSON.parse(document.getElementById('profile-data').textContent), {
        previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                this.profile_picture_preview = URL.createObjectURL(file);
                this.previewLoaded = true;
            }
        }
    })">
        <!-- Profile Picture Sidebar -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-2xl rounded-5 h-100 overflow-hidden text-center sticky-top" 
                 style="background: var(--card-bg); top: 2rem; data-aos='fade-right'">
                <div class="card-body p-5 d-flex flex-column align-items-center position-relative">
                    <div class="position-absolute top-0 start-0 w-100" style="height: 120px; background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); opacity: 0.15; filter: blur(40px);"></div>
                    
                    <div class="position-relative mb-5 mt-3 group">
                        <div class="position-absolute top-50 start-50 translate-middle w-100 h-100 rounded-circle border border-2 border-primary border-dashed animate-pulse opacity-20" style="padding: 10px;"></div>
                        <img :src="profile_picture_preview" 
                             class="rounded-circle shadow-2xl object-fit-cover border border-4 border-white position-relative"
                             style="width: 180px; height: 180px; background-color: white; z-index: 10; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);" 
                             alt="Profile Picture"
                             :class="previewLoaded ? 'scale-105 shadow-primary-3d' : ''">
                        
                        <label for="profile_upload" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-lg transition-all hover-scale cursor-pointer" 
                               style="width: 48px; height: 48px; z-index: 20; border: 4px solid var(--card-bg);">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    
                    <h2 class="fw-black mb-1 theme-text-main" style="letter-spacing: -0.5px;">{{ $user->profile->nickname ?? $user->username }}</h2>
                    <p class="text-muted small mb-4 opacity-75">{{ $user->email }}</p>
                    
                    <div class="d-flex gap-2 mb-5">
                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-4 py-2 fw-bold border border-primary border-opacity-10">
                            <i class="fas fa-shield-alt me-2"></i>{{ $user->role->role_name ?? $user->role->name ?? 'User' }}
                        </span>
                        <span class="badge rounded-pill bg-info bg-opacity-10 text-info px-4 py-2 fw-bold border border-info border-opacity-10">
                             Lv. {{ $user->id }}
                        </span>
                    </div>
                    
                    <div class="w-100 space-y-4 text-start pt-4 border-top theme-border">
                        <div class="p-4 rounded-4 bg-light bg-opacity-50 theme-card-sub mb-3 border theme-border">
                            <div class="d-flex align-items-center text-muted">
                                <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3 text-primary shadow-sm">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small fw-black text-uppercase opacity-50 mb-1" style="font-size: 10px; letter-spacing: 1px;">Member Since</div>
                                    <div class="fw-bold theme-text-main">{{ $user->created_at->format('M d, Y') }}</div>
                                </div>
                            </div>
                        </div>

                        @if($user->profile && $user->profile->phone_number)
                        <div class="p-4 rounded-4 bg-light bg-opacity-50 theme-card-sub border theme-border">
                            <div class="d-flex align-items-center text-muted">
                                <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3 text-success shadow-sm">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small fw-black text-uppercase opacity-50 mb-1" style="font-size: 10px; letter-spacing: 1px;">Contact Info</div>
                                    <div class="fw-bold theme-text-main">{{ $user->profile->phone_number }}</div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Form Area -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-2xl rounded-5 theme-card" data-aos="fade-left">
                <div class="card-header bg-transparent border-bottom theme-border p-5">
                    <h3 class="fw-black mb-0 theme-text-main" style="letter-spacing: -0.5px;">Update Identity</h3>
                    <p class="text-muted small mb-0 mt-2">All changes will be logged for security audits.</p>
                </div>
                
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('POST')
                    
                    <input type="file" name="profile_picture" id="profile_upload" class="d-none" accept="image/*" @change="previewImage($event)">

                    <div class="card-body p-5">
                        <section class="mb-5 pb-4 border-bottom theme-border">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-primary text-white rounded-4 p-3 me-3 shadow-primary">
                                    <i class="fas fa-id-card fs-5"></i>
                                </div>
                                <h5 class="fw-black mb-0 theme-text-main">Personal Details</h5>
                            </div>
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Unique Username</label>
                                    <div class="input-group input-soft">
                                        <input type="text" name="username" class="form-control rounded-4 px-4 py-3" 
                                               value="{{ old('username', $user->username) }}" required placeholder="e.g. johndoe">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Active Email Address</label>
                                    <div class="input-group input-soft">
                                        <input type="email" name="email" class="form-control rounded-4 px-4 py-3" 
                                               value="{{ old('email', $user->email) }}" required placeholder="e.g. john@example.com">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Public Nickname</label>
                                    <div class="input-group input-soft">
                                        <input type="text" name="nickname" class="form-control rounded-4 px-4 py-3" 
                                               value="{{ old('nickname', $user->profile->nickname ?? '') }}" placeholder="e.g. John D.">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Verified Phone</label>
                                    <div class="input-group input-soft">
                                        <input type="text" name="phone_number" class="form-control rounded-4 px-4 py-3" 
                                               value="{{ old('phone_number', $user->profile->phone_number ?? '') }}" placeholder="+1 234 567 8900">
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Residential Address</label>
                                    <div class="input-group input-soft">
                                        <input type="text" name="address" class="form-control rounded-4 px-4 py-3" 
                                               value="{{ old('address', $user->profile->address ?? '') }}" placeholder="123 Main St, City, Country">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Date of Birth</label>
                                    <div class="input-group input-soft">
                                        <input type="date" name="date_of_birth" class="form-control rounded-4 px-4 py-3 text-inherit" 
                                               value="{{ old('date_of_birth', $user->profile->date_of_birth ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-danger text-white rounded-4 p-3 me-3 shadow-danger">
                                    <i class="fas fa-lock fs-5"></i>
                                </div>
                                <h5 class="fw-black mb-0 theme-text-main">Security Architecture</h5>
                            </div>
                            
                            <div class="alert alert-soft-primary rounded-4 border-0 p-4 mb-4 d-flex align-items-start shadow-sm">
                                <div class="me-3 mt-1"><i class="fas fa-info-circle fs-5 opacity-50"></i></div>
                                <div class="small fw-semibold">Protective Measure: Encryption keys will be regenerated upon password change. Leave blank to maintain current credentials.</div>
                            </div>
                            
                            <div class="row g-4">
                                <div class="col-md-12">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Existing Password</label>
                                    <div class="input-group input-soft">
                                        <input type="password" name="current_password" class="form-control rounded-4 px-4 py-3 text-inherit" 
                                               placeholder="Verify current password to proceed">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Vault Password</label>
                                    <div class="input-group input-soft">
                                        <input type="password" name="password" class="form-control rounded-4 px-4 py-3 text-inherit" 
                                               placeholder="Enter new strong password">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fs-7 fw-bold text-uppercase text-muted opacity-75 mb-2 ms-1">Repeat Vault Password</label>
                                    <div class="input-group input-soft">
                                        <input type="password" name="password_confirmation" class="form-control rounded-4 px-4 py-3 text-inherit" 
                                               placeholder="Confirm new credentials">
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                    
                    <div class="card-footer theme-card border-top theme-border p-5 d-flex justify-content-between align-items-center">
                        <span class="small text-muted opacity-50 fw-bold">Last Updated: {{ $user->updated_at->diffForHumans() }}</span>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-3 fw-black shadow-primary-lg transition-all hover-translate-y">
                            Commit Changes <i class="fas fa-arrow-right ms-2 fs-6 opacity-75"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    :root {
        --bs-primary: #3b82f6;
        --bs-primary-rgb: 59, 130, 246;
    }

    .fw-black { font-weight: 950 !important; }
    .fs-7 { font-size: 0.75rem !important; }
    .shadow-2xl { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important; }
    .shadow-primary { box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4) !important; }
    .shadow-primary-lg { box-shadow: 0 15px 30px -5px rgba(59, 130, 246, 0.5) !important; }
    .shadow-primary-3d { box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.1), 0 20px 40px -10px rgba(59, 130, 246, 0.4) !important; }
    .shadow-danger { box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4) !important; }

    .rounded-5 { border-radius: 2rem !important; }
    .rounded-4 { border-radius: 1.25rem !important; }

    .form-control {
        background: var(--bg-main) !important;
        border: 2px solid transparent !important;
        color: var(--text-main) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-weight: 600;
    }

    .form-control:focus {
        border-color: var(--bs-primary) !important;
        background: var(--card-bg) !important;
        box-shadow: 0 0 0 6px rgba(var(--bs-primary-rgb), 0.1) !important;
        transform: scale(1.01);
    }

    .form-control::placeholder {
        color: var(--text-main);
        opacity: 0.3;
    }

    .btn-primary {
        background: linear-gradient(to right, #3b82f6, #8b5cf6);
        border: none;
    }

    .btn-primary:hover {
        background: linear-gradient(to right, #2563eb, #7c3aed);
    }

    .alert-soft-primary {
        background: rgba(var(--bs-primary-rgb), 0.08);
        color: #3b82f6;
    }

    .theme-card { background-color: var(--card-bg) !important; }
    .theme-card-sub { background-color: rgba(var(--bs-primary-rgb), 0.03) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: rgba(0,0,0,0.05) !important; }
    [data-bs-theme="dark"] .theme-border { border-color: rgba(255,255,255,0.05) !important; }

    .hover-translate-y:hover { transform: translateY(-4px); }
    .hover-scale:hover { transform: scale(1.1); }
    
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 0.2; transform: translate(-50%, -50%) scale(1); }
        50% { opacity: 0.4; transform: translate(-50%, -50%) scale(1.05); }
    }

    .space-y-4 > * + * { margin-top: 1rem !important; }
</style>
@endpush
@endsection
