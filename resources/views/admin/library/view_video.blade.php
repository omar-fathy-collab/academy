@extends('layouts.authenticated')

@section('title', $video->title . ' - Academy Video')

@section('content')
<div x-data="securePlayer({
    isAdmin: {{ (auth()->user()->role_id == 1 || auth()->user()->hasRole('super-admin')) ? 'true' : 'false' }},
    videoId: '{{ $video->id }}',
    provider: '{{ $video->provider }}',
    videoUrl: '{{ $video->file_path }}',
    user: {
        id: '{{ $user->id }}',
        name: '{{ $user->name }}',
        email: '{{ $user->email }}'
    },
    getProgressUrl: '{{ route('student.video-progress.get', ['material_id' => $video->id]) }}',
    getSignedUrlRoute: '{{ route('student.secure_video.url', ['video_id' => $video->id]) }}',
    heartbeatUrl: '{{ route('student.video-progress.heartbeat', ['material_id' => $video->id]) }}',
    thumbnailUrl: '{{ $video->thumbnail_url ? asset($video->thumbnail_url) : '' }}',
    streamType: '{{ $video->stream_type }}'
})" class="container-fluid py-4" @contextmenu.prevent>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb glass-card rounded-pill px-4 py-2 shadow-sm border-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.library') }}" class="text-decoration-none text-muted"><i class="fas fa-university me-1"></i> Library</a></li>
                    <li class="breadcrumb-item active theme-text-main fw-bold text-truncate" aria-current="page" style="max-width: 300px;">{{ $video->title }}</li>
                </ol>
            </nav>

            <!-- Video Player Stage -->
            <div class="video-stage position-relative bg-black rounded-4 shadow-2xl overflow-hidden mb-4" 
                 style="aspect-ratio: 16/9; box-shadow: 0 40px 80px -12px rgba(0,0,0,0.7);">
                
                <!-- Loading State / Errors -->
                <div x-show="loading || playerStatus === 'error'" 
                     class="position-absolute inset-0 d-flex flex-column align-items-center justify-content-center text-white" 
                     style="z-index: 40; background: #000;">
                    
                    <template x-if="loading">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <div class="small opacity-75 font-monospace">Securing Stream...</div>
                        </div>
                    </template>

                    <template x-if="playerStatus === 'error'">
                        <div class="text-center p-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5 class="fw-bold" x-text="errorMessage || 'Connection failed'"></h5>
                            <button @click="window.location.reload()" class="btn btn-outline-light rounded-pill px-4 mt-3">
                                <i class="fas fa-sync me-2"></i> Retry
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Plyr Container Stage -->
                <div class="w-100 h-100 bg-black" id="plyr-container" x-ignore>
                    {{-- Target will be swapped dynamically by initPlyr --}}
                    <div id="plyr-player-target"></div>
                </div>
            </div>

            <!-- Asset Info -->
            <div class="card glass-card border-0 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="fw-bold theme-text-main mb-1">{{ $video->title }}</h4>
                        <div class="d-flex align-items-center gap-2">
                             <span class="badge bg-primary bg-opacity-10 text-primary border-0 rounded-pill">
                                <i class="fas fa-film me-1"></i> Library Asset
                             </span>
                             <span class="text-muted small">Updated {{ $video->updated_at->format('M d, Y') }}</span>
                             <span class="badge bg-dark rounded-pill">{{ strtoupper($video->provider) }}</span>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-light rounded-pill px-4 shadow-sm border theme-border" @click="window.history.back()">Back</button>
                    </div>
                </div>
                <hr class="opacity-10 my-3">
                <div class="description text-muted">
                    {!! nl2br(e($video->description)) !!}
                    @if(empty($video->description))
                        <span class="opacity-50 italic">No description available for this video.</span>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
