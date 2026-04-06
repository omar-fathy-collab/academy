@extends('layouts.authenticated')

@section('title', 'Vault Setup Required')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center text-center">
        <div class="col-lg-8 col-xl-7">
            <div class="glass-card shadow-2xl rounded-5 p-5 border-0 bg-white" style="backdrop-filter: blur(20px);">
                <div class="mb-5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle p-4 mb-4" style="width: 100px; height: 100px;">
                        <i class="fas fa-exclamation-triangle fa-3x animate-pulse"></i>
                    </div>
                    <h2 class="fw-bold mb-3">System Error in Finance Engine</h2>
                    <p class="text-muted lead px-4">
                        We encountered a critical error while calculating your financial data. This usually happens when the system is missing required database tables or initialization.
                    </p>
                </div>

                @if(isset($error))
                <div class="alert alert-danger shadow-sm rounded-4 border-0 p-4 mb-5 text-start overflow-auto" style="max-height: 200px;">
                    <div class="fw-bold mb-1"><i class="fas fa-bug me-2"></i> Technical details:</div>
                    <code class="small">{{ $error }}</code>
                </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <button onclick="window.location.reload()" class="btn btn-primary w-100 py-3 rounded-pill shadow-lg fw-bold transition-transform">
                            <i class="fas fa-sync me-2"></i> Retry Connection
                        </button>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary w-100 py-3 rounded-pill fw-bold transition-transform">
                            <i class="fas fa-home me-2"></i> Go to Dashboard
                        </a>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-top border-light">
                    <div class="small text-muted">
                        <i class="fas fa-info-circle me-1"></i> 
                        Please contact the technical support team if this error persists.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.2);
        animation: slideUp 0.8s ease-out forwards;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-pulse {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .text-shadow {
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>
@endsection
