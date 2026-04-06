<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $valid ? 'Verified: ' . $certificate->certificate_number : 'Invalid Certificate' }} | ICT Academy</title>
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --success: #059669;
            --danger: #dc2626;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            min-vh-100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .verification-card {
            background: white;
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        .verification-header {
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            background: rgba(255, 255, 255, 0.5);
        }
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .status-icon.success {
            background: #ecfdf5;
            color: var(--success);
        }
        .status-icon.fail {
            background: #fef2f2;
            color: var(--danger);
        }
        .info-grid {
            padding: 2rem;
            background: #f8fafc;
        }
        .info-item {
            margin-bottom: 1.5rem;
        }
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-size: 1.125rem;
            color: #1e293b;
            font-weight: 600;
        }
        .academy-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        .verification-footer {
            padding: 1.5rrem;
            text-align: center;
            font-size: 0.875rem;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .btn-academy {
            background: var(--primary);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
            margin-top: 1rem;
        }
        .btn-academy:hover {
            transform: translateY(-2px);
            background: #1d4ed8;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

<div class="verification-card">
    @if($valid)
        <div class="verification-header">
            <div class="status-icon success bounce">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="fw-bold text-dark mb-2">Authentic Credential</h2>
            <p class="text-muted small">This certificate has been issued and verified by the ICT Academy Board of Education.</p>
        </div>

        <div class="info-grid">
            <div class="row">
                <div class="col-12 info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value">{{ $certificate->user->name ?? 'Student Name N/A' }}</div>
                </div>
                <div class="col-md-6 info-item">
                    <div class="info-label">Course Title</div>
                    <div class="info-value">{{ $certificate->course->course_name ?? 'Academic Merit' }}</div>
                </div>
                <div class="col-md-6 info-item">
                    <div class="info-label">Issue Date</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($certificate->issue_date)->format('M d, Y') }}</div>
                </div>
                <div class="col-12 info-item">
                    <div class="info-label">Certificate ID / Serial</div>
                    <div class="info-value text-uppercase family-mono">{{ $certificate->certificate_number }}</div>
                </div>
            </div>
        </div>
    @else
        <div class="verification-header">
            <div class="status-icon fail">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2 class="fw-bold text-dark mb-2">Invalid Certificate</h2>
            <p class="text-muted small">We could not find a valid academic record matching this serial number in our database.</p>
            <p class="text-danger small fw-bold">Caution: This document may be fraudulent or expired.</p>
            <a href="mailto:support@ict-academy.com" class="btn-academy">Report Fraud</a>
        </div>
    @endif

    <div class="verification-footer">
        <span class="academy-logo">Shefae Academy</span>
        &copy; {{ date('Y') }} All academic rights reserved.
    </div>
</div>

</body>
</html>
