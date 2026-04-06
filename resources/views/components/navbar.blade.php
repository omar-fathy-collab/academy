@php
    $user = $sharedData['auth']['user'];
    $isImpersonating = $sharedData['auth']['is_impersonating'] ?? false;
@endphp

<header class="top-navbar-v3 glass-navbar shadow-sm sticky-top" x-data="{ showProfile: false, searchQuery: '', showResults: false }">
    <div class="container-fluid h-100 d-flex align-items-center justify-content-between px-4">
        <div class="d-flex align-items-center">
            <button class="navbar-toggle-btn me-3" @click="isSidebarOpen = !isSidebarOpen">
                <i class="fas" :class="isSidebarOpen ? 'fa-align-right' : 'fa-align-left'"></i>
            </button>
        </div>

        <div class="d-flex align-items-center gap-3">
            {{-- Search Box --}}
            <div class="search-box-v3 d-none d-md-flex position-relative">
                <i class="fas fa-search opacity-50"></i>
                <input type="text" placeholder="Search..." x-model="searchQuery" @focus="showResults = true" @click.away="showResults = false">
                
                {{-- Search Results Placeholder (Logic would need AJAX later) --}}
                <template x-if="showResults && searchQuery.length >= 2">
                    <div class="premium-dropdown search-results-v3 shadow-xl show">
                        <div class="p-3 text-center text-muted tiny">Searching for <span class="fw-bold" x-text="searchQuery"></span>...</div>
                    </div>
                </template>
            </div>

            {{-- Theme Toggle --}}
            <button @click="toggleTheme()" class="theme-toggle shadow-sm">
                <i class="fas" :class="theme === 'light' ? 'fa-moon' : 'fa-sun'"></i>
            </button>

            {{-- Profile Dropdown --}}
            <div class="profile-btn-v3 profile-dropdown-container position-relative" @click="showProfile = !showProfile" @click.away="showProfile = false">
                <div class="profile-info-v3 me-2 d-none d-sm-block">
                    <span class="name">{{ $user['username'] ?? 'User' }}</span>
                    <span class="role">{{ $user['role'] ?? 'Guest' }}</span>
                </div>
                <img src="{{ $user['profile_photo_url'] ?? '/assets/user_image.jpg' }}" alt="" class="avatar">
                
                <div class="premium-dropdown shadow-xl" :class="showProfile ? 'show' : ''">
                    <div class="dropdown-header mb-2 px-3">
                        <p class="m-0 fw-bold">{{ $user['username'] }}</p>
                        <p class="m-0 tiny opacity-50">{{ $user['email'] }}</p>
                    </div>
                    <a href="{{ route('profile.index') }}" class="dropdown-item">My Account</a>
                    <div class="dropdown-divider"></div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger w-100 text-start border-0 bg-transparent">Sign Out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
