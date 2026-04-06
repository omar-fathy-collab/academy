@extends('layouts.authenticated')

@section('title', 'Certificate Requests')

@section('content')
<div class="container-fluid py-4 min-vh-100">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Certificate Requests | طلبات الشهادات</h2>
            <p class="text-muted mb-0">Manage and approve student certificate applications.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary rounded-pill px-3 py-2 fs-6 shadow-sm">
                {{ $requests->total() }} Total Requests
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2 fs-4"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light bg-opacity-50">
                        <tr>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Student</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Course / Group</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Reason</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Status</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Date</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $request)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $request->user->name ?? 'Unknown Student' }}</div>
                                            <div class="extra-small text-muted">{{ $request->user->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="fw-bold text-primary small">{{ $request->course->course_name ?? 'N/A' }}</div>
                                    <div class="extra-small text-muted">{{ $request->group->group_name ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-muted small text-truncate" style="max-width: 200px;" title="{{ $request->remarks }}">
                                        {{ $request->remarks ?: 'No reason provided' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $badgeClass = [
                                            'pending' => 'bg-warning-subtle text-warning border-warning-subtle',
                                            'approved' => 'bg-success-subtle text-success border-success-subtle',
                                            'rejected' => 'bg-danger-subtle text-danger border-danger-subtle',
                                        ][$request->status] ?? 'bg-secondary-subtle text-secondary';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} rounded-pill px-3 py-2 border text-uppercase fs-xs">
                                        {{ $request->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-muted small">
                                    {{ $request->created_at->format('M d, Y') }}
                                    <div class="extra-small opacity-75">{{ $request->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    @if($request->status === 'pending')
                                        <div class="d-flex justify-content-end gap-2">
                                            <form action="{{ route('certificate_requests.approve', $request->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="return confirm('Approve this request and generate certificate? | هل أنت متأكد من الموافقة؟')">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold" 
                                                    data-bs-toggle="modal" data-bs-target="#rejectModal{{ $request->id }}">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </div>

                                        {{-- Reject Modal --}}
                                        <div class="modal fade" id="rejectModal{{ $request->id }}" tabindex="-1" aria-hidden="true text-start">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 rounded-4 shadow">
                                                    <div class="modal-header border-0 pt-4 px-4">
                                                        <h5 class="modal-title fw-bold">Reject Certificate Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form action="{{ route('certificate_requests.reject', $request->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-body px-4 pb-4">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Internal Notes / Rejection Reason</label>
                                                                <textarea name="admin_notes" class="form-control rounded-3" rows="3" placeholder="Explain why this request is being rejected..." required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 px-4 pb-4 gap-2">
                                                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">Confirm Rejection</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted extra-small italic">Processed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center text-muted">
                                    <div class="mb-3 opacity-25">
                                        <i class="fas fa-inbox fa-4x"></i>
                                    </div>
                                    <h5 class="fw-bold">No requests found</h5>
                                    <p class="small mb-0">Student certificate requests will appear here once submitted.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requests->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>

<style>
    .fs-xs { font-size: 0.7rem; }
    .bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1); }
    .bg-success-subtle { background-color: rgba(25, 135, 84, 0.1); }
    .bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1); }
    .bg-secondary-subtle { background-color: rgba(108, 117, 125, 0.1); }
    .text-start { text-align: left !important; }
</style>
@endsection
