@extends('layouts.authenticated')

@section('title', $book->title . ' - Academy Library')

@section('content')
<div x-data="{ 
    expanded: false,
    loading: false
}" x-init="setTimeout(() => { loading = false; }, 2000)" class="container-fluid py-4" @contextmenu.prevent>

    <div class="row justify-content-center">
        <div :class="expanded ? 'col-12 px-0 pt-0' : 'col-lg-10 col-xl-9'">
            
            <!-- Breadcrumb & Actions -->
            <div x-show="!expanded" class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb glass-card rounded-pill px-4 py-2 shadow-sm border-0 mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.library') }}" class="text-decoration-none text-muted"><i class="fas fa-university me-1"></i> Library</a></li>
                        <li class="breadcrumb-item active theme-text-main fw-bold text-truncate" aria-current="page" style="max-width: 250px;">{{ $book->title }}</li>
                    </ol>
                </nav>

                <div class="d-flex align-items-center gap-2">
                    <button @click="expanded = !expanded" class="btn btn-light rounded-pill px-3 shadow-sm border theme-border">
                        <i class="fas" :class="expanded ? 'fa-compress-arrows-alt' : 'fa-expand-arrows-alt'"></i>
                        <span class="ms-1 d-none d-sm-inline" x-text="expanded ? 'Exit Focus' : 'Focus Mode'"></span>
                    </button>
                    <a href="{{ route('admin.library') }}" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm">
                        <i class="fas fa-arrow-left me-2"></i> Back
                    </a>
                </div>
            </div>

            <!-- Floating Controls in Expanded Mode -->
            <div x-show="expanded" class="position-fixed bottom-0 end-0 p-4" style="z-index: 1000;">
                <button @click="expanded = false" class="btn btn-dark shadow-lg rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fas fa-compress-arrows-alt fa-lg"></i>
                </button>
            </div>

            <div class="row g-4">
                <!-- PDF Viewer Area -->
                <div :class="expanded ? 'col-12' : 'col-xl-8'">
                    <div class="book-stage position-relative bg-dark shadow-2xl overflow-hidden border theme-border" 
                         :class="expanded ? '' : 'rounded-4'"
                         style="height: 85vh; min-height: 600px;">
                        
                        @if($fileExists)
                            <!-- Iframe PDF Viewer -->
                            <iframe 
                                x-ref="pdfFrame"
                                src="{{ $pdfUrl }}#toolbar=0&navpanes=0&scrollbar=0" 
                                class="w-100 h-100 border-0"
                                allow="fullscreen"
                            ></iframe>
                        @else
                            <!-- Missing File Error -->
                            <div class="position-absolute inset-0 d-flex flex-column align-items-center justify-content-center text-white w-100 h-100">
                                <div class="text-center p-5 bg-dark bg-opacity-75 rounded-4 border border-danger shadow-lg">
                                    <i class="fas fa-file-excel fa-4x text-danger mb-3"></i>
                                    <h3 class="fw-bold">File Not Found</h3>
                                    <p class="text-muted mb-0">The requested document is missing from the server.</p>
                                    @if(Auth::user()->isAdmin())
                                        <div class="alert alert-danger mt-4 mb-0 small">
                                            <strong>Admin Notice:</strong> The file physically does not exist at the expected path on the disk. It may have been deleted manually or failed to upload.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Anti-Copy Overlay (Subtle Watermark) -->
                        <div class="position-absolute pointer-events-none w-100 d-flex justify-content-center" style="bottom: 40px; z-index: 50; opacity: 0.1;">
                            <span class="badge bg-dark rounded-pill font-monospace h5 px-4 py-2 border">SECURE-VIEW-{{ Auth::id() }}-{{ now()->format('Ymd') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Book Sidebar Info -->
                <div class="col-xl-4 d-flex flex-column" x-show="!expanded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4">
                    <div class="card glass-card border-0 shadow-sm rounded-4 flex-grow-1 overflow-hidden d-flex flex-column">
                        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                            <h5 class="fw-bold theme-text-main mb-0">Study Material Details</h5>
                            <hr class="mt-3 opacity-10">
                        </div>
                        <div class="card-body p-4 pt-2">
                            <div class="mb-4">
                                <label class="text-muted small text-uppercase fw-bold mb-2 d-block">Title</label>
                                <p class="h5 fw-bold mb-0 theme-text-main">{{ $book->title }}</p>
                            </div>

                            <div class="mb-4">
                                <label class="text-muted small text-uppercase fw-bold mb-2 d-block">Description</label>
                                <div class="text-muted" style="font-size: 0.95rem; line-height: 1.6;">
                                    {!! nl2br(e($book->description)) !!}
                                    @if(empty($book->description))
                                        <span class="opacity-50 italic">No additional summary provided.</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-auto pt-4 d-flex flex-column gap-3">
                                <div class="p-3 rounded-3 bg-light bg-opacity-50 border border-white">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Asset ID</span>
                                        <span class="badge bg-dark rounded-pill font-monospace small px-2">{{ strtoupper(substr($book->uuid, 0, 8)) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Access Mode</span>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border-0 rounded-pill px-3">{{ ucfirst($book->visibility) }}</span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 border theme-border">
                                    <span class="text-muted small">Licensing</span>
                                    <span class="fw-bold theme-text-main">{{ $book->price > 0 ? number_format($book->price, 2) . ' EGP' : 'Free Learning Content' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light bg-opacity-75 border-0 p-4 text-center mt-3">
                            <button @click="expanded = true" class="btn btn-primary rounded-pill w-100 shadow-sm py-2">
                                <i class="fas fa-expand-arrows-alt me-2"></i> Enter Reading Mode
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@push('styles')
<style>
    .book-stage { 
        background: #2a2a2a; 
        transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 40px 100px -20px rgba(0,0,0,0.4);
    }
    .col-12 .book-stage {
        height: 100vh !important;
        max-height: 100vh !important;
        border: none !important;
    }
    [x-cloak] { display: none !important; }
    
    .glass-card {
        background: rgba(255, 255, 255, 0.7) !important;
        backdrop-filter: blur(20px) !important;
        -webkit-backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255,255,255,0.4) !important;
    }
    
    [data-bs-theme="dark"] .glass-card {
        background: rgba(30, 30, 30, 0.7) !important;
        border-color: rgba(255,255,255,0.05) !important;
    }
</style>
@endpush
@endsection
