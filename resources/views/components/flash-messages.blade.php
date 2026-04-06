@php
    $flash = session()->only(['success', 'error', 'warning', 'info']);
    $titles = [
        'success' => 'Success',
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Info',
    ];
@endphp

@if (count($flash) > 0)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @foreach ($flash as $type => $message)
            if (window.Toast) {
                window.Toast.fire({
                    icon: '{{ $type }}',
                    title: '{{ $titles[$type] ?? "Notice" }}',
                    text: '{{ $message }}',
                });
            } else {
                console.warn('SweetAlert2 Toast helper not loaded yet.');
            }
        @endforeach
    });
</script>
@endif
