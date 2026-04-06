@php
    $siteName = $sharedData['global_settings']['site_name'] ?? 'Shefae';
    $siteLogo = $sharedData['global_settings']['site_logo'] ?? '/img/shefae-logo.png';
    $user = $sharedData['auth']['user'];
    $permissions = $user['permissions'] ?? [];
    $role = strtolower($user['role'] ?? '');
    
    $hasPermission = function($permission) use ($user, $permissions, $role) {
        if ($user['isAdminFull'] || $role === 'super-admin' || $role === 'admin') {
            return true;
        }
        return in_array($permission, $permissions);
    };
@endphp

<aside class="main-sidebar-v3 shadow-lg" 
       x-data="{ 
            openMenus: { 
                academyCore: true, 
                academicOps: true, 
                financial: true, 
                systemAdmin: true,
                instructor: true, 
                student: true 
            },
            init() {
                try {
                    const saved = localStorage.getItem('sidebar-menus');
                    if (saved) Object.assign(this.openMenus, JSON.parse(saved));
                } catch (e) { console.error('Sidebar state reset due to error'); }
            },
            toggleMenu(menu) {
                this.openMenus[menu] = !this.openMenus[menu];
                localStorage.setItem('sidebar-menus', JSON.stringify(this.openMenus));
            }
       }">
    <div class="sidebar-blur-bg"></div>
    <div class="sidebar-content-v3">
        <div class="sidebar-brand-v3 py-4 text-center">
            <a href="{{ url('/') }}" class="d-inline-block p-1 bg-white rounded-circle shadow-sm overflow-hidden" style="width: 70px; height: 70px;">
                <img src="{{ $siteLogo }}" alt="Logo" class="w-100 h-100 object-contain" />
            </a>
            <h5 class="mt-3 fs-6 fw-bold text-white opacity-90 text-uppercase tracking-widest">{{ $siteName }}</h5>
        </div>

        <nav class="sidebar-nav-v3 mt-2 px-3 custom-scrollbar">
            @if($hasPermission('view_dashboard'))
                <div class="nav-section-label">General</div>
                <a href="{{ route('dashboard') }}" class="nav-link-v3 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-th-large"></i> <span>Overview</span>
                </a>
            @endif

            {{-- Academy Core --}}
            @if($hasPermission('view_courses') || $hasPermission('view_students') || $hasPermission('view_teachers'))
                <div class="nav-section-divider"></div>
                <div class="nav-section-label d-flex justify-content-between cursor-pointer" @click="toggleMenu('academyCore')">
                    Academy Core <i class="fas fa-chevron-down transition-all" :class="openMenus['academyCore'] ? 'rotate-180' : ''"></i>
                </div>
                <div class="collapsible-nav" :class="openMenus['academyCore'] ? 'open' : ''">
                    @if($hasPermission('view_courses'))
                        <a href="{{ route('courses.index') }}" class="nav-link-v3 {{ request()->routeIs('courses.*') ? 'active' : '' }}"><i class="fas fa-book"></i> <span>Courses</span></a>
                        <a href="{{ route('enrollment-requests.index') }}" class="nav-link-v3 {{ request()->routeIs('enrollment-requests.*') ? 'active' : '' }}"><i class="fas fa-user-plus"></i> <span>Enrollment Requests</span></a>
                        <a href="{{ route('subcourses.index') }}" class="nav-link-v3 {{ request()->routeIs('subcourses.*') ? 'active' : '' }}"><i class="fas fa-list-ol"></i> <span>Sub-Courses</span></a>
                    @endif
                    @if($hasPermission('view_students'))
                        <a href="{{ route('students.index') }}" class="nav-link-v3 {{ request()->routeIs('students.*') ? 'active' : '' }}"><i class="fas fa-user-graduate"></i> <span>Student Hub</span></a>
                    @endif
                    @if($hasPermission('view_teachers'))
                        <a href="{{ route('teachers.index') }}" class="nav-link-v3 {{ request()->routeIs('teachers.*') ? 'active' : '' }}"><i class="fas fa-chalkboard-teacher"></i> <span>Instructors</span></a>
                    @endif
                    @if($hasPermission('manage_groups'))
                        <a href="{{ route('groups.index') }}" class="nav-link-v3 {{ request()->routeIs('groups.*') ? 'active' : '' }}"><i class="fas fa-users"></i> <span>Study Groups</span></a>
                    @endif
                    @if($hasPermission('view_courses'))
                        <a href="{{ route('admin.library') }}" class="nav-link-v3 {{ request()->routeIs('admin.library') ? 'active' : '' }}"><i class="fas fa-book-atlas"></i> <span>Academy Library</span></a>
                    @endif
                </div>
            @endif

            {{-- Academic Ops --}}
            @if($hasPermission('manage_schedules') || $hasPermission('view_assignments'))
                <div class="nav-section-divider"></div>
                <div class="nav-section-label d-flex justify-content-between cursor-pointer" @click="toggleMenu('academicOps')">
                    Academic Ops <i class="fas fa-chevron-down transition-all" :class="openMenus['academicOps'] ? 'rotate-180' : ''"></i>
                </div>
                <div class="collapsible-nav" :class="openMenus['academicOps'] ? 'open' : ''">
                    @if($hasPermission('manage_schedules'))
                        <a href="{{ route('schedules.index') }}" class="nav-link-v3 {{ request()->routeIs('schedules.*') ? 'active' : '' }}"><i class="fas fa-calendar-alt"></i> <span>Timetable</span></a>
                    @endif
                    @if($hasPermission('view_assignments'))
                        <a href="{{ route('assignments.index') }}" class="nav-link-v3 {{ request()->routeIs('assignments.*') ? 'active' : '' }}"><i class="fas fa-file-signature"></i> <span>Assignments</span></a>
                    @endif
                    @if($hasPermission('manage_schedules'))
                        <a href="{{ route('certificate_requests.index') }}" class="nav-link-v3 {{ request()->routeIs('certificate_requests.*') ? 'active' : '' }}">
                            <i class="fas fa-certificate"></i> <span>Certificate Requests</span>
                        </a>
                    @endif
                </div>

            @endif

            {{-- Financials --}}
            @if($hasPermission('view_financials') || $hasPermission('view_vault'))
                <div class="nav-section-divider"></div>
                <div class="nav-section-label d-flex justify-content-between cursor-pointer" @click="toggleMenu('financial')">
                    Financials <i class="fas fa-chevron-down transition-all" :class="openMenus['financial'] ? 'rotate-180' : ''"></i>
                </div>
                <div class="collapsible-nav" :class="openMenus['financial'] ? 'open' : ''">
                    @if($hasPermission('view_invoices'))
                        <a href="{{ route('invoices.index') }}" class="nav-link-v3 {{ request()->routeIs('invoices.*') ? 'active' : '' }}"><i class="fas fa-file-invoice-dollar"></i> <span>Billing</span></a>
                    @endif
                    @if($hasPermission('view_salaries'))
                        <a href="{{ route('salaries.index') }}" class="nav-link-v3 {{ request()->routeIs('salaries.*') ? 'active' : '' }}"><i class="fas fa-coins"></i> <span>Salaries</span></a>
                    @endif
                    @if($hasPermission('view_expenses'))
                        <a href="{{ route('expenses.index') }}" class="nav-link-v3 {{ request()->routeIs('expenses.*') ? 'active' : '' }}"><i class="fas fa-money-bill-wave"></i> <span>Expenses</span></a>
                    @endif
                    @if($hasPermission('view_vault'))
                        <a href="{{ route('admin.vault.index') }}" class="nav-link-v3 {{ request()->routeIs('admin.vault.*') ? 'active' : '' }}"><i class="fas fa-vault"></i> <span>Vault</span></a>
                    @endif
                </div>
            @endif

            {{-- System Admin --}}
            @if($hasPermission('view_users') || $hasPermission('manage_roles') || $hasPermission('manage_rooms') || $hasPermission('view_logs') || $hasPermission('manage_settings'))
                <div class="nav-section-divider"></div>
                <div class="nav-section-label d-flex justify-content-between cursor-pointer" @click="toggleMenu('systemAdmin')">
                    System Admin <i class="fas fa-chevron-down transition-all" :class="openMenus['systemAdmin'] ? 'rotate-180' : ''"></i>
                </div>
                <div class="collapsible-nav" :class="openMenus['systemAdmin'] ? 'open' : ''">
                    @if($hasPermission('view_users'))
                        <a href="{{ route('users.index') }}" class="nav-link-v3 {{ request()->routeIs('users.*') ? 'active' : '' }}"><i class="fas fa-users-cog"></i> <span>Users</span></a>
                    @endif
                    @if($hasPermission('manage_roles'))
                        <a href="{{ route('roles.index') }}" class="nav-link-v3 {{ request()->routeIs('roles.*') ? 'active' : '' }}"><i class="fas fa-user-shield"></i> <span>Roles</span></a>
                    @endif
                    @if($hasPermission('manage_rooms'))
                        <a href="{{ route('rooms.index') }}" class="nav-link-v3 {{ request()->routeIs('rooms.*') ? 'active' : '' }}"><i class="fas fa-door-open"></i> <span>Rooms</span></a>
                    @endif
                    @if($hasPermission('view_logs'))
                        <a href="{{ route('activities.index') }}" class="nav-link-v3 {{ request()->routeIs('activities.*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>
                    @endif
                    @if($hasPermission('manage_settings'))
                        <a href="{{ route('settings.index') }}" class="nav-link-v3 {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="fas fa-cog"></i> <span>Settings</span></a>
                    @endif
                </div>
            @endif

            {{-- Instructor Section --}}
            @if($hasPermission('be_teacher'))
                <div class="nav-section-divider"></div>
                <div class="nav-section-label text-white-50 small text-uppercase fw-bold mb-2">Teaching Dashboard</div>
                
                <a href="{{ route('teacher.dashboard') }}" class="nav-link-v3 {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-desktop"></i> <span>Overview</span>
                </a>

                <a href="{{ route('assignments.index') }}" class="nav-link-v3 {{ request()->routeIs('assignments.index') ? 'active' : '' }}">
                    <i class="fas fa-file-signature"></i> <span>My Assignments</span>
                </a>

                <a href="{{ route('quizzes.index') }}" class="nav-link-v3 {{ (request()->routeIs('quizzes.*') && !request()->routeIs('quizzes.attempts')) ? 'active' : '' }}">
                    <i class="fas fa-brain"></i> <span>My Quizzes</span>
                </a>

                <a href="{{ route('sessions.materials.index') }}" class="nav-link-v3 {{ request()->routeIs('sessions.materials.index') ? 'active' : '' }}">
                    <i class="fas fa-folder-open"></i> <span>My Materials</span>
                </a>

                <a href="{{ route('assignments.view_submissions') }}" class="nav-link-v3 {{ request()->routeIs('assignments.view_submissions') || request()->routeIs('assignments.show') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-check"></i> <span>Marking Hub</span>
                </a>

                <a href="{{ route('teacher.certificates.index') }}" class="nav-link-v3 {{ request()->routeIs('teacher.certificates.*') ? 'active' : '' }}">
                    <i class="fas fa-medal"></i> <span>My Achievements</span>
                </a>
            @endif



            {{-- Student Section --}}
            @if($hasPermission('be_student'))
                <div class="nav-section-divider"></div>
                <div class="nav-section-label">My Learning</div>
                <a href="{{ route('student.dashboard.index') }}" class="nav-link-v3 {{ (request()->routeIs('student.dashboard.*') || request()->routeIs('student.dashboard')) ? 'active' : '' }}"><i class="fas fa-home"></i> <span>Academy Home</span></a>
                <a href="{{ route('student.my_groups') }}" class="nav-link-v3 {{ (request()->routeIs('student.my_groups') || request()->routeIs('student.group_details')) ? 'active' : '' }}"><i class="fas fa-users"></i> <span>My Groups</span></a>
                <a href="{{ route('student.my_sessions') }}" class="nav-link-v3 {{ (request()->routeIs('student.my_sessions') || request()->routeIs('student.session_details')) ? 'active' : '' }}"><i class="fas fa-calendar-alt"></i> <span>My Sessions</span></a>
                <a href="{{ route('student.library') }}" class="nav-link-v3 {{ request()->routeIs('student.library') ? 'active' : '' }}"><i class="fas fa-book-reader"></i> <span>Digital Library</span></a>

                <div class="nav-section-label">Tasks & Records</div>
                <a href="{{ route('student.assignments') }}" class="nav-link-v3 {{ (request()->routeIs('student.assignments') || request()->routeIs('student.submit_assignment')) ? 'active' : '' }}"><i class="fas fa-tasks"></i> <span>Assignments</span></a>
                <a href="{{ route('student.quizzes') }}" class="nav-link-v3 {{ (request()->routeIs('student.quizzes') || request()->routeIs('student.take_quiz') || request()->routeIs('student.my_quizzes')) ? 'active' : '' }}"><i class="fas fa-brain"></i> <span>Quizzes</span></a>
                <a href="{{ route('student.certificates.index') }}" class="nav-link-v3 {{ request()->routeIs('student.certificates.index') ? 'active' : '' }}"><i class="fas fa-certificate"></i> <span>Certificates</span></a>
                
                <div class="nav-section-label">Financial</div>
                <a href="{{ route('student.payments') }}" class="nav-link-v3 {{ (request()->routeIs('student.payments') || request()->routeIs('student.payments.history') || request()->routeIs('student.invoice.view')) ? 'active' : '' }}"><i class="fas fa-file-invoice-dollar"></i> <span>My Invoices</span></a>
            @endif

            <div class="nav-section-divider"></div>
            <div class="nav-section-label">Account</div>
            <a href="{{ route('profile.index') }}" class="nav-link-v3 {{ request()->routeIs('profile.index') ? 'active' : '' }}"><i class="fas fa-user-cog"></i> <span>Account Settings</span></a>
        </nav>
    </div>
</aside>
