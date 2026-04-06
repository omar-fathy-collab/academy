@extends('layouts.authenticated')

@section('title', 'Library Payments')

@section('content')
<div x-data="{ 
    selectedScreenshot: null,
    processing: false,

    async updateStatus(id, status) {
        if (!confirm(`Are you sure you want to ${status} this request?`)) return;
        
        this.processing = true;
        try {
            const response = await fetch(`{{ url('admin/library/payments') }}/${id}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ status })
            });
            
            if (response.ok) {
                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.message || 'Operation failed');
            }
        } catch (e) {
            console.error(e);
            alert('An error occurred');
        } finally {
            this.processing = false;
        }
    }
}" class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 pt-3">
        <div>
            <h2 class="fw-bold mb-1 theme-text-main shadow-smooth">
                <i class="fas fa-receipt me-2 text-primary"></i>Payment Requests
            </h2>
            <p class="text-muted small">Review manual screenshots and grant access to students.</p>
        </div>
        <a href="{{ route('admin.library') }}" class="btn btn-light rounded-pill px-4 shadow-sm">
            <i class="fas fa-arrow-left me-2"></i> Back to Library
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden glass-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light bg-opacity-50">
                    <tr>
                        <th class="px-4 py-3 border-0 text-uppercase small fw-bold">Student</th>
                        <th class="py-3 border-0 text-uppercase small fw-bold">Asset</th>
                        <th class="py-3 border-0 text-uppercase small fw-bold">Amount</th>
                        <th class="py-3 border-0 text-uppercase small fw-bold">Screenshot</th>
                        <th class="py-3 border-0 text-uppercase small fw-bold">Date</th>
                        <th class="py-3 border-0 text-uppercase small fw-bold text-end px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr class="transition-all hover-bg-light">
                            <td class="px-4">
                                <div class="fw-bold text-main">{{ $req->user->name }}</div>
                                <div class="small text-muted">{{ $req->user->email }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill {{ $req->item_type === 'video' ? 'bg-primary' : 'bg-success' }} bg-opacity-10 text-{{ $req->item_type === 'video' ? 'primary' : 'success' }} border-0 px-2 py-1">
                                        {{ strtoupper($req->item_type) }}
                                    </span>
                                    <span class="fw-medium theme-text-main">{{ $req->item->title ?? 'Deleted Item' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-primary">{{ number_format($req->amount, 2) }} EGP</span>
                            </td>
                            <td>
                                @if($req->screenshot_path)
                                    <button 
                                        @click="selectedScreenshot = '{{ asset('storage/' . $req->screenshot_path) }}'"
                                        class="btn btn-sm btn-outline-secondary rounded-pill px-3 border-dashed"
                                    >
                                        <i class="fas fa-image me-1"></i> View Screenshot
                                    </button>
                                @else
                                    <span class="text-muted italic small">No image provided</span>
                                @endif
                            </td>
                            <td class="text-muted small">
                                {{ $req->created_at->format('M d, Y') }}<br>
                                <span class="opacity-75">{{ $req->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="text-end px-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <button 
                                        @click="updateStatus({{ $req->id }}, 'approved')"
                                        :disabled="processing"
                                        class="btn btn-success btn-sm rounded-pill px-4 shadow-sm transition-all hover-scale"
                                    >
                                        Approve
                                    </button>
                                    <button 
                                        @click="updateStatus({{ $req->id }}, 'rejected')"
                                        :disabled="processing"
                                        class="btn btn-danger btn-sm rounded-pill px-4 shadow-sm transition-all hover-scale"
                                    >
                                        Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="fas fa-receipt fa-4x mb-3 opacity-25"></i>
                                    <h5 class="fw-bold">No Pending Requests</h5>
                                    <p class="mb-0">Everything is up to date!</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Screenshot Modal -->
    <div x-show="selectedScreenshot" 
         class="modal fade show" 
         style="display: block; background: rgba(0,0,0,0.85); z-index: 2000;" 
         @click="selectedScreenshot = null"
         x-transition x-cloak>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 bg-transparent shadow-none" @click.stop>
                <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn-close btn-close-white" @click="selectedScreenshot = null"></button>
                </div>
                <div class="text-center">
                    <img :src="selectedScreenshot" class="img-fluid rounded-4 shadow-2xl" alt="Screenshot" style="max-height: 85vh;">
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .glass-card {
        background: rgba(var(--bs-body-bg-rgb), 0.7) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(var(--bs-body-color-rgb), 0.08) !important;
    }
    .hover-scale:hover { transform: scale(1.05); }
    .border-dashed { border-style: dashed !important; border-width: 1px !important; }
    [x-cloak] { display: none !important; }
</style>
@endsection
