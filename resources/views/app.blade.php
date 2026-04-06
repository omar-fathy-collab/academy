{{-- This file is kept for legacy compatibility but no longer used as main entry --}}
{{-- All pages now use resources/views/layouts/app.blade.php + layouts/authenticated.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ICT Academy') }}</title>
    <meta http-equiv="refresh" content="0;url={{ route('login') }}">
</head>
<body>
    <p>Redirecting to <a href="{{ route('login') }}">login</a>...</p>
</body>
</html>
