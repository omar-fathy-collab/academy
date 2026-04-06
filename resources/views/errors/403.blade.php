{{-- resources/views/errors/403.blade.php --}}
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .error-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }

        .error-code {
            font-size: 5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }

        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .error-title {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 1rem 0 0.5rem;
        }

        .error-desc {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>

<body>
    <div class="error-card">
        <div class="error-icon">🔒</div>
        <div class="error-code">403</div>
        <div class="error-title">Access Denied</div>
        <div class="error-desc">
            You do not have sufficient permissions to access this page.
            <br>
            If you believe this is an error, please contact the system administrator.
        </div>

        @auth
            @if (auth()->user()->hasRole(['super-admin', 'admin']))
                <a href="{{ route('dashboard') }}" class="btn-home">
                    🏠 Back to Dashboard
                </a>
            @elseif(auth()->user()->hasRole('teacher'))
                <a href="{{ route('teacher.dashboard') }}" class="btn-home">
                    🏫 Back to Dashboard
                </a>
            @elseif(auth()->user()->hasRole('student'))
                <a href="{{ route('student.dashboard') }}" class="btn-home">
                    📚 Back to Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-home">
                    🔑 Login
                </a>
            @endif
        @else
            <a href="{{ route('login') }}" class="btn-home">
                🔑 Login
            </a>
        @endauth
    </div>
</body>

</html>
