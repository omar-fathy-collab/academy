@extends('layouts.app')

@section('title', 'Welcome')

@push('styles')
<style>
    :root {
        --primary: #6c3fff;
        --primary-dark: #4d1fe8;
        --secondary: #00d4aa;
        --accent: #ff6b6b;
        --dark: #0a0e1a;
        --dark-2: #111627;
        --dark-3: #1a2035;
        --card-bg: rgba(255,255,255,0.04);
        --text-muted: rgba(255,255,255,0.55);
        --border: rgba(255,255,255,0.08);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html { scroll-behavior: smooth; }

    body {
        background: var(--dark);
        color: #fff;
        font-family: 'Outfit', sans-serif;
        overflow-x: hidden;
    }

    /* ─── NAVBAR ─── */
    .navbar-landing {
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 1000;
        padding: 1rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(10,14,26,0.75);
        backdrop-filter: blur(18px);
        border-bottom: 1px solid var(--border);
        transition: background .3s;
    }
    .navbar-landing .brand {
        display: flex; align-items: center; gap: .6rem;
        text-decoration: none;
    }
    .navbar-landing .brand-icon {
        width: 42px; height: 42px;
        background: #fff;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .navbar-landing .brand-name {
        font-size: 1.25rem;
        font-weight: 700;
        background: linear-gradient(90deg, #fff 40%, var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .navbar-landing .nav-links {
        display: flex; align-items: center; gap: 2rem;
        list-style: none;
    }
    .navbar-landing .nav-links a {
        color: var(--text-muted);
        text-decoration: none;
        font-size: .95rem;
        font-weight: 500;
        transition: color .2s;
    }
    .navbar-landing .nav-links a:hover { color: #fff; }
    .btn-nav-login {
        padding: .5rem 1.4rem;
        background: transparent;
        border: 1.5px solid var(--primary);
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        transition: background .25s, box-shadow .25s !important;
    }
    .btn-nav-login:hover {
        background: var(--primary) !important;
        box-shadow: 0 0 20px rgba(108,63,255,.4) !important;
        color: #fff !important;
    }

    /* ─── HERO ─── */
    .hero {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 6rem 1.5rem 4rem;
        position: relative;
        overflow: hidden;
    }
    .hero-bg {
        position: absolute; inset: 0; z-index: 0;
        background:
            radial-gradient(ellipse 80% 60% at 50% -10%, rgba(108,63,255,.35) 0%, transparent 70%),
            radial-gradient(ellipse 60% 50% at 80% 80%, rgba(0,212,170,.15) 0%, transparent 60%),
            var(--dark);
    }
    .hero-grid {
        position: absolute; inset: 0; z-index: 0;
        background-image:
            linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
        background-size: 60px 60px;
        mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, black, transparent);
    }
    .hero-content { position: relative; z-index: 1; max-width: 860px; }
    .hero-badge {
        display: inline-flex; align-items: center; gap: .5rem;
        background: rgba(108,63,255,.15);
        border: 1px solid rgba(108,63,255,.4);
        border-radius: 100px;
        padding: .35rem 1rem;
        font-size: .82rem;
        font-weight: 600;
        color: #b39dff;
        letter-spacing: .04em;
        text-transform: uppercase;
        margin-bottom: 1.8rem;
    }
    .hero-badge span { width:7px; height:7px; background: var(--secondary); border-radius: 50%; animation: pulse 1.8s infinite; }
    @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }

    .hero h1 {
        font-size: clamp(2.6rem, 6vw, 4.5rem);
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 1.4rem;
        letter-spacing: -.02em;
    }
    .hero h1 .gradient-text {
        background: linear-gradient(135deg, #a78bff 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero p {
        font-size: 1.15rem;
        color: var(--text-muted);
        max-width: 560px;
        margin: 0 auto 2.5rem;
        line-height: 1.7;
    }
    .hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
    .btn-primary-hero {
        padding: .85rem 2.2rem;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: .5rem;
        transition: transform .2s, box-shadow .2s;
        box-shadow: 0 8px 30px rgba(108,63,255,.4);
    }
    .btn-primary-hero:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 40px rgba(108,63,255,.6);
        color: #fff;
    }
    .btn-secondary-hero {
        padding: .85rem 2.2rem;
        background: rgba(255,255,255,.06);
        color: #fff;
        border: 1.5px solid rgba(255,255,255,.15);
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: .5rem;
        transition: background .2s, border-color .2s, transform .2s;
    }
    .btn-secondary-hero:hover {
        background: rgba(255,255,255,.1);
        border-color: rgba(255,255,255,.3);
        transform: translateY(-3px);
        color: #fff;
    }

    /* Stats strip */
    .stats-strip {
        display: flex; justify-content: center; gap: 3rem; flex-wrap: wrap;
        margin-top: 4rem;
        padding-top: 2.5rem;
        border-top: 1px solid var(--border);
    }
    .stat-item { text-align: center; }
    .stat-item .stat-num {
        font-size: 2rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fff 50%, var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .stat-item .stat-label { color: var(--text-muted); font-size: .85rem; margin-top: .2rem; }

    /* Floating cards */
    .floating-card {
        position: absolute;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: .9rem 1.2rem;
        backdrop-filter: blur(20px);
        display: flex; align-items: center; gap: .75rem;
        font-size: .85rem;
        white-space: nowrap;
        animation: float 5s ease-in-out infinite;
    }
    .floating-card.card-1 { top: 22%; left: 5%; animation-delay: 0s; }
    .floating-card.card-2 { top: 30%; right: 5%; animation-delay: 1.5s; }
    .floating-card.card-3 { bottom: 28%; left: 8%; animation-delay: .8s; }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
    .fc-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; }

    @media(max-width:768px){ .floating-card{display:none;} }

    /* ─── FEATURES ─── */
    .section {
        padding: 6rem 1.5rem;
        max-width: 1200px;
        margin: 0 auto;
    }
    .section-label {
        text-align: center;
        color: var(--secondary);
        font-size: .8rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: .8rem;
    }
    .section-title {
        text-align: center;
        font-size: clamp(1.8rem, 4vw, 2.8rem);
        font-weight: 800;
        margin-bottom: 1rem;
        letter-spacing: -.01em;
    }
    .section-desc {
        text-align: center;
        color: var(--text-muted);
        font-size: 1.05rem;
        max-width: 540px;
        margin: 0 auto 3.5rem;
        line-height: 1.7;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
        gap: 1.5rem;
    }
    .feature-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 2rem;
        transition: transform .3s, border-color .3s, box-shadow .3s;
        position: relative;
        overflow: hidden;
    }
    .feature-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform .3s;
    }
    .feature-card:hover { transform: translateY(-6px); border-color: rgba(108,63,255,.3); box-shadow: 0 20px 50px rgba(0,0,0,.3); }
    .feature-card:hover::before { transform: scaleX(1); }
    .feature-icon {
        width: 54px; height: 54px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 1.3rem;
    }
    .feature-card h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: .6rem; }
    .feature-card p { color: var(--text-muted); font-size: .92rem; line-height: 1.65; }

    /* ─── HOW IT WORKS ─── */
    .how-section { background: var(--dark-2); padding: 6rem 1.5rem; }
    .how-inner { max-width: 1100px; margin: 0 auto; }
    .steps-row {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 2rem;
        margin-top: 3.5rem;
    }
    .step-card {
        text-align: center;
        padding: 2rem 1.5rem;
    }
    .step-num {
        width: 60px; height: 60px;
        margin: 0 auto 1.3rem;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem;
        font-weight: 800;
        box-shadow: 0 8px 25px rgba(108,63,255,.4);
    }
    .step-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: .5rem; }
    .step-card p { color: var(--text-muted); font-size: .9rem; line-height: 1.6; }

    /* ─── CTA ─── */
    .cta-section {
        padding: 6rem 1.5rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .cta-section::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse 80% 80% at 50% 50%, rgba(108,63,255,.18) 0%, transparent 70%);
        pointer-events: none;
    }
    .cta-inner { position: relative; max-width: 700px; margin: 0 auto; }
    .cta-section h2 { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; margin-bottom: 1rem; }
    .cta-section p { color: var(--text-muted); font-size: 1.1rem; margin-bottom: 2.2rem; line-height: 1.7; }

    /* ─── FOOTER ─── */
    .footer {
        background: var(--dark-2);
        border-top: 1px solid var(--border);
        padding: 2rem 1.5rem;
        text-align: center;
    }
    .footer-links { display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .footer-links a { color: var(--text-muted); text-decoration: none; font-size: .9rem; transition: color .2s; }
    .footer-links a:hover { color: #fff; }
    .footer-copy { color: var(--text-muted); font-size: .82rem; }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--dark); }
    ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
