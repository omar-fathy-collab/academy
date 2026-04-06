@extends('layouts.authenticated')

@section('title', 'Session: ' . $session->topic)

@section('content')
    @php
        $sessionId = $session->uuid ?? $session->session_id;
        
        $sessionJSData = [
            'attendance' => collect($students)->mapWithKeys(fn($s) => [$s->student_id => $attendanceData[$s->student_id]->status ?? 'present']),
            'ratings' => collect($students)->mapWithKeys(fn($s) => [$s->student_id => $ratingData[$s->student_id]->rating_value ?? 0]),
            'meetingLinks' => $session->meetings->map(fn($m) => [
                'id' => $m->id, 
                'title' => $m->title, 
                'link' => $m->meeting_link, 
                'end_time' => $m->end_time, 
                'is_closed' => (bool)$m->is_closed
            ])
        ];
    @endphp

    <script id="session-initial-data" type="application/json">
        @json($sessionJSData)
    </script>

    <script>
        function sessionComponent() {
            const initialData = JSON.parse(document.getElementById('session-initial-data').textContent);
            
            return {
                ...initialData,
                activeTab: 'attendance',
                showMaterialModal: false,
                showBookModal: false,
                showUploadVideoModal: false,
                showPlaybackModal: false,
                showMeetingModal: false,
                showCloneModal: false,
                provider: 'local',

                // Video Playback State
                videoPlaybackData: null,
                videoPlaybackUrl: null,
                isPlaying: false,
                watermarkPos: { x: 10, y: 10 },
                watermarkInterval: null,
                heartbeatInterval: null,
                lastSavedPosition: 0,
                heartbeatUrl: null,

                async openVideoPlayer(video) {
                    if (video.provider === 'local' && video.status === 'processing') {
                        const isStaff = {{ Auth::user()->isAdmin() || Auth::user()->isTeacher() ? 'true' : 'false' }};
                        
                        const result = await Swal.fire({
                            title: 'Processing Stream',
                            text: isStaff 
                                ? 'This local video is still marked as processing. Would you like to try playing it anyway? | هذا الفيديو المحلي لا يزال في مرحلة المعالجة. هل تريد محاولة تشغيله على أي حال؟'
                                : 'We are still securing this local video file. | جاري تأمين وتشفير ملف الفيديو.',
                            icon: 'info',
                            showCancelButton: isStaff,
                            confirmButtonText: isStaff ? 'Force Play' : 'OK',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#4e73df'
                        });

                        if (!isStaff || (isStaff && !result.isConfirmed)) return;
                    }



                    this.videoPlaybackData = video;
                    this.showPlaybackModal = true;

                    // For external providers, we don't need a secure signed URL
                    if (video.provider !== 'local') {
                        this.videoPlaybackUrl = video.file_path;
                        this.startWatermark();
                        return;
                    }

                    try {
                        const response = await fetch(`/api/secure-video/${video.id}/url`);
                        const data = await response.json();
                        this.videoPlaybackUrl = data.url;
                        this.heartbeatUrl = data.heartbeat_url;
                        this.lastSavedPosition = video.last_position || 0;


                        this.$nextTick(() => {
                            const player = this.$refs.videoPlayer;
                            if (player) {
                                player.load();
                                player.currentTime = this.lastSavedPosition;
                                player.play().catch(e => console.log("Autoplay blocked"));
                            }
                        });
                        this.startHeartbeat();
                        this.startWatermark();
                    } catch (err) {
                        console.error("Playback failed", err);
                        Swal.fire('Error', 'Failed to load video stream.', 'error');
                        this.showPlaybackModal = false;
                    }
                },

                closeVideoPlayer() {
                    this.showPlaybackModal = false;
                    this.isPlaying = false;
                    if (this.$refs.videoPlayer) this.$refs.videoPlayer.pause();
                    this.videoPlaybackUrl = null;
                    this.stopHeartbeat();
                    this.stopWatermark();
                },

                startWatermark() {
                    this.watermarkInterval = setInterval(() => {
                        this.watermarkPos = {
                            x: Math.floor(Math.random() * 70) + 5,
                            y: Math.floor(Math.random() * 80) + 10
                        };
                    }, 4000);
                },

                stopWatermark() {
                    if (this.watermarkInterval) clearInterval(this.watermarkInterval);
                },

                startHeartbeat() {
                    this.heartbeatInterval = setInterval(() => {
                        if (this.isPlaying && this.$refs.videoPlayer && this.heartbeatUrl) {
                            this.sendHeartbeat();
                        }
                    }, 10000);
                },

                stopHeartbeat() {
                    if (this.heartbeatInterval) clearInterval(this.heartbeatInterval);
                },

                async sendHeartbeat() {
                    const player = this.$refs.videoPlayer;
                    if (!player) return;
                    const currentTime = Math.floor(player.currentTime);
                    const duration = Math.floor(player.duration);
                    
                    try {
                        await fetch(this.heartbeatUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({
                                current_position: currentTime,
                                duration: duration,
                                is_completed: (currentTime / duration) > 0.9
                            })
                        });
                    } catch (e) {
                        console.error("Heartbeat failed", e);
                    }
                },

                getYoutubeId(url) {
                    if (!url) return '';
                    if (url.length === 11 && !url.includes('.')) return url;
                    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
                    const match = url.match(regExp);
                    return (match && match[2].length === 11) ? match[2] : (this.videoPlaybackData?.provider_id || url);
                },

                getVimeoId(url) {
                    if (!url) return '';
                    if (/^\d+$/.test(url)) return url;
                    const match = url.match(/vimeo\.com\/(\d+)/);
                    return match ? match[1] : (this.videoPlaybackData?.provider_id || url);
                },

                openMeetingModal() {
                    this.showMeetingModal = true;
                    if (this.meetingLinks.length === 0) {
                        this.addRoom();
                    }
                },
                addRoom() {
                    this.meetingLinks.push({ title: 'Main Room', link: '', end_time: '' });
                },
                removeRoom(index) {
                    this.meetingLinks.splice(index, 1);
                },
                
                // Quiz Cloning State
                clonableQuizzes: [],
                quizSearch: '',
                cloning: false,
                
                async fetchClonableQuizzes() {
                    try {
                        const response = await fetch(`/quizzes/fetch?query=${this.quizSearch}`);
                        this.clonableQuizzes = await response.json();
                    } catch (e) {
                        console.error('Error fetching quizzes', e);
                    }
                },
                
                async cloneQuiz(sourceQuizId) {
                    if (!confirm('Are you sure you want to clone this quiz? All questions and options will be copied.')) return;
                    this.cloning = true;
                    try {
                        const response = await fetch('/quizzes/clone', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                source_quiz_id: sourceQuizId,
                                target_session_id: '{{ $session->session_id }}'
                            })
                        });
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            alert('Error cloning quiz');
                        }
                    } catch (e) {
                        console.error('Error cloning quiz', e);
                    } finally {
                        this.cloning = false;
                    }
                },

                // Attendance State
                wifiOpen: false,
                qrOpen: false,
                wifiSubnet: null,
                wifiCheckins: [],
                pollingInterval: null,
                qrInterval: 30,
                qrToken: null,
                qrRefreshTimer: null,
                lat: null,
                lng: null,

                async toggleWifiAttendance() {
                    if (!this.wifiOpen) {
                         // When opening, try to get location
                         await this.updateLocation();
                    }
                    const action = this.wifiOpen ? 'close' : 'open';
                    try {
                        const response = await fetch(`/sessions/${'{{ $sessionId }}'}/attendance/wifi/${action}`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                type: 'wifi',
                                lat: this.lat,
                                lng: this.lng
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.wifiOpen = data.is_wifi_open;
                            if (this.wifiOpen) {
                                this.startPolling();
                            } else if (!this.qrOpen) {
                                this.stopPolling();
                            }
                        }
                    } catch (e) { 
                        console.error('Wifi operation failed', e); 
                        alert('Check connection/permissions.'); 
                    }
                },

                async toggleQrAttendance() {
                    if (!this.qrOpen) {
                        await this.updateLocation();
                    }
                    const action = this.qrOpen ? 'close' : 'open';
                    try {
                        const response = await fetch(`/sessions/${'{{ $sessionId }}'}/attendance/wifi/${action}`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                type: 'qr',
                                refresh_interval: this.qrInterval,
                                lat: this.lat,
                                lng: this.lng
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.qrOpen = data.is_qr_open;
                            this.qrToken = data.qr_token || null;
                            
                            if (this.qrOpen) {
                                this.startPolling();
                                this.startQrRefresh();
                            } else {
                                this.stopQrRefresh();
                                if (!this.wifiOpen) this.stopPolling();
                            }
                        }
                    } catch (e) { 
                        console.error('QR operation failed', e); 
                        alert('Check connection/permissions.'); 
                    }
                },

                async updateLocation() {
                    return new Promise((resolve) => {
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    this.lat = pos.coords.latitude;
                                    this.lng = pos.coords.longitude;
                                    resolve();
                                },
                                (err) => {
                                    console.warn('Geolocation failed', err);
                                    resolve(); // Resolve anyway to allow opening without GPS
                                },
                                { timeout: 5000 }
                            );
                        } else {
                            resolve();
                        }
                    });
                },

                async fetchWifiStatus() {
                    try {
                        const response = await fetch(`/sessions/${'{{ $sessionId }}'}/attendance/wifi/status`);
                        const data = await response.json();
                        this.wifiOpen = data.is_wifi_open;
                        this.qrOpen = data.is_qr_open;
                        this.wifiCheckins = data.checkins || [];
                        
                        if (data.qr_token && data.qr_token !== this.qrToken) {
                            this.qrToken = data.qr_token;
                        }

                        if (this.wifiOpen || this.qrOpen) {
                            this.startPolling();
                        } else {
                            this.stopPolling();
                        }
                    } catch (e) { 
                        console.error('Failed to fetch status', e); 
                    }
                },

                startPolling() {
                    if (this.pollingInterval) return;
                    this.pollingInterval = setInterval(() => {
                        if (document.visibilityState === 'visible') {
                            this.fetchWifiStatus();
                        }
                    }, 5000);
                },

                stopPolling() {
                    if (this.pollingInterval) {
                        clearInterval(this.pollingInterval);
                        this.pollingInterval = null;
                    }
                },

                // QR logic stubs to prevent reference errors
                startQrRefresh() {
                    console.log('QR Refresh started');
                },
                stopQrRefresh() {
                    console.log('QR Refresh stopped');
                }
            };
        }
    </script>

    <div x-data="sessionComponent()" x-init="fetchClonableQuizzes(); $watch('quizSearch', () => fetchClonableQuizzes()); fetchWifiStatus();">

    
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">{{ $session->topic }}</h1>
            <p class="text-muted small mb-0">
                <span class="badge bg-primary me-2">{{ $session->group->group_name }}</span>
                <span class="theme-text-main">
                    {{ \Carbon\Carbon::parse($session->session_date)->translatedFormat('d F Y') }} | {{ $session->start_time }} - {{ $session->end_time }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($isEditable)
                <button
                    @click="openMeetingModal()"
                    class="btn btn-sm btn-outline-primary px-3 rounded-pill shadow-sm"
                >
                    <i class="fas fa-video me-2"></i> Add Meeting Link
                </button>
            @endif
            <a href="{{ route('groups.show', $session->group->uuid) }}" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border theme-text-main">
                <i class="fas fa-arrow-left me-2"></i> Back to Group
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card mb-4 min-vh-100">
                <div class="card-header theme-badge-bg border-0 p-0">
                    <ul class="nav nav-tabs nav-fill border-bottom-0">
                        <li class="nav-item">
                            <button class="nav-link py-3 border-0 rounded-0 fw-bold theme-text-main" :class="activeTab === 'attendance' ? 'active theme-tab-active border-bottom border-primary border-3' : ''" @click="activeTab = 'attendance'">
                                <i class="fas fa-users me-2"></i> Attendance & Ratings
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-3 border-0 rounded-0 fw-bold theme-text-main" :class="activeTab === 'assignments' ? 'active theme-tab-active border-bottom border-primary border-3' : ''" @click="activeTab = 'assignments'">
                                <i class="fas fa-tasks me-2"></i> Assignments ({{ count($assignments) }})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-3 border-0 rounded-0 fw-bold theme-text-main" :class="activeTab === 'quizzes' ? 'active theme-tab-active border-bottom border-primary border-3' : ''" @click="activeTab = 'quizzes'">
                                <i class="fas fa-question-circle me-2"></i> Quizzes ({{ count($quizzes) }})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-3 border-0 rounded-0 fw-bold theme-text-main" :class="activeTab === 'materials' ? 'active theme-tab-active border-bottom border-primary border-3' : ''" @click="activeTab = 'materials'">
                                <i class="fas fa-file-alt me-2"></i> Materials ({{ count($materials) }})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-3 border-0 rounded-0 fw-bold theme-text-main" :class="activeTab === 'videos' ? 'active theme-tab-active border-bottom border-primary border-3' : ''" @click="activeTab = 'videos'">
                                <i class="fas fa-video me-2"></i> Videos ({{ count($videos) }})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-3 border-0 rounded-0 fw-bold theme-text-main" :class="activeTab === 'meetings' ? 'active theme-tab-active border-bottom border-primary border-3' : ''" @click="activeTab = 'meetings'">
                                <i class="fas fa-video-camera me-1"></i> Meeting
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4 theme-card">
                    <!-- Attendance Tab -->
                    <div x-show="activeTab === 'attendance'">
                        <!-- Attendance Selection & QR Section -->
                        <div class="row mb-4 g-4">
                            <!-- Method 1: WiFi -->
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm rounded-4 theme-card h-100 overflow-hidden" 
                                     :class="wifiOpen ? 'border-start border-4 border-success' : 'border-start border-4 border-secondary'">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle p-2 me-2" :class="wifiOpen ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary'">
                                                    <i class="fas fa-wifi fa-lg" :class="wifiOpen ? 'fa-beat' : ''"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0 theme-text-main">Method 1: WiFi</h6>
                                                    <small :class="wifiOpen ? 'text-success fw-bold' : 'text-muted'" x-text="wifiOpen ? 'ACTIVE' : 'INACTIVE'"></small>
                                                </div>
                                            </div>
                                            <button type="button" @click="toggleWifiAttendance()" 
                                                    class="btn btn-sm rounded-pill px-3 fw-bold shadow-sm"
                                                    :class="wifiOpen ? 'btn-danger' : 'btn-success'">
                                                <i class="fas" :class="wifiOpen ? 'fa-stop-circle' : 'fa-play-circle'"></i>
                                                <span x-text="wifiOpen ? ' Stop' : ' Start'"></span>
                                            </button>
                                        </div>
                                        <div x-show="wifiOpen" class="p-2 theme-badge-bg rounded-3 small theme-text-main border theme-border mb-0">
                                            <i class="fas fa-info-circle me-1 text-primary"></i> 
                                            Shared Network Only.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Method 2: QR Code -->
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm rounded-4 theme-card h-100 overflow-hidden" 
                                     :class="qrOpen ? 'border-start border-4 border-primary' : 'border-start border-4 border-secondary'">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle p-2 me-2" :class="qrOpen ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary'">
                                                    <i class="fas fa-qrcode fa-lg"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0 theme-text-main">Method 2: QR Code</h6>
                                                    <small :class="qrOpen ? 'text-primary fw-bold' : 'text-muted'" x-text="qrOpen ? 'ACTIVE' : 'INACTIVE'"></small>
                                                </div>
                                            </div>
                                            <button type="button" @click="toggleQrAttendance()" 
                                                    class="btn btn-sm rounded-pill px-3 fw-bold shadow-sm"
                                                    :class="qrOpen ? 'btn-danger' : 'btn-primary'">
                                                <i class="fas" :class="qrOpen ? 'fa-stop-circle' : 'fa-play-circle'"></i>
                                                <span x-text="qrOpen ? ' Stop' : ' Start'"></span>
                                            </button>
                                        </div>
                                        
                                        <div x-show="qrOpen" class="text-center mt-2">
                                            <div id="qrcode" class="d-inline-block p-2 bg-white rounded-3 mb-2" x-init="$watch('qrToken', (val) => { 
                                                if(val) {
                                                    document.getElementById('qrcode').innerHTML = '';
                                                    new QRCode(document.getElementById('qrcode'), {
                                                        text: window.location.origin + '/s/' + '{{ $sessionId }}' + '/check-in?token=' + val,
                                                        width: 120,
                                                        height: 120
                                                    });
                                                }
                                            })"></div>
                                            <div class="small theme-text-main opacity-75">GPS Verification Enabled</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm rounded-4 theme-card h-100 p-4">
                                    <h6 class="fw-bold mb-3 theme-text-main d-flex justify-content-between">
                                        <span><i class="fas fa-list-ul me-2 text-primary"></i> Live Log</span>
                                        <span class="badge bg-primary rounded-pill" x-text="wifiCheckins.length"></span>
                                    </h6>
                                    <div class="overflow-auto" style="max-height: 150px;">
                                        <template x-if="wifiCheckins.length === 0">
                                            <div class="text-center py-2 text-muted small">Waiting for check-ins...</div>
                                        </template>
                                        <div class="list-group list-group-flush small">
                                            <template x-for="log in wifiCheckins" :key="log.ip_address + log.recorded_at">
                                                <div class="list-group-item bg-transparent theme-text-main border-0 py-1 d-flex justify-content-between align-items-center px-0">
                                                    <span class="fw-bold text-truncate" style="max-width: 120px;" x-text="log.student_name"></span>
                                                    <span class="badge theme-badge-bg theme-text-main border theme-border" x-text="log.ago"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="{{ route('sessions.update', $session->uuid ?? $session->session_id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="alert border-warning border-start border-4 bg-warning-subtle text-dark p-3 rounded-4 mb-4 d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">Attention Required: Physical Verification</h6>
                                    <p class="mb-0 small">Please physically count the students in the room. Verify the actual headcount matches the "Present" count below.</p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle theme-text-main">
                                    <thead class="theme-badge-bg">
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Attendance</th>
                                            <th>Rating</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($students as $student)
                                            <tr class="border-bottom theme-border">
                                                <td class="fw-bold">{{ $student->student_name }}</td>
                                                <td>
                                                    <select
                                                        name="attendance[{{ $student->student_id }}]"
                                                        class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3 text-white"
                                                        :class="{
                                                            'bg-success': attendance[{{ $student->student_id }}] === 'present',
                                                            'bg-danger': attendance[{{ $student->student_id }}] === 'absent',
                                                            'bg-warning text-dark': attendance[{{ $student->student_id }}] === 'late',
                                                            'bg-info': attendance[{{ $student->student_id }}] === 'excused'
                                                        }"
                                                        x-model="attendance[{{ $student->student_id }}]"
                                                        @if(!$isEditable) disabled @endif
                                                    >
                                                        <option value="present">Present</option>
                                                        <option value="absent">Absent</option>
                                                        <option value="late">Late</option>
                                                        <option value="excused">Excused</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select
                                                        name="rating[{{ $student->student_id }}]"
                                                        class="form-select form-select-sm border shadow-sm rounded-pill theme-badge-bg theme-text-main theme-border"
                                                        x-model="ratings[{{ $student->student_id }}]"
                                                        @if(!$isEditable) disabled @endif
                                                    >
                                                        <option value="0">Not Rated</option>
                                                        <option value="1">1 - Poor</option>
                                                        <option value="2">2 - Fair</option>
                                                        <option value="3">3 - Good</option>
                                                        <option value="4">4 - Very Good</option>
                                                        <option value="5">Excellent</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    @if(isset($submissionData[$student->student_id]) && collect($submissionData[$student->student_id])->some(fn($s) => $s))
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Submitted</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Pending</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($isEditable)
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-primary px-5 rounded-pill shadow">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </div>
                            @endif
                        </form>
                    </div>

                    <!-- Assignments Tab -->
                    <div x-show="activeTab === 'assignments'">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0 theme-text-main">Assignments</h5>
                            @if($isEditable)
                                <a href="{{ route('assignments.create', ['session_id' => $session->uuid ?? $session->session_id, 'group_id' => $session->group->uuid]) }}" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-plus me-2"></i> Add New
                                </a>
                            @endif
                        </div>
                        @forelse($assignments as $assignment)
                            <div class="card border p-3 mb-3 rounded-4 shadow-sm theme-card theme-border">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="fw-bold mb-1">{{ $assignment->title }}</h6>
                                        <p class="small text-muted mb-2">{{ $assignment->description }}</p>
                                        <div class="d-flex gap-3 small text-muted">
                                            <span><i class="fas fa-calendar-alt me-1"></i> Due: {{ $assignment->due_date }}</span>
                                            @if($assignment->teacher_file)
                                                <a href="/{{ $assignment->teacher_file }}" target="_blank" class="text-primary text-decoration-none">
                                                    <i class="fas fa-paperclip me-1"></i> Attachment
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                    @if($isEditable)
                                        <div class="d-flex gap-2 align-items-start">
                                            <a href="{{ route('assignments.edit', $assignment->uuid ?? $assignment->assignment_id) }}" class="btn theme-badge-bg btn-sm rounded-circle theme-text-main border theme-border"><i class="fas fa-edit text-warning"></i></a>
                                            <form action="{{ route('assignments.destroy', $assignment->assignment_id) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn theme-badge-bg btn-sm rounded-circle border theme-border"><i class="fas fa-trash text-danger"></i></button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-tasks fa-3x mb-3 opacity-25"></i>
                                <p>No assignments found for this session.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Quizzes Tab -->
                    <div x-show="activeTab === 'quizzes'">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Quizzes</h5>
                            @if($isEditable)
                                <div class="d-flex gap-2">
                                    <button @click="showCloneModal = true" class="btn theme-badge-bg btn-sm rounded-pill px-3 theme-text-main border theme-border">
                                        <i class="fas fa-copy me-2 text-primary"></i> Clone Existing
                                    </button>
                                    <a href="{{ route('quizzes.create', ['session_id' => $session->uuid ?? $session->session_id]) }}" class="btn btn-primary btn-sm rounded-pill px-3">
                                        <i class="fas fa-plus me-2"></i> Add Quiz
                                    </a>
                                </div>
                            @endif
                        </div>
                        <div class="table-responsive">
                            <table class="table theme-text-main">
                                <thead class="theme-badge-bg">
                                    <tr class="theme-border">
                                        <th>Title</th>
                                        <th>Limit</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($quizzes as $quiz)
                                        <tr class="theme-border">
                                            <td class="fw-bold">{{ $quiz->title }}</td>
                                            <td>{{ $quiz->time_limit ? $quiz->time_limit . ' mins' : 'No limit' }}</td>
                                            <td>
                                                <span class="badge rounded-pill {{ $quiz->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                    {{ $quiz->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('quizzes.show', $quiz->uuid ?? $quiz->quiz_id) }}" class="btn theme-badge-bg border theme-border"><i class="fas fa-eye text-info"></i></a>
                                                    @if($isEditable)
                                                        <a href="{{ route('quizzes.edit', $quiz->uuid ?? $quiz->quiz_id) }}" class="btn theme-badge-bg border theme-border"><i class="fas fa-edit text-warning"></i></a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Materials Tab -->
                    <div x-show="activeTab === 'materials'">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Lesson Materials</h5>
                            @if($isEditable)
                                <button @click="showMaterialModal = true" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-upload me-2"></i> Upload
                                </button>
                            @endif
                        </div>
                        <ul class="list-group list-group-flush border rounded-4 overflow-hidden theme-border">
                            @foreach($materials as $m)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3 theme-card theme-border">
                                    <div class="d-flex align-items-center">
                                        <div class="theme-badge-bg p-2 rounded theme-text-main me-3 border theme-border">
                                            <i class="fas fa-file-pdf fa-lg text-primary"></i>
                                        </div>
                                        <div>
                                            <p class="mb-0 fw-bold">{{ $m->original_name }}</p>
                                            <small class="text-muted">{{ round($m->size / 1024) }} KB • {{ $m->created_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('sessions.materials.download', $m->id) }}" class="btn theme-badge-bg border theme-border theme-text-main"><i class="fas fa-download text-primary"></i></a>
                                        @if($isEditable)
                                            <form action="{{ route('sessions.materials.destroy', $m->id) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn theme-badge-bg border theme-border"><i class="fas fa-trash text-danger"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Videos Tab -->
                    <div x-show="activeTab === 'videos'">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Recordings</h5>
                            @if($isEditable)
                                <button @click="showUploadVideoModal = true" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-video me-2"></i> Upload Video
                                </button>
                            @endif

                        </div>
                        <div class="row g-4">
                            @foreach($videos as $v)
                                <div class="col-md-4">
                                    <div class="card h-100 border rounded-4 overflow-hidden shadow-sm transition theme-card theme-border">
                                        <div class="position-relative bg-dark" style="height: 180px;">
                                            @if(isset($v->thumbnail_url))
                                                <img 
                                                    src="{{ in_array($v->provider, ['youtube', 'vimeo']) ? $v->thumbnail_url : '/storage/' . $v->thumbnail_url }}" 
                                                    class="w-100 h-100 object-fit-cover opacity-75"
                                                    alt="{{ $v->title }}"
                                                >
                                            @else
                                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white-50">
                                                    <i class="fas fa-video fa-3x"></i>
                                                </div>
                                            @endif
                                            <div class="position-absolute top-50 start-50 translate-middle">
                                                <button @click="openVideoPlayer(@js($v))" class="btn btn-primary rounded-circle p-3 shadow-lg">
                                                    <i class="fas fa-play fa-lg"></i>
                                                </button>
                                            </div>

                                        </div>
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-0">{{ $v->title }}</h6>
                                            <p class="small text-muted mb-0 text-truncate">{{ $v->description ?? 'No description' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Meetings Tab -->
                    <div x-show="activeTab === 'meetings'">
                        <div class="row g-3 mb-4">
                            <template x-for="(m, i) in meetingLinks" :key="m.id">
                                <div class="col-md-6">
                                    <div class="card border-0 rounded-4 shadow-sm h-100 overflow-hidden" :class="m.is_closed ? 'bg-light border-start border-4 border-danger' : 'border-start border-4 border-primary'">
                                        <div class="card-body p-4">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                                                     :class="m.is_closed ? 'bg-danger bg-opacity-10 text-danger' : 'bg-primary bg-opacity-10 text-primary'"
                                                     style="width: 52px; height: 52px;">
                                                    <i class="fas fa-video fa-lg"></i>
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <h6 class="fw-bold mb-1 theme-text-main" x-text="m.title || 'Main Room'"></h6>
                                                    <p class="small text-muted text-truncate mb-0" x-text="m.link"></p>
                                                    <p class="extra-small mt-1 mb-0" :class="m.is_closed ? 'text-danger fw-bold' : 'text-muted'">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <span x-show="m.end_time">Ends at: <span x-text="m.end_time"></span></span>
                                                        <span x-show="!m.end_time">No expiry set</span>
                                                        <template x-if="m.is_closed">
                                                            <span class="ms-2">| CLOSED MANUALLY</span>
                                                        </template>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <a :href="m.link" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    <i class="fas fa-external-link me-1"></i>Open
                                                </a>
                                                <button type="button" 
                                                        @click="
                                                            fetch(`/sessions/meeting/${m.id}/toggle`, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                                                            .then(res => res.json())
                                                            .then(data => { m.is_closed = data.is_closed; });
                                                        "
                                                        class="btn btn-sm rounded-pill px-3 shadow-sm"
                                                        :class="m.is_closed ? 'btn-success' : 'btn-danger'">
                                                    <i class="fas" :class="m.is_closed ? 'fa-lock-open' : 'fa-lock'"></i>
                                                    <span x-text="m.is_closed ? ' Reopen Access' : ' Close Access'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals (Material, Book, Video, Meeting, Clone) -->
    <!-- Manage Meeting Links Modal -->
    <div x-show="showMeetingModal" class="modal fade" :class="{ 'show d-block': showMeetingModal }" tabindex="-1" role="dialog" style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999;" x-cloak>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card overflow-hidden">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold">إدارة روابط الاجتماع (Meeting Links)</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showMeetingModal = false"></button>
                </div>
                <form action="{{ route('sessions.update', $session->uuid ?? $session->session_id) }}" method="POST" class="p-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="topic" value="{{ $session->topic }}">
                    <input type="hidden" name="session_date" value="{{ $session->session_date }}">
                    <input type="hidden" name="start_time" value="{{ $session->start_time }}">
                    <input type="hidden" name="end_time" value="{{ $session->end_time }}">

                    <div class="mb-4">
                        <p class="text-muted small">يمكنك إضافة أكثر من رابط (مثل Google Meet) وتسمية كل غرفة باسم معين.</p>
                    </div>

                    <div class="meeting-list">
                        <template x-for="(m, i) in meetingLinks" :key="i">
                            <div class="p-3 mb-3 theme-badge-bg rounded-3 position-relative border theme-border">
                                <button type="button" @click="removeRoom(i)" class="btn btn-sm btn-link text-danger position-absolute top-0 end-0 p-0 me-2 mt-2" :disabled="meetingLinks.length <= 1">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="small fw-bold mb-1">اسم الغرفة (Title)</label>
                                        <input type="text" :name="'meetings['+i+'][title]'" class="form-control form-control-sm border-0 theme-card" placeholder="مثلاً: قاعة 1" x-model="m.title" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="small fw-bold mb-1">الرابط (Link)</label>
                                        <input type="url" :name="'meetings['+i+'][link]'" class="form-control form-control-sm border-0 theme-card" placeholder="https://meet.google.com/..." x-model="m.link" required>
                                        <input type="hidden" :name="'meetings['+i+'][id]'" x-model="m.id">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small fw-bold mb-1">وقت الزوال (End Time)</label>
                                        <input type="time" :name="'meetings['+i+'][end_time]'" class="form-control form-control-sm border-0 theme-card" x-model="m.end_time">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top theme-border">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" @click="addRoom()">
                            <i class="fas fa-plus me-1"></i> إضافة غرفة أخرى
                        </button>
                        <div>
                            <button type="button" class="btn btn-sm btn-light rounded-pill px-4 me-2" @click="showMeetingModal = false">إلغاء</button>
                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 shadow">حفظ الروابط</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Clone Quiz Modal -->
    <div x-show="showCloneModal" class="modal fade" :class="{ 'show d-block': showCloneModal }" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card">
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <h5 class="modal-title">Clone Existing Quiz</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showCloneModal = false"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" placeholder="Search quizzes..." x-model="quizSearch">
                    </div>
                    <div class="list-group">
                        <template x-for="q in clonableQuizzes" :key="q.quiz_id">
                            <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" @click="cloneQuiz(q.quiz_id)">
                                <span x-text="q.title"></span>
                                <i class="fas fa-clone text-primary"></i>
                            </button>
                        </template>
                    </div>
                    <div x-show="cloning" class="text-center mt-3">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span class="ms-2">Cloning...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Material Modal -->
    <div x-show="showMaterialModal" class="modal fade" :class="{ 'show d-block': showMaterialModal }" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <h5 class="modal-title">Upload Material</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showMaterialModal = false"></button>
                </div>
                <form action="{{ route('sessions.materials.store', $session->uuid ?? $session->session_id) }}" method="POST" enctype="multipart/form-data" class="p-4">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-bold small">Choose File (PDF, DOCX, etc.)</label>
                        <input type="file" name="material" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Upload</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Video Modal -->
    <div x-show="showUploadVideoModal" class="modal fade" :class="{ 'show d-block': showUploadVideoModal }" style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold">Upload Session Video</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showUploadVideoModal = false"></button>
                </div>
                <form action="{{ route('sessions.videos.store', $session->uuid ?? $session->session_id) }}" method="POST" enctype="multipart/form-data" class="p-4">

                    @csrf
                    <div class="mb-3 d-flex justify-content-center">
                        <div class="btn-group btn-group-sm rounded-pill overflow-hidden border theme-border shadow-sm p-1 bg-light">
                            <input type="radio" class="btn-check" name="provider" value="local" id="prov_local" checked x-model="provider">
                            <label class="btn btn-outline-primary border-0 rounded-pill px-3" for="prov_local">Local File</label>
                            
                            <input type="radio" class="btn-check" name="provider" value="youtube" id="prov_yt" x-model="provider">
                            <label class="btn btn-outline-primary border-0 rounded-pill px-3" for="prov_yt">YouTube</label>
                            
                            <input type="radio" class="btn-check" name="provider" value="vimeo" id="prov_vm" x-model="provider">
                            <label class="btn btn-outline-primary border-0 rounded-pill px-3" for="prov_vm">Vimeo</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small theme-text-main">Video Title</label>
                        <input type="text" name="title" class="form-control theme-card border theme-border" placeholder="e.g. Session Recording - Part 1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small theme-text-main">Description (Optional)</label>
                        <textarea name="description" class="form-control theme-card border theme-border" rows="2"></textarea>
                    </div>

                    <div x-show="provider === 'local'" class="mb-3" x-transition>
                        <label class="form-label fw-bold small theme-text-main">Select Video File (MP4)</label>
                        <input type="file" name="video" class="form-control theme-card border theme-border" accept="video/*">
                    </div>

                    <div x-show="provider === 'youtube'" class="mb-3" x-transition>
                        <label class="form-label fw-bold small theme-text-main">YouTube URL</label>
                        <input type="url" name="youtube_url" class="form-control theme-card border theme-border" placeholder="https://www.youtube.com/watch?v=...">
                    </div>

                    <div x-show="provider === 'vimeo'" class="mb-3" x-transition>
                        <label class="form-label fw-bold small theme-text-main">Vimeo URL</label>
                        <input type="url" name="vimeo_url" class="form-control theme-card border theme-border" placeholder="https://vimeo.com/...">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small theme-text-main">Visibility</label>
                            <select name="visibility" class="form-select theme-card border theme-border">
                                <option value="group">Group Only</option>
                                <option value="public">Public (Library)</option>
                                <option value="private">Private (Admin only)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small theme-text-main">Price (0 for Free)</label>
                            <input type="number" name="price" class="form-control theme-card border theme-border" value="0" step="0.01">
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 shadow fw-bold">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Save Recording
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Video Playback Modal -->
    <div x-show="showPlaybackModal" class="modal fade" :class="{ 'show d-block': showPlaybackModal }" x-cloak style="background: #000; z-index: 9999;">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content border-0 rounded-0 bg-black position-relative overflow-hidden">
                <!-- Header Overlay -->
                <div class="position-absolute top-0 start-0 w-100 p-4 d-flex justify-content-between align-items-center z-3" style="background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);">
                    <h5 class="text-white mb-0 fw-bold" x-text="videoPlaybackData?.title"></h5>
                    <button type="button" class="btn btn-link text-white text-decoration-none bg-white bg-opacity-10 rounded-circle p-2" @click="closeVideoPlayer" style="width: 45px; height: 45px;">
                        <i class="fas fa-times fs-5"></i>
                    </button>
                </div>

                <!-- Watermark -->
                <div x-show="isPlaying" 
                     class="position-absolute text-white opacity-25 fw-bold select-none pointer-events-none z-2"
                     :style="`left: ${watermarkPos.x}%; top: ${watermarkPos.y}%; transition: all 1s linear; font-size: 1.2rem;`"
                     x-text="'{{ Auth::user()->email }}'">
                </div>

                <!-- Player Interface -->
                <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-black">
                    <template x-if="videoPlaybackData?.provider === 'youtube'">
                        <iframe :src="`https://www.youtube.com/embed/${getYoutubeId(videoPlaybackUrl)}?autoplay=1&modestbranding=1&rel=0`" 
                                class="w-100 h-100 border-0" allow="autoplay; encrypted-media; fullscreen"></iframe>
                    </template>
                    <template x-if="videoPlaybackData?.provider === 'vimeo'">
                        <iframe :src="`https://player.vimeo.com/video/${getVimeoId(videoPlaybackUrl)}?autoplay=1`" 
                                class="w-100 h-100 border-0" allow="autoplay; fullscreen"></iframe>
                    </template>
                    <template x-if="videoPlaybackData?.provider === 'local'">
                        <div class="w-100 h-100 position-relative">
                            <video x-ref="videoPlayer" 
                                   class="w-100 h-100" 
                                   controls 
                                   controlsList="nodownload"
                                   @play="isPlaying = true"
                                   @pause="isPlaying = false">
                                <source :src="videoPlaybackUrl" type="video/mp4">
                            </video>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .theme-tab-active { background-color: var(--bg-main) !important; color: var(--bs-primary) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    [x-cloak] { display: none !important; }
    .nav-tabs .nav-link { color: var(--text-main); opacity: 0.6; }
    .nav-tabs .nav-link.active { color: var(--bs-primary); opacity: 1; }
</style>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    // QR logic is now managed by Alpine.js methods
</script>
@endpush
