@extends('layouts.app')

@section('title', 'Login | Shefae')

@section('body')
    <div class="premium-auth-container min-vh-100 d-flex align-items-center justify-content-center p-4" dir="ltr" 
         x-data="{ 
            login: '{{ old('login') }}',
            showPassword: false,
            get loginTypeInfo() {
                if (this.login.includes('@') && this.login.includes('.')) {
                    return { text: 'Login with Email', color: '#10b981' };
                } else if (/^[0-9+\-\s\(\)]+$/.test(this.login)) {
                    return { text: 'Login with Phone Number', color: '#3b82f6' };
                } else if (this.login.length > 0) {
                    return { text: 'Login with Username', color: '#8b5cf6' };
                }
                return { text: 'Enter Email, Username, or Phone', color: '#64748b' };
            }
         }">

        {{-- Abstract Background Shapes --}}
        <div class="bg-shape shape-1"></div>
        <div class="bg-shape shape-2"></div>
        <div class="bg-shape shape-3"></div>

        <div class="auth-card-glass shadow-2xl position-relative z-1" style="max-width: 440px; width: 100%;">
            <div class="text-center mb-4 pb-2">
                <div class="logo-pulse-wrapper mb-3 mx-auto shadow-sm rounded-circle d-flex align-items-center justify-content-center bg-white" style="width: 80px; height: 80px; padding: 10px;">
                    <img src="{{ asset('img/shefae-logo.png') }}" alt="Shefae Logo" class="img-fluid" style="object-fit: contain; width: 100%; height: 100%;">
                </div>
                <h2 class="fw-900 mb-1 text-dark" style="letter-spacing: -0.5px;">Welcome Back</h2>
                <p class="text-secondary small">Access your personalized learning dashboard</p>
            </div>

            @if(session('status'))
                <div class="alert alert-success border-0 rounded-3 shadow-sm py-2 mb-4 small fw-medium">
                    <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger border-0 rounded-3 shadow-sm py-2 mb-4 small fw-medium text-start">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="auth-form">
                @csrf
                <div class="form-floating-custom mb-4 text-start">
                    <label class="text-muted small fw-bold mb-2 d-block text-start">Account ID</label>
                    <div class="input-group-premium">
                        <span class="premium-icon"><i class="fas fa-user"></i></span>
                        <input
                            type="text"
                            name="login"
                            class="form-control premium-input text-start"
                            placeholder="Email, Username, or Phone"
                            x-model="login"
                            required
                        >
                    </div>
                    <div class="mt-2 small fw-medium auth-hint-transition text-start" style="font-size: 0.75rem;" :style="{ color: loginTypeInfo.color }" x-text="loginTypeInfo.text"></div>
                </div>

                <div class="form-floating-custom mb-4 text-start">
                    <label class="text-muted small fw-bold mb-2 d-block text-start">Password</label>
                    <div class="input-group-premium">
                        <span class="premium-icon"><i class="fas fa-lock"></i></span>
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            name="pass"
                            class="form-control premium-input text-start"
                            placeholder="••••••••"
                            required
                        >
                        <button
                            type="button"
                            class="premium-icon-btn"
                            @click="showPassword = !showPassword"
                            tabindex="-1"
                        >
                            <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 pb-2">
                    <div class="form-check custom-checkbox">
                        <input
                            type="checkbox"
                            name="remember"
                            class="form-check-input"
                            id="remember"
                        >
                        <label class="form-check-label small fw-medium text-secondary ms-2" style="padding-top: 2px; cursor: pointer;" for="remember">Remember Me</label>
                    </div>
                    <a href="{{ url('/help') }}" class="small fw-bold text-decoration-none premium-link">Forgot Password?</a>
                </div>

                <button
                    type="submit"
                    class="btn premium-btn-primary w-100 py-3 mb-4 shadow-lg d-flex justify-content-center align-items-center gap-2"
                >
                    <span>Login</span>
                    <i class="fas fa-arrow-right"></i>
                </button>

                <div class="position-relative text-center my-4">
                    <hr class="divider-line" />
                    <span class="divider-text small fw-bold text-muted px-3 text-uppercase">Or continue with</span>
                </div>

                <div class="d-flex gap-3 mb-4">
                    <a href="{{ route('auth.google') }}" class="btn premium-btn-social flex-fill d-flex align-items-center justify-content-center gap-2 py-2">
                        <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" alt="Google" style="width: 18px;">
                        <span class="small fw-bold text-dark">Google</span>
                    </a>
                    <a href="{{ route('auth.github') }}" class="btn premium-btn-social flex-fill d-flex align-items-center justify-content-center gap-2 py-2">
                        <i class="fab fa-github fs-5 text-dark"></i>
                        <span class="small fw-bold text-dark">GitHub</span>
                    </a>
                </div>

                <div class="text-center mt-4">
                    <p class="text-secondary small fw-medium mb-0">
                        New to Shefae? <a href="{{ route('register') }}" class="fw-bold premium-link text-decoration-none">Create an account</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <style>
        .premium-auth-container {
            background-color: #f8fafc;
            position: relative;
            overflow: hidden;
            font-family: 'Outfit', 'Inter', sans-serif;
        }
        .bg-shape {
            position: absolute; border-radius: 50%; filter: blur(80px); z-index: 0; opacity: 0.6; animation: float 20s infinite alternate;
        }
        .shape-1 { width: 500px; height: 500px; background: rgba(59, 130, 246, 0.3); top: -100px; left: -100px; }
        .shape-2 { width: 400px; height: 400px; background: rgba(139, 92, 246, 0.3); bottom: -50px; right: -100px; animation-delay: -5s; }
        .shape-3 { width: 300px; height: 300px; background: rgba(16, 185, 129, 0.2); bottom: 20%; left: 20%; animation-delay: -10s; }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(50px, 30px) scale(1.1); } 100% { transform: translate(-30px, 50px) scale(0.9); } }
        .auth-card-glass {
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 24px; padding: 3rem 2.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .logo-pulse-wrapper { animation: subtlePulse 3s infinite; }
        @keyframes subtlePulse { 0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.2); } 70% { box-shadow: 0 0 0 15px rgba(59, 130, 246, 0); } 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); } }
        .input-group-premium {
            display: flex; align-items: center; background: #f1f5f9; border-radius: 12px; border: 2px solid transparent; transition: all 0.2s ease; overflow: hidden;
        }
        .input-group-premium:focus-within { background: #fff; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        .premium-icon { padding: 0 1rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; }
        .premium-input { border: none; background: transparent; padding: 0.85rem 1rem 0.85rem 0; box-shadow: none !important; font-weight: 500; color: #0f172a; width: 100%; }
        .premium-icon-btn { border: none; background: transparent; padding: 0 1rem; color: #94a3b8; cursor: pointer; }
        .premium-btn-primary { background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); border: none; color: white; border-radius: 12px; font-weight: 700; transition: all 0.3s ease; }
        .premium-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4); }
        .premium-btn-social { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; transition: all 0.2s; }
        .premium-link { color: #3b82f6; font-weight: 700; }
        .divider-line { border-color: #cbd5e1; opacity: 1; }
        .divider-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 0 1rem; font-size: 0.7rem; }
        [data-bs-theme="dark"] .premium-auth-container { background-color: #0f172a; }
        [data-bs-theme="dark"] .auth-card-glass { background: rgba(30, 41, 59, 0.7); border-color: rgba(255,255,255,0.08); }
        [data-bs-theme="dark"] .logo-pulse-wrapper { background: #1e293b !important; }
        [data-bs-theme="dark"] .input-group-premium { background: #1e293b; }
        [data-bs-theme="dark"] .premium-input { color: #f8fafc; }
        [data-bs-theme="dark"] .premium-btn-social { background: #1e293b; border-color: #334155; }
        [data-bs-theme="dark"] .divider-text { background: #1e293b; color: #94a3b8 !important; }
    </style>
@endsection