</style>
@endpush

@section('body')

{{-- ─── NAVBAR ─── --}}
<nav class="navbar-landing" id="topNavbar">
    <a href="{{ route('welcome') }}" class="brand">
        <div class="brand-icon">
            <img src="{{ asset('img/shefae-logo.png') }}" alt="Shefae" style="width: 100%; height: 100%; object-fit: cover;">
        </div>
        <span class="brand-name">{{ config('app.name', 'Shefae') }}</span>
    </a>
    <ul class="nav-links d-none d-md-flex">
        <li><a href="#features">Features</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
        @if(Route::has('terms'))<li><a href="{{ route('terms') }}">Terms</a></li>@endif
        @if(Route::has('privacy-policy'))<li><a href="{{ route('privacy-policy') }}">Privacy</a></li>@endif
        <li><a href="{{ route('login') }}" class="btn-nav-login">Sign In</a></li>
    </ul>
    <a href="{{ route('login') }}" class="btn-nav-login d-md-none">Sign In</a>
</nav>

{{-- ─── HERO ─── --}}
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-grid"></div>

    {{-- Floating ambient cards --}}
    <div class="floating-card card-1" data-aos="fade-right" data-aos-delay="600">
        <div class="fc-icon" style="background:rgba(108,63,255,.2)">📚</div>
        <div>
            <div style="font-weight:700">Live Courses</div>
            <div style="color:var(--text-muted);font-size:.78rem">Instructor-led sessions</div>
        </div>
    </div>
    <div class="floating-card card-2" data-aos="fade-left" data-aos-delay="800">
        <div class="fc-icon" style="background:rgba(0,212,170,.15)">🏅</div>
        <div>
            <div style="font-weight:700">Certificates</div>
            <div style="color:var(--text-muted);font-size:.78rem">Verified credentials</div>
        </div>
    </div>
    <div class="floating-card card-3" data-aos="fade-right" data-aos-delay="1000">
        <div class="fc-icon" style="background:rgba(255,107,107,.15)">⚡</div>
        <div>
            <div style="font-weight:700">Real-time Progress</div>
            <div style="color:var(--text-muted);font-size:.78rem">Track every milestone</div>
        </div>
    </div>

    <div class="hero-content">
        <div class="hero-badge" data-aos="fade-down">
            <span></span>
            Academy Management System
        </div>
        <h1 data-aos="fade-up" data-aos-delay="100">
            Empower Learning,<br>
            <span class="gradient-text">Elevate Results</span>
        </h1>
        <p data-aos="fade-up" data-aos-delay="200">
            A complete platform to manage courses, students, schedules, assessments, and certifications — all in one streamlined dashboard.
        </p>
        <div class="hero-actions" data-aos="fade-up" data-aos-delay="300">
            <a href="{{ route('login') }}" class="btn-primary-hero" id="hero-get-started">
                <i class="fas fa-rocket"></i> Get Started
            </a>
            <a href="#features" class="btn-secondary-hero" id="hero-learn-more">
                <i class="fas fa-play-circle"></i> Learn More
            </a>
        </div>

        <div class="stats-strip" data-aos="fade-up" data-aos-delay="450">
            <div class="stat-item">
                <div class="stat-num">500+</div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">50+</div>
                <div class="stat-label">Courses Offered</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">98%</div>
                <div class="stat-label">Satisfaction Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">24/7</div>
                <div class="stat-label">Support Available</div>
            </div>
        </div>
    </div>
