@extends('layouts.authenticated')

@section('title', 'Academy Library')

@section('content')
<div x-data='{
    videos: @json($videos, JSON_HEX_APOS),
    books: @json($books, JSON_HEX_APOS),
    allGroups: @json($all_groups, JSON_HEX_APOS),
    isAdmin: {{ $is_admin ? "true" : "false" }},
    searchQuery: "",
    activeType: "all",

    showVideoModal: false,
    showBookModal: false,
    showEditModal: false,
    showPurchaseModal: false,

    videoForm: {
        title: "",
        description: "",
        provider: "local",
        video: null,
        youtube_url: "",
        vimeo_url: "",
        visibility: "public",
        price: 0,
        group_ids: []
    },
    bookForm: {
        title: "",
        description: "",
        book: null,
        visibility: "public",
        price: 0,
        group_ids: []
    },
    editForm: {
        title: "",
        description: "",
        visibility: "public",
        price: 0,
        group_ids: [],
        type: "",
        id: ""
    },
    purchaseForm: {
        item: null,
        type: "",
        screenshot: null,
        notes: ""
    },

    get filteredVideos() {
        if (!this.searchQuery) return this.videos;
        const q = this.searchQuery.toLowerCase();
        return this.videos.filter(v => 
            v.title.toLowerCase().includes(q) || 
            (v.description && v.description.toLowerCase().includes(q))
        );
    },

    get filteredBooks() {
        if (!this.searchQuery) return this.books;
        const q = this.searchQuery.toLowerCase();
        return this.books.filter(b => 
            b.title.toLowerCase().includes(q) || 
            (b.description && b.description.toLowerCase().includes(q))
        );
    },

    openEditModal(item, type) {
        this.editForm = {
            title: item.title,
            description: item.description || "",
            visibility: item.visibility,
            price: item.price,
            group_ids: item.groups ? item.groups.map(g => parseInt(g.group_id)) : [],
            type: type,
            id: item.id
        };
        this.showEditModal = true;
    },

    openPurchaseModal(item, type) {
        this.purchaseForm = {
            item: item,
            type: type,
            screenshot: null,
            notes: ""
        };
        this.showPurchaseModal = true;
    },

    toggleGroupId(formType, id) {
        const target = this[formType].group_ids;
        const index = target.indexOf(id);
        if (index > -1) {
            target.splice(index, 1);
        } else {
            target.push(id);
        }
    },

    getThumbnail(video) {
        if (video.provider === "youtube" && video.provider_id) {
            return `https://img.youtube.com/vi/${video.provider_id}/mqdefault.jpg`;
        }
        if (video.provider === "vimeo" && video.provider_id) {
            return `https://vumbnail.com/${video.provider_id}.jpg`;
        }
        return null;
    },

    showPlayerModal: false,
    playerVideoData: null,
    playerUrl: null,
    playerHeartbeatUrl: null,
    isPlayerPlaying: false,
    playerWatermarkPos: { x: 10, y: 10 },
    playerWatermarkInterval: null,
    playerHeartbeatInterval: null,

    async watchVideo(video) {
        this.playerVideoData = video;
        this.showPlayerModal = true;
        this.isPlayerPlaying = false;
        
        try {
            if (video.provider === "youtube" || video.provider === "vimeo") {
                this.isPlayerPlaying = true;
                return;
            }
            
            const response = await axios.get(`/api/secure-video/${video.id}/url`);
            this.playerUrl = response.data.url;
            this.playerHeartbeatUrl = response.data.heartbeat_url;

            if (video.provider === "local") {
                this.$nextTick(() => {
                    const player = this.$refs.secureVideoPlayer;
                    if (player) {
                        player.load();
                        player.play().catch(e => console.log("Autoplay prevented"));
                    }
                });
                this.startHeartbeat();
            }
            this.startWatermark();
        } catch (err) {
            Swal.fire("Error", "Unable to load secure video stream.", "error");
            this.showPlayerModal = false;
        }
    },

    closePlayer() {
        this.showPlayerModal = false;
        this.isPlayerPlaying = false;
        if (this.$refs.secureVideoPlayer) this.$refs.secureVideoPlayer.pause();
        this.playerUrl = null;
        this.stopHeartbeat();
        this.stopWatermark();
    },

    startWatermark() {
        this.playerWatermarkInterval = setInterval(() => {
            this.playerWatermarkPos = {
                x: Math.floor(Math.random() * 70) + 5,
                y: Math.floor(Math.random() * 80) + 10
            };
        }, 4000);
    },

    stopWatermark() {
        if (this.playerWatermarkInterval) clearInterval(this.playerWatermarkInterval);
    },

    startHeartbeat() {
        this.playerHeartbeatInterval = setInterval(() => {
            if (this.isPlayerPlaying && this.$refs.secureVideoPlayer && this.playerHeartbeatUrl) {
                const player = this.$refs.secureVideoPlayer;
                axios.post(this.playerHeartbeatUrl, {
                    current_position: Math.floor(player.currentTime),
                    duration: Math.floor(player.duration),
                    is_completed: (player.currentTime / player.duration) > 0.9
                }).catch(e => console.error("Heartbeat issue", e));
            }
        }, 15000);
    },

    stopHeartbeat() {
        if (this.playerHeartbeatInterval) clearInterval(this.playerHeartbeatInterval);
    },

    getYoutubeId(url) {
        if (!url) return "";
        if (url.length === 11 && !url.includes("/")) return url;
        const regExp = /^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=|shorts\/)([^#\&\?]*).*/;
        const match = url.match(regExp);
        return (match && match[2].length === 11) ? match[2] : url;
    },

    getVimeoId(url) {
        if (!url) return "";
        if (/^\d+$/.test(url)) return url;
        const match = url.match(/vimeo\.com\/(\d+)/);
        return match ? match[1] : url;
    }
}' class="container-fluid py-4">

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 pt-3">
        <div>
            <h2 class="fw-bold mb-1 theme-text-main shadow-smooth">
                <i class="fas fa-university me-2 text-primary"></i>Academy Library
            </h2>
            <p class="text-muted small">Manage and access educational assets with ease.</p>
        </div>
        
        <template x-if="isAdmin">
            <div class="d-flex gap-2">
                <a href="{{ route('admin.library.payments') }}" class="btn btn-outline-primary rounded-pill px-4 shadow-sm transition-all hover-scale">
                    <i class="fas fa-receipt me-2"></i> Payments
                </a>
                <button @click="showVideoModal = true" class="btn btn-primary rounded-pill px-4 shadow-sm transition-all hover-scale">
                    <i class="fas fa-video me-2"></i> Add Video
                </button>
                <button @click="showBookModal = true" class="btn btn-success rounded-pill px-4 shadow-sm transition-all hover-scale">
                    <i class="fas fa-book me-2"></i> Add Book
                </button>
            </div>
        </template>
    </div>

    <!-- Filters & Search Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="flex-grow-1">
            <div class="glass-card border-0 shadow-sm rounded-pill px-3 py-1 d-flex align-items-center">
                <i class="fas fa-search text-muted me-3"></i>
                <input 
                    type="text" 
                    class="form-control bg-transparent border-0 shadow-none py-2" 
                    placeholder="Search by title or description..." 
                    x-model="searchQuery"
                />
            </div>
        </div>
        
        <div class="btn-group p-1 glass-card rounded-pill shadow-sm border-0">
            <button @click="activeType = 'all'" :class="activeType === 'all' ? 'btn btn-primary rounded-pill px-4' : 'btn btn-link text-decoration-none text-muted px-4'">All</button>
            <button @click="activeType = 'videos'" :class="activeType === 'videos' ? 'btn btn-primary rounded-pill px-4' : 'btn btn-link text-decoration-none text-muted px-4'">Videos</button>
            <button @click="activeType = 'books'" :class="activeType === 'books' ? 'btn btn-primary rounded-pill px-4' : 'btn btn-link text-decoration-none text-muted px-4'">Books</button>
        </div>
    </div>

    <!-- Videos Grid -->
    <div x-show="activeType === 'all' || activeType === 'videos'" x-cloak class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary shadow-sm"><i class="fas fa-video"></i></div>
            <h4 class="fw-bold mb-0">Videos</h4>
            <span class="badge bg-primary bg-opacity-10 text-primary border-0 rounded-pill ms-2" x-text="filteredVideos.length"></span>
        </div>
        
        <div class="row g-4">
            <template x-for="video in filteredVideos" :key="video.id">
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden item-card glass-card-hover transition-all">
                        <!-- Thumbnail/Cover -->
                        <div class="position-relative bg-dark" style="height: 180px;">
                            <!-- Thumbnail Image -->
                            <template x-if="getThumbnail(video)">
                                <img :src="getThumbnail(video)" class="w-100 h-100 object-fit-cover" :alt="video.title">
                            </template>
                            
                            <!-- Overlay Content -->
                            <div class="position-absolute top-0 start-0 w-100 h-100">
                                <template x-if="video.has_access">
                                    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white-50 text-decoration-none cursor-pointer" @click="watchVideo(video)">
                                        <i class="fas fa-play-circle fa-4x mb-2 text-white opacity-75 transition-all hover-scale"></i>
                                        <template x-if="!getThumbnail(video)">
                                            <span class="small fw-bold text-uppercase">Watch Now</span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!video.has_access && video.payment_status !== 'pending'">
                                    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-black bg-opacity-70">
                                        <i class="fas fa-lock fa-3x text-white mb-2"></i>
                                        <button @click="openPurchaseModal(video, 'video')" class="btn btn-sm btn-danger rounded-pill px-3 shadow-lg">Unlock for <span x-text="video.price"></span> EGP</button>
                                    </div>
                                </template>
                                <template x-if="video.payment_status === 'pending'">
                                    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-warning bg-opacity-30">
                                        <i class="fas fa-clock fa-3x text-white mb-2 pulse"></i>
                                        <span class="badge bg-warning text-dark rounded-pill">Pending Approval</span>
                                    </div>
                                </template>
                            </div>

                            <div class="position-absolute top-0 start-0 p-2 d-flex flex-wrap gap-1">
                                <span class="badge rounded-pill shadow-sm" :class="video.visibility === 'public' ? 'bg-success' : 'bg-warning text-dark'" x-text="video.visibility === 'public' ? 'Public' : 'For Groups'"></span>
                                <template x-if="video.price > 0">
                                    <span class="badge bg-dark rounded-pill shadow-sm" x-text="`${video.price} EGP`"></span>
                                </template>
                            </div>
                        </div>
                        
                        <div class="card-body p-3 d-flex flex-column">
                            <h6 class="fw-bold mb-1 text-truncate" :title="video.title" x-text="video.title"></h6>
                            <p class="text-muted extra-small mb-3 line-clamp-2" x-text="video.description"></p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <span class="text-muted extra-small" x-text="new Date(video.created_at).toLocaleDateString()"></span>
                                <div class="d-flex gap-1" x-show="isAdmin">
                                    <button @click="openEditModal(video, 'video')" class="btn btn-xs btn-light rounded-circle shadow-sm"><i class="fas fa-edit"></i></button>
                                    <form :action="`{{ url('admin/library/video') }}/${video.id}/toggle`" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-light rounded-circle shadow-sm"><i class="fas" :class="video.visibility === 'public' ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                                    </form>
                                    <form :action="`{{ url('admin/library/video') }}/${video.id}/delete`" method="POST" class="d-inline" @submit.prevent="if(confirm('Are you sure?')) $el.submit()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger rounded-circle shadow-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Books Grid -->
    <div x-show="activeType === 'all' || activeType === 'books'" x-cloak class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-success bg-opacity-10 p-2 rounded-3 text-success shadow-sm"><i class="fas fa-book"></i></div>
            <h4 class="fw-bold mb-0">Books (PDF)</h4>
            <span class="badge bg-success bg-opacity-10 text-success border-0 rounded-pill ms-2" x-text="filteredBooks.length"></span>
        </div>
        
        <div class="row g-4">
            <template x-for="book in filteredBooks" :key="book.id">
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden item-card glass-card-hover transition-all">
                        <div class="position-relative bg-success bg-opacity-10" style="height: 180px;">
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                <i class="fas fa-file-pdf fa-4x mb-2 text-danger opacity-25"></i>
                            </div>
                            
                            <template x-if="book.has_access">
                                <a :href="`{{ url('student/books') }}/${book.id}/view`" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-success text-opacity-50 text-decoration-none position-relative z-1">
                                    <i class="fas fa-book-open fa-2x mb-2"></i>
                                    <span class="small fw-bold">Read Now</span>
                                </a>
                            </template>
                            <template x-if="!book.has_access && book.payment_status !== 'pending'">
                                <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-black bg-opacity-50">
                                    <i class="fas fa-lock fa-3x text-white mb-2"></i>
                                    <button @click="openPurchaseModal(book, 'book')" class="btn btn-sm btn-danger rounded-pill px-3 shadow-lg">Buy Asset for <span x-text="book.price"></span> EGP</button>
                                </div>
                            </template>
                            <template x-if="book.payment_status === 'pending'">
                                <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-warning bg-opacity-30">
                                    <i class="fas fa-clock fa-3x text-white mb-2 pulse"></i>
                                    <span class="badge bg-warning text-dark rounded-pill">Pending Approval</span>
                                </div>
                            </template>
                            
                            <div class="position-absolute top-0 start-0 p-2 d-flex flex-wrap gap-1">
                                <span class="badge rounded-pill shadow-sm" :class="book.visibility === 'public' ? 'bg-success' : 'bg-warning text-dark'" x-text="book.visibility === 'public' ? 'Public' : 'For Groups'"></span>
                            </div>
                        </div>
                        
                        <div class="card-body p-3 d-flex flex-column">
                            <h6 class="fw-bold mb-1 text-truncate" x-text="book.title"></h6>
                            <p class="text-muted extra-small mb-3 line-clamp-2" x-text="book.description"></p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <span class="text-muted extra-small" x-text="new Date(book.created_at).toLocaleDateString()"></span>
                                <div class="d-flex gap-1" x-show="isAdmin">
                                    <button @click="openEditModal(book, 'book')" class="btn btn-xs btn-light rounded-circle shadow-sm"><i class="fas fa-edit"></i></button>
                                    <form :action="`{{ url('admin/library/book') }}/${book.id}/delete`" method="POST" class="d-inline" @submit.prevent="if(confirm('Are you sure?')) $el.submit()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger rounded-circle shadow-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Modals (Alpine.js templates) -->
    
    <!-- Add Video Modal -->
    <div x-show="showVideoModal" class="modal fade" :class="{ 'show d-block': showVideoModal }" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content glass-card border-0 rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-video me-2"></i> Add Video to Library</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showVideoModal = false"></button>
                </div>
                <form action="{{ route('admin.library.video.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="mb-4 text-center btn-group w-100 p-1 glass-card border rounded-pill">
                            <template x-for="p in ['local', 'youtube', 'vimeo']">
                                <button type="button" @click="videoForm.provider = p" :class="videoForm.provider === p ? 'btn btn-primary rounded-pill shadow-sm' : 'btn btn-link text-muted text-decoration-none'" class="rounded-pill text-capitalize" x-text="p"></button>
                            </template>
                        </div>
                        <input type="hidden" name="provider" :value="videoForm.provider">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">TITLE</label>
                                <input type="text" name="title" class="form-control rounded-3" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">DESCRIPTION</label>
                                <textarea name="description" class="form-control rounded-3" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <template x-if="videoForm.provider === 'local'">
                                    <div>
                                        <label class="form-label small fw-bold text-muted">MP4 VIDEO FILE</label>
                                        <input type="file" name="video" class="form-control rounded-3" accept="video/*" required>
                                    </div>
                                </template>
                                <template x-if="videoForm.provider !== 'local'">
                                    <div>
                                        <label class="form-label small fw-bold text-muted text-capitalize" x-text="`${videoForm.provider} URL`"></label>
                                        <input type="url" :name="`${videoForm.provider}_url`" class="form-control rounded-3" required>
                                    </div>
                                </template>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">VISIBILITY</label>
                                <select name="visibility" x-model="videoForm.visibility" class="form-select rounded-3">
                                    <option value="public">Academy-Wide</option>
                                    <option value="group">Specific Groups</option>
                                    <option value="private">Private</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">PRICE (EGP)</label>
                                <input type="number" name="price" class="form-control rounded-3" value="0" step="0.01">
                            </div>
                        </div>

                        <!-- Group Selector -->
                        <div x-show="videoForm.visibility === 'group'" class="mt-4" x-transition>
                            <label class="form-label small fw-bold text-muted">SELECT GROUPS</label>
                            <div class="list-group p-2 glass-card rounded-4 border-0" style="max-height: 200px; overflow-y: auto;">
                                <template x-for="group in allGroups" :key="group.group_id">
                                    <label class="list-group-item border-0 rounded-3 mb-1 d-flex align-items-center gap-2 cursor-pointer transition-all">
                                        <input type="checkbox" name="group_ids[]" :value="group.group_id" class="form-check-input mt-0">
                                        <span x-text="group.group_name" class="small"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" @click="showVideoModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm">Upload Video</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div x-show="showBookModal" class="modal fade" :class="{ 'show d-block': showBookModal }" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content glass-card border-0 rounded-4 overflow-hidden">
                <div class="modal-header bg-success text-white p-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-book me-2"></i> Upload PDF Book</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showBookModal = false"></button>
                </div>
                <form action="{{ route('admin.library.book.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">BOOK TITLE</label>
                                <input type="text" name="title" class="form-control rounded-3" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">DESCRIPTION</label>
                                <textarea name="description" class="form-control rounded-3" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">PDF FILE</label>
                                <input type="file" name="book" class="form-control rounded-3" accept=".pdf" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">VISIBILITY</label>
                                <select name="visibility" x-model="bookForm.visibility" class="form-select rounded-3">
                                    <option value="public">Academy-Wide</option>
                                    <option value="group">Specific Groups</option>
                                    <option value="private">Private</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">PRICE (EGP)</label>
                                <input type="number" name="price" class="form-control rounded-3" value="0">
                            </div>
                        </div>

                        <div x-show="bookForm.visibility === 'group'" class="mt-4">
                            <label class="form-label small fw-bold text-muted">SELECT GROUPS</label>
                            <div class="list-group p-2 glass-card rounded-4 border-0" style="max-height: 200px; overflow-y: auto;">
                                <template x-for="group in allGroups" :key="group.group_id">
                                    <label class="list-group-item border-0 rounded-3 mb-1 d-flex align-items-center gap-2 cursor-pointer transition-all">
                                        <input type="checkbox" name="group_ids[]" :value="group.group_id" class="form-check-input mt-0">
                                        <span x-text="group.group_name" class="small"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" @click="showBookModal = false">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-5 shadow-sm">Save Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="showEditModal" class="modal fade" :class="{ 'show d-block': showEditModal }" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold">Edit Asset</h5>
                    <button type="button" class="btn-close" @click="showEditModal = false"></button>
                </div>
                <form :action="`{{ url('admin/library') }}/${editForm.type}/${editForm.id}/update`" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body p-4 pt-0">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">TITLE</label>
                            <input type="text" name="title" x-model="editForm.title" class="form-control rounded-pill px-3 py-2" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">DESCRIPTION</label>
                            <textarea name="description" x-model="editForm.description" class="form-control rounded-4 px-3" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">VISIBILITY</label>
                                <select name="visibility" x-model="editForm.visibility" class="form-select rounded-pill px-3">
                                    <option value="public">Public</option>
                                    <option value="group">Group</option>
                                    <option value="private">Private</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">PRICE (EGP)</label>
                                <input type="number" name="price" x-model="editForm.price" class="form-control rounded-pill px-3">
                            </div>
                        </div>
                        
                        <div x-show="editForm.visibility === 'group'" class="mt-2">
                            <label class="form-label small fw-bold text-muted text-center w-100">ACCESSIBLE GROUPS</label>
                            <div class="card bg-light border-0 rounded-4 p-2" style="max-height: 150px; overflow-y: auto;">
                                <template x-for="group in allGroups" :key="group.group_id">
                                    <label class="form-check p-2 rounded-3 mb-1 d-flex align-items-center gap-2 cursor-pointer transition-all border-bottom border-white">
                                        <input type="checkbox" name="group_ids[]" :value="group.group_id" class="form-check-input mt-0" :checked="(editForm.group_ids || []).includes(parseInt(group.group_id))">
                                        <span x-text="group.group_name" class="small"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm w-100 py-2 fw-bold">Update Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Purchase Modal -->
    <div x-show="showPurchaseModal" class="modal fade" :class="{ 'show d-block': showPurchaseModal }" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0 rounded-4 overflow-hidden">
                <div class="modal-header bg-success text-white p-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-shopping-cart me-2"></i> Request Access</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showPurchaseModal = false"></button>
                </div>
                <form action="{{ route('student.library.request_access') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="item_id" :value="purchaseForm.item?.id">
                    <input type="hidden" name="item_type" :value="purchaseForm.type">
                    <input type="hidden" name="amount" :value="purchaseForm.item?.price">
                    <div class="modal-body p-4">
                        <div class="mb-4 text-center">
                            <h6 class="text-muted mb-2">Item: <span class="fw-bold" x-text="purchaseForm.item?.title"></span></h6>
                            <div class="display-6 fw-bold text-success mb-2" x-text="`${purchaseForm.item?.price} EGP`"></div>
                            <p class="text-muted small">Please upload a screenshot of your payment for manual approval by the admin.</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">PAYMENT SCREENSHOT</label>
                            <input type="file" name="screenshot" class="form-control rounded-pill border-dashed" accept="image/*" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">NOTES (OPTIONAL)</label>
                            <textarea name="notes" class="form-control rounded-4" rows="2" placeholder="Any info for the admin..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 bg-light">
                        <button type="submit" class="btn btn-success rounded-pill w-100 py-3 fw-bold shadow-sm transition-all hover-scale">Submit Access Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Secure Video Player Modal -->
    <div x-show="showPlayerModal" class="modal fade" :class="{ 'show d-block': showPlayerModal }" x-cloak style="background: #000; z-index: 3000;">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content border-0 rounded-0 bg-black position-relative overflow-hidden">
                <!-- Header Overlay -->
                <div class="position-absolute top-0 start-0 w-100 p-4 d-flex justify-content-between align-items-center z-3" style="background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);">
                    <h5 class="text-white mb-0 fw-bold" x-text="playerVideoData?.title"></h5>
                    <button type="button" class="btn btn-link text-white text-decoration-none bg-white bg-opacity-10 rounded-circle p-2" @click="closePlayer" style="width: 45px; height: 45px;">
                        <i class="fas fa-times fs-5"></i>
                    </button>
                </div>

                <!-- Watermark -->
                <div x-show="isPlayerPlaying" 
                     class="position-absolute text-white opacity-25 fw-bold select-none pointer-events-none z-2"
                     :style="`left: ${playerWatermarkPos.x}%; top: ${playerWatermarkPos.y}%; transition: all 1s linear; font-size: 1.2rem;`"
                     x-text="'{{ Auth::user()->email }}'">
                </div>

                <!-- Player Interface -->
                <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-black">
                    <template x-if="playerVideoData?.provider === 'youtube'">
                        <iframe :src="`https://www.youtube.com/embed/${getYoutubeId(playerVideoData.file_path)}?autoplay=1&modestbranding=1&rel=0`" 
                                class="w-100 h-100 border-0" allow="autoplay; encrypted-media; fullscreen"></iframe>
                    </template>
                    <template x-if="playerVideoData?.provider === 'vimeo'">
                        <iframe :src="`https://player.vimeo.com/video/${getVimeoId(playerVideoData.file_path)}?autoplay=1`" 
                                class="w-100 h-100 border-0" allow="autoplay; fullscreen"></iframe>
                    </template>
                    <template x-if="playerVideoData?.provider === 'local'">
                        <div class="w-100 h-100 position-relative">
                            <video x-ref="secureVideoPlayer" 
                                   class="w-100 h-100" 
                                   controls 
                                   controlsList="nodownload" 
                                   @play="isPlayerPlaying = true"
                                   @pause="isPlayerPlaying = false">
                                <source :src="playerUrl" type="video/mp4">
                            </video>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .glass-card {
        background: rgba(var(--bs-body-bg-rgb), 0.7) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(var(--bs-body-color-rgb), 0.08) !important;
    }
    
    .item-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .glass-card-hover:hover {
        transform: translateY(-8px);
        background: rgba(var(--bs-primary-rgb), 0.02) !important;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important;
    }
    
    .extra-small { font-size: 11.5px; }
    
    .hover-scale:hover {
        transform: scale(1.03);
    }
    
    .cursor-pointer { cursor: pointer; }
    
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    [x-cloak] { display: none !important; }
    
    .border-dashed {
        border-style: dashed !important;
        border-width: 2px !important;
    }
</style>
@endpush
@endsection
