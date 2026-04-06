@extends('layouts.authenticated')

@section('title', 'My Certificates')

@section('content')
<div class="container-fluid py-4 min-vh-100 p-0" x-data="certificatesPage()">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 fw-bold text-dark">
            <i class="fas fa-certificate text-primary me-2"></i>
            My Certificates
        </h1>
        <a href="{{ route('student.dashboard.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm fw-bold px-3">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row g-4">
        <!-- Main Content: Certificates List -->
        <div class="col-lg-8">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="mb-0 fw-bold">Issued Certificates</h5>
                </div>
                <div class="card-body p-0">
                    @if(count($certificates) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Course / Group</th>
                                        <th class="py-3 border-0">Issue Date</th>
                                        <th class="py-3 border-0 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($certificates as $cert)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="fw-bold text-dark">{{ $cert->course->course_name ?? 'N/A' }}</div>
                                                <div class="text-muted small">{{ $cert->group->group_name ?? 'N/A' }}</div>
                                            </td>
                                            <td class="py-3 text-muted small">{{ \Carbon\Carbon::parse($cert->issue_date)->format('M d, Y') }}</td>
                                            <td class="py-3 text-center">
                                                <a href="{{ route('student.certificates.view', $cert->id) }}" class="btn btn-sm btn-primary rounded-pill px-3" target="_blank">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-award fa-4x mb-3 opacity-25"></i>
                            <h4>No certificates issued yet</h4>
                            <p>Complete your courses to earn certificates!</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar: Request Certificate -->
        <div class="col-lg-4">
            <div class="card border-0 rounded-4 shadow-sm mb-4 bg-primary text-white overflow-hidden">
                <div class="card-body p-4 position-relative">
                    @if(session('success'))
                        <div class="alert alert-success border-0 rounded-3 shadow-sm mb-3">
                            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-3">
                            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                        </div>
                    @endif

                    <h5 class="fw-bold mb-3 position-relative z-index-1">Request Certificate | طلب شهادة</h5>

                    <p class="small opacity-75 mb-4 position-relative z-index-1">
                        If you have completed a course and haven't received your certificate, you can request it here.
                        <br>إذا أكملت دورة ولم تستلم شهادتك، يمكنك طلبها من هنا.
                    </p>

                    @if($pendingRequest)
                        <div class="alert bg-white bg-opacity-20 border-white border-opacity-25 text-white mb-0">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Request Pending</strong>
                            <div class="extra-small mt-1" style="font-size: 0.75rem;">Requested on {{ \Carbon\Carbon::parse($pendingRequest->created_at)->format('M d, Y') }}</div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('student.certificates.request') }}" id="requestForm" @submit="processing = true">

                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-white opacity-75">Select Course / Group | اختر الدورة</label>
                                <select name="group_id" class="form-select border-white border-opacity-25 text-white rounded-3 shadow-none fw-medium" style="background-color: rgba(255, 255, 255, 0.15); color: white !important;" required>
                                    <option value="" class="text-dark">Choose a group...</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->group_id }}" class="text-dark" {{ (isset($preSelectedGroupId) && $preSelectedGroupId == $group->group_id) ? 'selected' : '' }}>
                                            {{ $group->course->course_name }} ({{ $group->group_name }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-white opacity-75">Reason / Notes | ملاحظات (Optional)</label>
                                <textarea name="reason" class="form-control border-white border-opacity-25 text-white rounded-3 shadow-none fw-medium" rows="2" style="background-color: rgba(255, 255, 255, 0.15); color: white !important;" placeholder="e.g. Completed all requirements..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-light btn-lg rounded-pill w-100 fw-bold shadow-sm" :disabled="processing" @click="if(!confirm('Are you sure you want to request a certificate? | هل أنت متأكد من طلب الشهادة؟')) { $event.preventDefault(); }">
                                <span x-show="!processing"><i class="fas fa-paper-plane me-2"></i>Submit Request | إرسال الطلب</span>
                                <span x-show="processing"><span class="spinner-border spinner-border-sm me-2"></span>Submitting... | جاري الإرسال...</span>
                            </button>

                        </form>
                    @endif
                    <i class="fas fa-certificate position-absolute end-0 bottom-0 mb-n3 me-n3 opacity-25" style="font-size: 10rem;"></i>
                </div>
            </div>

            <div class="card border-0 rounded-4 shadow-sm bg-white">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="width: 80px; height: 80px;">
                        <i class="fas fa-user-graduate fa-2x text-primary"></i>
                    </div>
                    <h6 class="fw-bold mb-1">{{ $student->student_name ?? Auth::user()->name }}</h6>
                    <p class="text-muted small mb-0">{{ $student->student_email ?? Auth::user()->email }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function certificatesPage() {
    return {
        processing: false
    };
}
</script>
@endsection