</section>

{{-- ─── FEATURES ─── --}}
<section class="section" id="features">
    <div class="section-label">Why Choose Us</div>
    <h2 class="section-title">Everything You Need to Run Your Academy</h2>
    <p class="section-desc">From enrollment to certification — manage every aspect of your educational institution with ease.</p>

    <div class="features-grid">
        <div class="feature-card" data-aos="fade-up" data-aos-delay="0">
            <div class="feature-icon" style="background:rgba(108,63,255,.18)">🎓</div>
            <h3>Course Management</h3>
            <p>Create and organize courses with sessions, materials, quizzes, and assignments in a structured, easy-to-navigate interface.</p>
        </div>
        <div class="feature-card" data-aos="fade-up" data-aos-delay="80">
            <div class="feature-icon" style="background:rgba(0,212,170,.15)">👥</div>
            <h3>Student Enrollment</h3>
            <p>Seamlessly register students, manage groups, track attendance, and keep complete academic profiles for every learner.</p>
        </div>
        <div class="feature-card" data-aos="fade-up" data-aos-delay="160">
            <div class="feature-icon" style="background:rgba(255,107,107,.15)">📅</div>
            <h3>Smart Scheduling</h3>
            <p>Manage rooms, timeslots, and instructors from a unified scheduling module that prevents conflicts automatically.</p>
        </div>
        <div class="feature-card" data-aos="fade-up" data-aos-delay="240">
            <div class="feature-icon" style="background:rgba(255,185,0,.15)">📝</div>
            <h3>Quizzes & Assessments</h3>
            <p>Build timed quizzes with auto-grading, manage assignments, and provide students with instant feedback on performance.</p>
        </div>
        <div class="feature-card" data-aos="fade-up" data-aos-delay="320">
            <div class="feature-icon" style="background:rgba(0,184,255,.15)">🏅</div>
            <h3>Digital Certificates</h3>
            <p>Auto-generate and issue branded certificates upon course completion, with PDF download and verification capabilities.</p>
        </div>
        <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
            <div class="feature-icon" style="background:rgba(138,255,107,.12)">📊</div>
            <h3>Reports & Analytics</h3>
            <p>Get actionable insights with detailed reports on revenue, attendance, student performance, and instructor productivity.</p>
        </div>
    </div>
