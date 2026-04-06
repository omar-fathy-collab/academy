@extends('layouts.app')

@section('body')
    @php
        $user = $sharedData['auth']['user'];
        $isImpersonating = $sharedData['auth']['is_impersonating'] ?? false;
        $primaryColor = $sharedData['global_settings']['primary_color'] ?? '#0d6efd';
    @endphp

    <div class="premium-lms-v3 no-transition" 
         :class="{
             'dark-theme': theme === 'dark',
             'light-theme': theme !== 'dark',
             'layout-expanded': isSidebarOpen,
             'layout-collapsed': !isSidebarOpen
         }"
         x-data="{ 
            isSidebarOpen: localStorage.getItem('sidebar-open') !== 'false',
            init() {
                this.$watch('isSidebarOpen', value => {
                    localStorage.setItem('sidebar-open', value);
                    document.documentElement.classList.toggle('sidebar-expanded', value);
                    document.documentElement.classList.toggle('sidebar-collapsed', !value);
                });
                {{-- Remove no-transition after initial render --}}
                setTimeout(() => this.$el.classList.remove('no-transition'), 50);
            }
         }">
        
        <x-flash-messages />

        @if($isImpersonating)
            <div class="impersonation-banner-fixed shadow-lg d-flex align-items-center justify-content-between px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="pulse-indicator"></div>
                    <span class="fw-bold text-white small">
                        <i class="fas fa-user-secret me-2"></i>
                        You are now browsing as <span class="text-warning">{{ $user['username'] ?? $user['name'] }}</span>
                    </span>
                </div>
                <form action="{{ route('impersonate.stop') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-warning rounded-pill px-3 py-1 fw-bold transition-all hover-scale">
                        <i class="fas fa-power-off me-2"></i> Stop Impersonation
                    </button>
                </form>
            </div>
        @endif

        <x-sidebar :sharedData="$sharedData" />

        <div class="content-container-v3">
            <x-navbar :sharedData="$sharedData" />

            <main class="main-content-v3 mt-4">
                <div class="container-fluid px-4 pb-5">
                    @yield('content')
                </div>
            </main>
            
            <x-premium-footer :sharedData="$sharedData" />
        </div>

        <x-payment-modal />

        <style>
            :root { 
                --app-primary-color: {{ $primaryColor }}; 
                --sidebar-v3-width: 280px; 
                --glass-navbar-height: 70px; 
                --glass-blur: 15px; 
                --accent-gradient: linear-gradient(135deg, {{ $primaryColor }} 0%, #0b5ed7 100%); 
                
                {{-- Default (Light) Theme Variables --}}
                --bg-main: #f8fafc; 
                --sidebar-bg: #0a0f1d; 
                --nav-bg: rgba(255, 255, 255, 0.85); 
                --text-main: #1e293b; 
                --border-color: rgba(0, 0, 0, 0.05);
                --card-bg: #ffffff;
            }

            {{-- Instant Theme Application (No JS Flash) --}}
            [data-bs-theme="dark"] { 
                --bg-main: #131b2e; 
                --sidebar-bg: #0a0f1d; 
                --nav-bg: rgba(19, 27, 46, 0.85); 
                --text-main: #f8fafc; 
                --border-color: rgba(255, 255, 255, 0.08);
                --card-bg: #1e293b;
            }

            .premium-lms-v3 { background: var(--bg-main); color: var(--text-main); min-height: 100vh; font-family: 'Plus Jakarta Sans', 'Cairo', sans-serif; display: flex; width: 100%; }
            
            .main-sidebar-v3 { 
                width: var(--sidebar-v3-width); 
                height: 100vh; 
                position: fixed; 
                left: 0; 
                top: 0; 
                z-index: 1040; 
                background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0d1226 100%); 
                color: #fff; 
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
                overflow: hidden; 
                border-right: 1px solid rgba(255, 255, 255, 0.05);
                box-shadow: 20px 0 50px rgba(0,0,0,0.15);
            }

            .sidebar-blur-bg { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 0% 0%, rgba(13, 110, 253, 0.2), transparent 50%); }
            .sidebar-content-v3 { position: relative; height: 100%; display: flex; flex-direction: column; z-index: 2; }
            .sidebar-nav-v3 { flex: 1; overflow-y: auto; padding-bottom: 2rem; scrollbar-width: none; }
            .sidebar-nav-v3::-webkit-scrollbar { display: none; }

            .nav-link-v3 { 
                display: flex; 
                align-items: center; 
                padding: 0.85rem 1.25rem; 
                margin: 0.35rem 0.75rem; 
                color: rgba(255, 255, 255, 0.5); 
                text-decoration: none !important; 
                border-radius: 12px; 
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
                font-size: 0.88rem;
                font-weight: 500;
                letter-spacing: 0.01em;
            }
            .nav-link-v3 i { width: 34px; font-size: 1.15rem; opacity: 0.6; transition: all 0.25s; }
            .nav-link-v3:hover { color: #fff; background: rgba(255, 255, 255, 0.08); transform: translateX(4px); }
            .nav-link-v3:hover i { opacity: 1; color: var(--app-primary-color); }
            
            .nav-link-v3.active { 
                background: linear-gradient(90deg, var(--app-primary-color) 0%, #3b82f6 100%); 
                color: #fff; 
                box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3); 
                font-weight: 600;
            }
            .nav-link-v3.active i { opacity: 1; color: #fff; }
            
            .content-container-v3 { 
                flex: 1; 
                margin-left: var(--sidebar-v3-width); 
                transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
                min-width: 0; 
                background: var(--bg-main);
                position: relative;
            }

            .collapsible-nav {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.35s ease-out;
            }
            .collapsible-nav.open {
                max-height: 1000px; /* large enough for menus */
                transition: max-height 0.5s ease-in;
            }

            .nav-section-label { 
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                color: rgba(255, 255, 255, 0.65);
                font-weight: 800;
                padding: 0.85rem 1.25rem;
                margin: 1.5rem 0.75rem 0.5rem;
                border-radius: 10px;
                display: flex;
                align-items: center;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .nav-section-label.cursor-pointer:hover {
                color: #fff;
                background: rgba(255, 255, 255, 0.06);
            }
            .nav-section-label i {
                font-size: 0.8rem;
                opacity: 0.8;
            }

            {{-- Instant Layout State (No JS Jump) --}}
            html.sidebar-collapsed .main-sidebar-v3, .layout-collapsed .main-sidebar-v3 { transform: translateX(-100%); }
            html.sidebar-collapsed .content-container-v3, .layout-collapsed .content-container-v3 { margin-left: 0; }
            
            .top-navbar-v3 { height: var(--glass-navbar-height); background: var(--nav-bg); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border-bottom: 1px solid var(--border-color); z-index: 1030; }
            .navbar-toggle-btn { width: 40px; height: 40px; border: none; background: rgba(13, 110, 253, 0.1); color: var(--app-primary-color); border-radius: 10px; transition: all 0.2s; }
            .navbar-toggle-btn:hover { background: rgba(13, 110, 253, 0.2); transform: scale(1.05); }
            
            .search-box-v3 { background: rgba(0, 0, 0, 0.05); padding: 0.5rem 1.25rem; border-radius: 50px; width: 300px; display: flex; align-items: center; gap: 10px; border: 1px solid transparent; transition: all 0.2s; }
            .search-box-v3:focus-within { background: var(--card-bg); border-color: var(--app-primary-color); box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1); }
            [data-bs-theme="dark"] .search-box-v3 { background: rgba(255, 255, 255, 0.05); }
            
            .premium-dropdown { background: var(--card-bg) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
            .theme-toggle { background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border-color); }
            .navbar-toggle-btn { width: 40px; height: 40px; border: none; background: rgba(13, 110, 253, 0.1); color: var(--app-primary-color); border-radius: 10px; }
            .search-box-v3 { background: rgba(0, 0, 0, 0.05); padding: 0.5rem 1.25rem; border-radius: 50px; width: 300px; display: flex; align-items: center; gap: 10px; }
            .search-box-v3 input { border: none; background: transparent; width: 100%; outline: none; }
            .profile-btn-v3 { display: flex; align-items: center; cursor: pointer; padding: 5px; border-radius: 50px; }
            .profile-btn-v3 .avatar { width: 38px; height: 38px; border-radius: 50%; border: 2px solid #fff; }
            .premium-dropdown { position: absolute; top: calc(100% + 15px); right: 0; background: var(--card-bg); border-radius: 15px; min-width: 240px; padding: 10px; border: 1px solid var(--border-color); transform: translateY(10px); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 1080; }
            .premium-dropdown.show { transform: translateY(0); opacity: 1; visibility: visible; }
            .dropdown-item { padding: 10px 15px; border-radius: 10px; text-decoration: none !important; color: inherit; display: block; }
            .theme-toggle { width: 40px; height: 40px; border-radius: 12px; border: none; background: var(--card-bg); display: flex; align-items: center; justify-content: center; color: #64748b; }
            .rotate-180 { transform: rotate(180deg); }
            .cursor-pointer { cursor: pointer; }
            @media (max-width: 991.98px) { .main-sidebar-v3 { transform: translateX(-100%); } .content-container-v3 { margin-left: 0 !important; } .layout-expanded .main-sidebar-v3 { transform: translateX(0); } }
            .impersonation-banner-fixed { background: linear-gradient(90deg, #dc3545 0%, #a71d2a 100%); z-index: 2000; position: fixed; top: 0; left: 0; right: 0; height: 40px; border-bottom: 2px solid rgba(255,215,0,0.3); }
            @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); } }
            .pulse-indicator { width: 10px; height: 10px; background: #ffc107; border-radius: 50%; animation: pulse 2s infinite; }
            .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
            .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        </style>
    </div>

@endsection
