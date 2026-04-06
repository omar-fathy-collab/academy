@php
    $siteName = $sharedData['global_settings']['site_name'] ?? 'Shefae';
    $siteLogo = $sharedData['global_settings']['site_logo'] ?? '/img/shefae-logo.png';
    $footerData = $sharedData['global_settings']['footer_content'] ?? [];

    $aboutText = $footerData['about_text'] ?? 'Empowering the next generation of tech leaders through engaging and professional education.';
    $address = $footerData['address'] ?? '10th of Ramadan, Egypt';
    $phone = $footerData['phone'] ?? '+20 1012822817';
    $email = $footerData['email'] ?? 'info@shefae.com';

    $socialLinks = [
        ['icon' => 'facebook-f', 'url' => $footerData['social_facebook'] ?? 'https://www.facebook.com/shefae', 'className' => 'facebook'],
        ['icon' => 'instagram', 'url' => $footerData['social_instagram'] ?? 'https://www.instagram.com/shefae/', 'className' => 'instagram'],
        ['icon' => 'tiktok', 'url' => $footerData['social_tiktok'] ?? 'https://www.tiktok.com/@shefae', 'className' => 'tiktok'],
        ['icon' => 'whatsapp', 'url' => $footerData['social_whatsapp'] ?? 'https://wa.me/+201012822817', 'className' => 'whatsapp'],
    ];
@endphp

<footer class="footer-v3 mt-auto">
    <div class="container-v3-footer px-4 py-5">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="footer-brand-v3 mb-4">
                    <img src="{{ $siteLogo }}" alt="{{ $siteName }}" class="footer-logo mb-3" />
                    <h4 class="fw-bold text-main">{{ $siteName }}</h4>
                    <p class="text-muted small mt-3">{{ $aboutText }}</p>
                </div>
                <div class="social-links-v3 d-flex gap-2">
                    @foreach ($socialLinks as $social)
                        @if ($social['url'])
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="social-btn-v3 {{ $social['className'] }}">
                                <i class="fab fa-{{ $social['icon'] }}"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <h6 class="footer-title-v3">Quick Links</h6>
                <ul class="footer-links-v3">
                    <li><a href="{{ url('/') }}">Home</a></li>
                    <li><a href="{{ url('/help') }}">Support</a></li>
                    <li><a href="#prices">Pricing</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-6">
                <h6 class="footer-title-v3">Legal</h6>
                <ul class="footer-links-v3">
                    <li><a href="{{ url('/terms') }}">Terms</a></li>
                    <li><a href="{{ url('/privacy-policy') }}">Privacy</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6 class="footer-title-v3">Contact</h6>
                <ul class="footer-info-v3">
                    <li><i class="fas fa-map-marker-alt"></i> <span dir="ltr">{{ $address }}</span></li>
                    <li><i class="fas fa-phone-alt"></i> <span dir="ltr">{{ $phone }}</span></li>
                    <li><i class="fas fa-envelope"></i> <span dir="ltr">{{ $email }}</span></li>
                </ul>
            </div>
        </div>
    </div>

    <style>
        .footer-v3 {
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
            color: var(--text-main);
        }
        .footer-logo { height: 50px; opacity: 0.9; transition: all 0.3s ease; }
        .footer-logo:hover { opacity: 1; transform: scale(1.05); }
        .footer-title-v3 { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; color: var(--app-primary-color); }
        .footer-links-v3, .footer-info-v3 { list-style: none; padding: 0; margin: 0; }
        .footer-links-v3 li, .footer-info-v3 li { margin-bottom: 12px; font-size: 0.9rem; color: var(--text-muted); display: flex; align-items: center; gap: 10px; }
        .footer-links-v3 a { color: inherit; text-decoration: none; transition: all 0.2s ease; }
        .footer-links-v3 a:hover { color: var(--app-primary-color); transform: translateX(5px); }
        .footer-info-v3 i { color: var(--app-primary-color); width: 20px; font-size: 1.1rem; }
        .social-btn-v3 { width: 36px; height: 36px; border-radius: 8px; background: rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: all 0.3s ease; text-decoration: none !important; }
        .social-btn-v3:hover { background: var(--app-primary-color); color: #fff; transform: translateY(-3px); }
    </style>
</footer>
