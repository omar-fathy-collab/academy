@extends('layouts.authenticated')

@section('title', 'Global Settings')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="settingsDashboard({
    initialSettings: {{ json_encode($settings) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">⚙️ Global Settings</h2>
            <p class="text-muted mb-0">Configure academy branding, theme, and behavior</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <button type="submit" form="settingsForm" class="btn btn-primary fw-bold rounded-pill px-5 shadow-sm transition-hover py-3">
                <i class="fas fa-save me-2"></i> Save All Changes
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <div class="list-group list-group-flush">
                    <button @click="activeTab = 'general'" :class="activeTab === 'general' ? 'bg-primary text-white border-0' : 'theme-text-main theme-badge-bg'" class="list-group-item list-group-item-action py-3 px-4 fw-bold transition-all">
                        <i class="fas fa-cog me-2"></i> General Identity
                    </button>
                    <button @click="activeTab = 'branding'" :class="activeTab === 'branding' ? 'bg-primary text-white border-0' : 'theme-text-main theme-badge-bg'" class="list-group-item list-group-item-action py-3 px-4 fw-bold transition-all">
                        <i class="fas fa-palette me-2"></i> Branding & Theme
                    </button>
                    <button @click="activeTab = 'advanced'" :class="activeTab === 'advanced' ? 'bg-primary text-white border-0' : 'theme-text-main theme-badge-bg'" class="list-group-item list-group-item-action py-3 px-4 fw-bold transition-all">
                        <i class="fas fa-tools me-2"></i> System & Advanced
                    </button>
                </div>
            </div>

            <!-- Preview Card -->
            <div class="card border-0 shadow-sm rounded-4 theme-card mt-4 overflow-hidden border-top border-4" :style="'border-color: ' + settings.primary_color + ' !important'">
                <div class="card-header theme-badge-bg border-bottom-0 py-3 px-4">
                    <h6 class="fw-bold mb-0 smaller text-muted text-uppercase">Live UI Preview</h6>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="mb-3 p-3 rounded-4 border theme-border" :style="'background-color: ' + settings.bg_color_light">
                        <img :src="settings.site_logo" alt="Logo" class="img-fluid mb-2" style="max-height: 40px;" />
                        <h6 class="fw-bold mb-0" :style="'color: ' + settings.primary_color" x-text="settings.site_name"></h6>
                    </div>
                    <button class="btn w-100 rounded-pill fw-bold smaller mb-2 py-2 shadow-sm" :style="'background-color: ' + settings.button_color + '; color: white; border: none;'" x-text="'Primary Button'"></button>
                    <p class="smaller text-muted mb-0">This is how your branding elements will appear across the dashboard.</p>
                </div>
            </div>
        </div>

        <!-- Main Form Content -->
        <div class="col-lg-9">
            <form action="{{ route('settings.update') }}" method="POST" id="settingsForm" enctype="multipart/form-data">
                @csrf
                
                <!-- General Identity Tab -->
                <div x-show="activeTab === 'general'" x-transition>
                    <div class="card border-0 shadow-sm rounded-4 theme-card p-4 p-md-5">
                        <h4 class="fw-bold mb-4 theme-text-main"><i class="fas fa-id-card text-primary me-2"></i> Academy Identity</h4>
                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-muted">Academy Name <span class="text-danger">*</span></label>
                                    <input type="text" name="site_name" x-model="settings.site_name" class="form-control rounded-3 py-2 theme-badge-bg theme-text-main theme-border shadow-none" required />
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-muted">System Typography Font</label>
                                    <input type="text" name="site_font" x-model="settings.site_font" class="form-control rounded-3 py-2 theme-badge-bg theme-text-main theme-border shadow-none" placeholder="e.g., 'Inter', sans-serif" />
                                    <p class="smaller text-muted mt-2">Default font family used across the application.</p>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-muted">Academy Logo</label>
                                <div class="p-4 theme-badge-bg rounded-4 border theme-border text-center position-relative overflow-hidden">
                                    <template x-if="settings.site_logo">
                                        <img :src="settings.site_logo" class="img-fluid mb-3 rounded shadow-sm" style="max-height: 100px;" />
                                    </template>
                                    <input type="file" name="site_logo" class="form-control smaller theme-border" accept="image/*" @change="handleLogoUpload" />
                                    <p class="smaller text-muted mt-2 mb-0">Recommended size: 250x100px (PNG/SVG)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Branding & Theme Tab -->
                <div x-show="activeTab === 'branding'" x-transition>
                    <div class="card border-0 shadow-sm rounded-4 theme-card p-4 p-md-5">
                        <h4 class="fw-bold mb-4 theme-text-main"><i class="fas fa-fill-drip text-warning me-2"></i> Color Palette & Theme</h4>
                        
                        <div class="row g-4 mb-5">
                            <div class="col-6 col-md-3">
                                <label class="smaller fw-bold text-muted mb-2 d-block">Primary Color</label>
                                <input type="color" name="primary_color" x-model="settings.primary_color" class="form-control form-control-color w-100 rounded-3 border-0 p-1 bg-transparent" />
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="smaller fw-bold text-muted mb-2 d-block">Button Color</label>
                                <input type="color" name="button_color" x-model="settings.button_color" class="form-control form-control-color w-100 rounded-3 border-0 p-1 bg-transparent" />
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="smaller fw-bold text-muted mb-2 d-block">Button Hover</label>
                                <input type="color" name="button_hover_color" x-model="settings.button_hover_color" class="form-control form-control-color w-100 rounded-3 border-0 p-1 bg-transparent" />
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="smaller fw-bold text-muted mb-2 d-block">Page Template</label>
                                <select name="theme_template" x-model="settings.theme_template" class="form-select smaller rounded-3 theme-badge-bg theme-text-main theme-border">
                                    <option value="default">Premium Professional (Default)</option>
                                    <option value="minimal">Minimalist Academic</option>
                                    <option value="corporate">Corporate Enterprise</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-4 theme-badge-bg rounded-4 border theme-border h-100">
                                    <h6 class="fw-bold mb-3 small">☀️ Light Mode Palette</h6>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-grow-1"><span class="smaller text-muted">Background</span></div>
                                        <input type="color" name="bg_color_light" x-model="settings.bg_color_light" class="form-control-color border-0 p-0 shadow-none bg-transparent" style="width: 32px; height: 32px;" />
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1"><span class="smaller text-muted">Main Text</span></div>
                                        <input type="color" name="text_color_light" x-model="settings.text_color_light" class="form-control-color border-0 p-0 shadow-none bg-transparent" style="width: 32px; height: 32px;" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-4 theme-badge-bg rounded-4 border theme-border h-100" style="background-color: #1e293b; border-color: #334155 !important;">
                                    <h6 class="fw-bold mb-3 small text-white">🌙 Dark Mode Palette</h6>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-grow-1"><span class="smaller text-slate-400">Background</span></div>
                                        <input type="color" name="bg_color_dark" x-model="settings.bg_color_dark" class="form-control-color border-0 p-0 shadow-none bg-transparent" style="width: 32px; height: 32px;" />
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1"><span class="smaller text-slate-400">Main Text</span></div>
                                        <input type="color" name="text_color_dark" x-model="settings.text_color_dark" class="form-control-color border-0 p-0 shadow-none bg-transparent" style="width: 32px; height: 32px;" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Tab -->
                <div x-show="activeTab === 'advanced'" x-transition>
                    <div class="card border-0 shadow-sm rounded-4 theme-card p-4 p-md-5">
                        <h4 class="fw-bold mb-4 theme-text-main"><i class="fas fa-shield-alt text-danger me-2"></i> System & Integration</h4>
                        
                        <div class="mb-5">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold theme-text-main mb-1">Action Monitoring</h6>
                                    <p class="smaller text-muted mb-0">Record all administrative operations in the Audit Log.</p>
                                </div>
                                <div class="form-check form-switch fs-4">
                                    <input class="form-check-input ms-0" type="checkbox" name="enable_action_monitoring" :checked="settings.enable_action_monitoring" value="1">
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold small text-muted">Footer Configuration (JSON)</label>
                            <textarea name="footer_content" x-model="settings.footer_content" class="form-control rounded-3 py-2 theme-badge-bg theme-text-main theme-border shadow-none font-monospace small" rows="8"></textarea>
                            <div class="mt-3 p-3 bg-light rounded-4 smaller text-muted theme-border border">
                                <i class="fas fa-info-circle me-1"></i> Tip: Use structured JSON to define footer links and copyright text.
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function settingsDashboard(config) {
    return {
        activeTab: 'general',
        settings: config.initialSettings,
        
        handleLogoUpload(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (f) => this.settings.site_logo = f.target.result;
                reader.readAsDataURL(file);
            }
        }
    };
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.72rem; }
    .transition-hover:hover { transform: translateY(-3px); }
    .transition-all { transition: all 0.3s ease; }
    .text-slate-400 { color: #94a3b8; }
</style>
@endsection
