Production deployment checklist
=============================

1. Environment
   - Set APP_ENV=production and APP_DEBUG=false in the production .env
   - Set APP_URL to https://your-domain.example

2. Security / Sessions
   - Ensure SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true, SESSION_SAME_SITE=lax (or strict)
   - Use database or redis session driver for multiple app instances
   - Set SESSION_ENCRYPT=true to encrypt session payloads if sensitive data stored

3. HTTPS / Proxies
   - Obtain a valid TLS certificate (Let's Encrypt / managed certs)
   - If behind a proxy or load balancer, set TRUSTED_PROXIES environment variable to proxy IPs
   - Ensure TrustProxies middleware is enabled (app/Http/Kernel.php)

4. Tokens & CSRF
   - Verify VerifyCsrfToken middleware is active (web middleware group)
   - For API tokens use short-lived tokens and refresh flows
   - Rotate any long-lived tokens before going live

5. Hardening & runtime
   - Run config:cache, route:cache, view:cache
   - Disable debug and error display
   - Use robust logging and alerting (external log shipper)
   - Ensure file permissions and storage protection

6. SEO
   - Provide sitemap.xml and robots.txt
   - Use canonical and Open Graph meta tags (we added a partial at resources/views/partials/meta.blade.php)

7. Final steps before going live
   - Run migrations and seeders on production DB with backups ready
   - Warm caches and queue workers
   - Monitor application health and metrics
