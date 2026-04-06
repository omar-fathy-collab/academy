{{-- Global Flash Notifications Handler --}}
@if (session('success') || session('error') || session('status') || $errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Wait slightly for any Alpine initialization if needed
            setTimeout(() => {
                @if (session('success'))
                    window.Toast.fire({
                        icon: 'success',
                        title: "{{ session('success') }}"
                    });
                @endif

                @if (session('error'))
                    window.Toast.fire({
                        icon: 'error',
                        title: "{{ session('error') }}"
                    });
                @endif

                @if (session('status'))
                    window.Toast.fire({
                        icon: 'info',
                        title: "{{ session('status') }}"
                    });
                @endif

                @if ($errors->any())
                    window.Toast.fire({
                        icon: 'error',
                        title: "Validation Error",
                        text: "{{ $errors->first() }}"
                    });
                @endif
            }, 100);
        });
    </script>
@endif