function securePlayer(config) {
    return {
        ...config,
        player: null,
        loading: true,
        playerStatus: 'loading',
        currentTime: 0,
        duration: 0,
        isPlaying: false,
        errorMessage: '',
        heartbeatInterval: null,

        async init() {
            try {
                // 1. Get initial progress
                const progressRes = await fetch(this.getProgressUrl);
                const progressData = await progressRes.json();
                const startPos = progressData?.progress?.last_position || 0;
                this.currentTime = startPos;
                
                // 2. Initialize Player
                await this.initPlyr(startPos);
                
            } catch (err) {
                console.error('Player Global Init Failed:', err);
                this.playerStatus = 'error';
                this.errorMessage = 'Secure connection failed.';
                this.loading = false;
            }
        },

        async initPlyr(startPos) {
            const provider = (this.provider || '').toLowerCase();
            const target = document.getElementById('plyr-player-target');
            if (!target) return;

            // Options for Plyr (Common)
            const options = {
                controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'],
                settings: ['quality', 'speed'],
                speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                seekTime: 10,
                ratio: '16:9',
                // Local specific poster loading
                poster: (provider === 'local' && this.thumbnailUrl) ? this.thumbnailUrl : null
            };

            try {
                if (provider === 'local') {
                    // Create Video Element
                    const video = document.createElement('video');
                    video.id = 'local-plyr';
                    video.playsInline = true;
                    video.controls = true;
                    video.crossOrigin = 'anonymous';
                    target.parentNode.replaceChild(video, target);

                    const res = await fetch(this.getSignedUrlRoute);
                    const data = await res.json();
                    const signedUrl = data.url;
                    
                    if (Hls.isSupported() && this.streamType === 'hls') {
                        const hls = new Hls();
                        hls.loadSource(signedUrl);
                        hls.attachMedia(video);
                        this.player = new Plyr(video, options);
                    } else {
                        video.src = signedUrl;
                        this.player = new Plyr(video, options);
                    }

                } else if (provider === 'youtube') {
                    // Set Data Attributes for YouTube
                    target.dataset.plyrProvider = 'youtube';
                    target.dataset.plyrEmbedId = this.extractYoutubeId(this.videoUrl);
                    this.player = new Plyr(target, options);

                } else if (provider === 'vimeo') {
                    // Set Data Attributes for Vimeo
                    target.dataset.plyrProvider = 'vimeo';
                    target.dataset.plyrEmbedId = this.extractVimeoId(this.videoUrl);
                    this.player = new Plyr(target, options);
                }

                if (!this.player) throw new Error('Player object not created');

                // Events
                this.player.on('ready', () => {
                    console.log('Plyr Ready Event Fired');
                    this.loading = false;
                    this.playerStatus = 'ready';
                    this.player.currentTime = startPos;
                    this.duration = this.player.duration;
                    this.setupHeartbeat();
                });

                this.player.on('timeupdate', () => {
                    this.currentTime = this.player.currentTime;
                    this.duration = this.player.duration;
                });

                this.player.on('playing', () => this.isPlaying = true);
                this.player.on('pause', () => this.isPlaying = false);
                this.player.on('ended', () => {
                    this.isPlaying = false;
                    this.sendHeartbeat(true);
                });

                // Fallback for loading spinner removal if provider is slow to signal ready
                setTimeout(() => { 
                    if(this.loading) {
                        console.log('Timeout fallback triggered - forcing ready state');
                        this.loading = false;
                        this.playerStatus = 'ready';
                    }
                }, 4000);

            } catch (e) {
                console.error('Plyr Architectural Error:', e);
                this.playerStatus = 'error';
                this.errorMessage = e.message;
                this.loading = false;
            }
        },

        setupHeartbeat() {
            if (this.heartbeatInterval) clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = setInterval(() => {
                if (this.isPlaying) this.sendHeartbeat();
            }, 8000);
        },

        async sendHeartbeat(isCompleted = false) {
            try {
                await fetch(this.heartbeatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        current_position: Math.floor(this.currentTime),
                        duration: Math.floor(this.duration),
                        is_completed: isCompleted || (this.duration > 0 && this.currentTime >= this.duration * 0.98)
                    })
                });
            } catch (err) {
                console.warn('Heartbeat error:', err);
            }
        },

        extractYoutubeId(url) {
            if (!url) return '';
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=|\/live\/|shorts\/)([^#\&\?]*).*/;
            const match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : url;
        },

        extractVimeoId(url) {
            if (!url) return '';
            const regExp = /vimeo\.com\/(?:video\/|channels\/|groups\/[^\/]+\/videos\/|album\/[^\/]+\/video\/|showcase\/[^\/]+\/video\/|event\/[^\/]+\/videos\/|)([\d]+)/;
            const match = url.match(regExp);
            return (match && match[1]) ? match[1] : url.split('/').pop();
        }
    }
}
</script>
@endpush

@push('styles')
<style>
    .plyr { border-radius: 12px; height: 100% !important; width: 100% !important; display: block !important; }
    .video-stage { background: #000; border: 1px solid var(--border-color); z-index: 1; }
    .plyr--video { border-radius: 12px; z-index: 10; }
    .plyr__video-wrapper { height: 100% !important; }
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection
