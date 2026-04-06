<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الحضور | WiFi Check-in</title>
    <!-- Google Fonts: Cairo & Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- AlpineJS -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        :root {
            --primary: #0d6efd;
            --primary-gradient: linear-gradient(135deg, #0d6efd, #0dcaf0);
            --bg-main: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
        }

        body {
            font-family: 'Cairo', 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 15px;
        }

        .checkin-card {
            background: var(--card-bg);
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .card-header-gradient {
            background: var(--primary-gradient);
            padding: 45px 20px;
            text-align: center;
            color: white;
            position: relative;
        }

        .icon-circle {
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .btn-checkin {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 20px rgba(13, 110, 253, 0.3);
        }

        .btn-checkin:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(13, 110, 253, 0.4);
            color: white;
        }

        .btn-checkin:active {
            transform: translateY(-1px);
        }

        .btn-checkin:disabled {
            background: #cbd5e1;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        .pulse-effect {
            position: relative;
        }
        
        .pulse-effect::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: 20px;
            background: var(--primary);
            z-index: -1;
            animation: pulse-ring 2s infinite;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            70% { transform: scale(1.05); opacity: 0; }
            100% { transform: scale(0.95); opacity: 0; }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 20px;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .session-info-box {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="{
    loading: false,
    message: null,
    error: null,
    success: false,

    async submitCheckIn() {
        this.loading = true;
        this.message = null;
        this.error = null;
        this.success = false;

        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        const method = token ? 'qr' : 'wifi';
        
        let coords = { lat: null, lng: null };

        // For QR method, or as a general enhancement, we try to get location
        if (navigator.geolocation) {
            try {
                const pos = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 6000 });
                });
                coords.lat = pos.coords.latitude;
                coords.lng = pos.coords.longitude;
            } catch (e) {
                console.warn('Location access denied or timed out');
            }
        }

        try {
            const response = await fetch('/s/{{ $session->uuid ?? $session->session_id }}/check-in', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ 
                    token: token,
                    method: method,
                    lat: coords.lat,
                    lng: coords.lng
                })
            });
            const data = await response.json();
            
            if (response.ok) {
                this.success = true;
                this.message = data.message || 'تم تسجيل حضورك بنجاح!';
            } else {
                this.error = data.error || 'فشل تسجيل الحضور.';
            }
        } catch (e) {
            this.error = 'تأكد من اتصالك بالإنترنت وتفعيل الموقع ثم حاول مرة أخرى.';
        } finally {
            this.loading = false;
        }
    }
}">
    <div class="checkin-card">
        <div class="card-header-gradient">
            <div class="icon-circle">
                <i class="fas fa-qrcode fa-3x"></i>
            </div>
            <h3 class="fw-bold mb-1">تسجيل الحضور الذكي</h3>
            <p class="opacity-75 small mb-0">Smart Attendance System</p>
        </div>
        
        <div class="p-4">
            <div class="text-center">
                <span class="status-badge">
                    <i class="fas fa-calendar-check me-2"></i> حصة اليوم | Today's Session
                </span>
                
                <div class="session-info-box">
                    <h5 class="fw-bold text-dark mb-2">{{ $session->topic }}</h5>
                    <div class="d-flex justify-content-center gap-3 text-muted small">
                        <span><i class="fas fa-layer-group me-1"></i> {{ $session->group->group_name }}</span>
                        <span><i class="fas fa-clock me-1"></i> {{ $session->start_time }}</span>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            <div x-show="success" x-cloak x-transition.scale.origin.top class="alert alert-success border-0 rounded-4 p-4 mb-4 text-center">
                <div class="mb-2">
                    <i class="fas fa-check-circle fa-3x text-success"></i>
                </div>
                <h5 class="fw-bold mb-1" x-text="message"></h5>
                <p class="small mb-0 opacity-75">شكراً لك، تم إثبات وجودك.</p>
            </div>

            <!-- Error Message -->
            <div x-show="error" x-cloak x-transition.fade class="alert alert-danger border-0 rounded-4 p-3 mb-4 text-center">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                    <i class="fas fa-exclamation-circle text-danger"></i>
                    <strong class="small">خطأ في التسجيل</strong>
                </div>
                <span x-text="error" class="small fw-bold"></span>
            </div>

            <!-- Action Button -->
            <div x-show="!success">
                <div class="mb-4 text-center">
                    <p class="text-muted small mb-1">تأكد من تواجدك داخل القاعة</p>
                    <p class="fw-bold small text-primary">Confirm your physical presence</p>
                </div>
                
                <div :class="!loading ? 'pulse-effect' : ''">
                    <button 
                        @click="submitCheckIn()" 
                        :disabled="loading"
                        class="btn btn-checkin"
                    >
                        <span x-show="!loading">
                            <i class="fas fa-fingerprint me-2"></i> تسجيل حضوري الآن
                        </span>
                        <div x-show="loading" class="d-flex align-items-center justify-content-center gap-2">
                            <span class="spinner-border spinner-border-sm"></span>
                            <span>جاري التحقق...</span>
                        </div>
                    </button>
                </div>
            </div>

            <div x-show="success" x-cloak class="text-center mt-3">
                <a href="/dashboard" class="btn btn-link text-decoration-none fw-bold">
                    <i class="fas fa-home me-1"></i> العودة للرئيسية
                </a>
            </div>
        </div>

        <div class="bg-light p-3 text-center border-top">
            <p class="extra-small text-muted mb-0 fw-bold">
                &copy; {{ date('Y') }} Academy System | نظام الحضور الذكي
            </p>
        </div>
    </div>
</body>
</html>
