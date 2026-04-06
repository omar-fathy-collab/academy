@extends('layouts.authenticated')

@section('title', "User — " . ($user->username))

@section('content')
    <div class="container py-4 min-vh-100" x-data="{}">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="d-flex align-items-center mb-4 gap-3">
                    <a href="{{ route('users.index') }}" class="btn btn-light border rounded-pill px-3 shadow-sm theme-card">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                    <div>
                        <h4 class="fw-bold mb-0 theme-text-main"><i class="fas fa-user-shield text-info me-2"></i>Account Overview</h4>
                        <p class="text-muted small mb-0">System credentials and profile information</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex flex-column flex-md-row align-items-center gap-4 mb-5 pb-4 border-bottom theme-border">
                            <div class="position-relative">
                                <img
                                    src="{{ $user->profile->profile_picture_url ?? asset('assets/user_image.jpg') }}"
                                    alt="Avatar"
                                    class="rounded-circle border border-4 border-white shadow-lg bg-white"
                                    style="width: 100px; height: 100px; object-fit: cover"
                                >
                                <span class="position-absolute bottom-0 end-0 bg-success border border-white border-2 rounded-circle" style="width: 18px; height: 18px; transform: translate(-10%, -10%);" x-show="{{ $user->is_active ? 'true' : 'false' }}"></span>
                            </div>
                            <div class="text-center text-md-start">
                                <h3 class="fw-bold mb-1 theme-text-main">{{ $user->profile->nickname ?? $user->username }}</h3>
                                <p class="text-muted mb-2"><i class="fas fa-envelope me-2 opacity-50"></i>{{ $user->email }}</p>
                                <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-2">
                                    <span class="badge rounded-pill {{ $user->is_active ? 'bg-success' : 'bg-secondary' }} px-3 py-2 shadow-sm">
                                        {{ $user->is_active ? 'Active Account' : 'Inactive Account' }}
                                    </span>
                                    <span class="badge rounded-pill bg-info text-dark px-3 py-2 shadow-sm">
                                        <i class="fas fa-user-tag me-1"></i> {{ $user->role->role_name ?? 'N/A' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 overflow-hidden">
                            @php
                                $items = [
                                    ['label' => 'Primary Role', 'value' => $user->role->role_name ?? 'N/A', 'icon' => 'fa-id-badge', 'color' => 'text-primary'],
                                    ['label' => 'Phone Number', 'value' => $user->profile->phone_number ?? 'Not provided', 'icon' => 'fa-phone', 'color' => 'text-success'],
                                    ['label' => 'Date of Birth', 'value' => $user->profile->date_of_birth ? \Carbon\Carbon::parse($user->profile->date_of_birth)->format('M d, Y') : 'Not provided', 'icon' => 'fa-calendar-alt', 'color' => 'text-info'],
                                    ['label' => 'Member Since', 'value' => $user->created_at->format('M d, Y'), 'icon' => 'fa-clock', 'color' => 'text-warning'],
                                ];
                            @endphp

                            @foreach($items as $item)
                                <div class="col-md-6">
                                    <div class="bg-light-theme rounded-4 p-4 h-100 border theme-border transition-all hover-shadow">
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="bg-white rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px">
                                                <i class="fas {{ $item['icon'] }} {{ $item['color'] }} small"></i>
                                            </div>
                                            <p class="text-muted small fw-bold mb-0 text-uppercase tracking-wider">{{ $item['label'] }}</p>
                                        </div>
                                        <p class="mb-0 fw-bold theme-text-main fs-5 px-1">{{ $item['value'] }}</p>
                                    </div>
                                </div>
                            @endforeach

                            <div class="col-12">
                                <div class="bg-light-theme rounded-4 p-4 border theme-border">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <div class="bg-white rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px">
                                            <i class="fas fa-map-marker-alt text-danger small"></i>
                                        </div>
                                        <p class="text-muted small fw-bold mb-0 text-uppercase tracking-wider">Living Address</p>
                                    </div>
                                    <p class="mb-0 fw-medium theme-text-main px-1">{{ $user->profile->address ?? 'No address information registered.' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-end mt-5 pt-4 border-top theme-border gap-3">
                            <a href="{{ route('users.index') }}" class="btn btn-light rounded-pill px-4 fw-bold order-2 order-sm-1">Back to Directory</a>
                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm order-1 order-sm-2">
                                <i class="fas fa-edit me-2"></i>Edit User Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .bg-light-theme { background-color: var(--bg-main) !important; }
        .tracking-wider { letter-spacing: 0.05em; }
        .hover-shadow:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important; transform: translateY(-3px); }
        .transition-all { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        
        .card { border-radius: 1.5rem; }
        .btn-primary { background: linear-gradient(135deg, var(--bs-primary) 0%, #6610f2 100%); border: none; }
        .btn-primary:hover { border: none; box-shadow: 0 8px 15px rgba(102, 16, 242, 0.3); }
    </style>
@endsection