</section>

{{-- ─── HOW IT WORKS ─── --}}
<div class="how-section" id="how-it-works">
    <div class="how-inner">
        <div class="section-label">Simple Process</div>
        <h2 class="section-title">How It Works</h2>
        <p class="section-desc">Get up and running in minutes with our intuitive onboarding flow.</p>

        <div class="steps-row">
            <div class="step-card" data-aos="fade-up" data-aos-delay="0">
                <div class="step-num">1</div>
                <h3>Create Your Account</h3>
                <p>Sign in with your credentials and set up your admin profile in seconds.</p>
            </div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="100">
                <div class="step-num">2</div>
                <h3>Set Up Courses</h3>
                <p>Add your courses, sessions, materials, and define the curriculum structure.</p>
            </div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="200">
                <div class="step-num">3</div>
                <h3>Enroll Students</h3>
                <p>Register learners individually or in bulk and assign them to the right groups.</p>
            </div>
            <div class="step-card" data-aos="fade-up" data-aos-delay="300">
                <div class="step-num">4</div>
                <h3>Track & Certify</h3>
                <p>Monitor progress in real-time and automatically issue certificates upon completion.</p>
            </div>
        </div>
    </div>
</div>

{{-- ─── CTA ─── --}}
<section class="cta-section">
    <div class="cta-inner" data-aos="zoom-in">
        <h2>Ready to Transform Your Academy?</h2>
        <p>Join hundreds of educators already using Shefae to deliver world-class learning experiences.</p>
        <a href="{{ route('login') }}" class="btn-primary-hero" id="cta-sign-in" style="display:inline-flex; font-size:1.05rem;">
            <i class="fas fa-arrow-right"></i> Sign In to Your Dashboard
        </a>
    </div>
</section>

{{-- ─── FOOTER ─── --}}
<footer class="footer">
    <div class="footer-links">
        @if(Route::has('help'))<a href="{{ route('help') }}">Help</a>@endif
        @if(Route::has('terms'))<a href="{{ route('terms') }}">Terms of Service</a>@endif
        @if(Route::has('privacy-policy'))<a href="{{ route('privacy-policy') }}">Privacy Policy</a>@endif
        <a href="{{ route('login') }}">Sign In</a>
    </div>
    <p class="footer-copy">&copy; {{ date('Y') }} {{ config('app.name', 'ICT Academy') }}. All rights reserved.</p>
</footer>

@push('scripts')
<script>
    // Navbar scroll effect
    const navbar = document.getElementById('topNavbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 60) {
            navbar.style.background = 'rgba(10,14,26,0.95)';
        } else {
            navbar.style.background = 'rgba(10,14,26,0.75)';
        }
    });
</script>
@endpush
@endsection
