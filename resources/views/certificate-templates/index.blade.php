@extends('layouts.authenticated')

@section('title', 'Certificate Visual Templates')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="templateManager({
    templates: {{ json_encode($templates->items()) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">🎨 Design Templates</h2>
            <p class="text-muted mb-0">Manage visual styles and layouts for student certificates</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="{{ route('certificate_templates.create') }}" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-magic me-2"></i> Design New Template
            </a>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="row g-4 ajax-content position-relative min-vh-50" id="templates-grid">
        <!-- Loading Overlay -->
        <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        @forelse($templates as $template)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden h-100 transition-hover">
                    <div class="position-relative" style="height: 180px; background-color: #f8fafc;">
                        @if($template->background_image)
                            <img src="{{ Storage::url($template->background_image) }}" class="w-100 h-100 object-fit-contain opacity-75" alt="{{ $template->name }}">
                        @else
                            <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                                <i class="far fa-image fa-3x text-muted opacity-25"></i>
                            </div>
                        @endif
                        <div class="position-absolute top-0 end-0 p-2">
                             @if($template->is_active)
                                <span class="badge bg-success rounded-pill px-3 py-1 smaller shadow-sm">Active</span>
                            @else
                                <span class="badge bg-secondary rounded-pill px-3 py-1 smaller shadow-sm">Draft</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-4 text-start" dir="ltr">
                        <h5 class="fw-bold theme-text-main mb-1">{{ $template->name }}</h5>
                        <p class="smaller text-muted mb-4">Template: <span class="badge bg-light text-dark">{{ $template->blade_view ?: 'Default Design' }}</span></p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-row">
                            <div class="smaller text-muted">Font Style: {{ $template->font_style ?: 'Default Sans' }}</div>
                            <div class="rounded-circle border" style="width: 20px; height: 20px; background-color: {{ $template->text_color ?: '#000000' }}"></div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                                <a href="{{ route('certificate_templates.preview', $template->id) }}" class="btn btn-light border-0 py-2 fw-bold smaller flex-grow-1"><i class="fas fa-eye me-1"></i> Preview</a>
                                <a href="{{ route('certificate_templates.edit', $template->id) }}" class="btn btn-light border-0 py-2 fw-bold smaller flex-grow-1 border-start theme-border"><i class="far fa-edit me-1"></i> Edit</a>
                                <form action="{{ route('certificate_templates.destroy', $template->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this template?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-light border-0 py-2 fw-bold smaller text-danger flex-grow-1 border-start theme-border"><i class="far fa-trash-alt me-1"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-layer-group fa-4x mb-3 opacity-25"></i>
                <h4 class="fw-bold">No Visual Templates Found</h4>
                <p>Create your first design template to start issuing formal certificates.</p>
                <a href="{{ route('certificate_templates.create') }}" class="btn btn-primary rounded-pill px-5 py-2 fw-bold mt-3">Start Designing</a>
            </div>
        @endforelse

        <div class="col-12 mt-5" @click="navigate">
            {{ $templates->links() }}
        </div>
    </div>
</div>

<script>
function templateManager(config) {
    return {
        ...ajaxTable(),
        templates: config.templates
    };
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.72rem; }
    .transition-hover:hover { transform: translateY(-5px); transition: all 0.3s ease; box-shadow: 0 1rem 3rem rgba(0,0,0,.1) !important; }
    .object-fit-contain { object-fit: contain; }
</style>
@endsection
