<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;

use App\Models\CertificateRequest;
use App\Policies\CertificateRequestPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CertificateRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'nullable|exists:courses,course_id',
            'group_id' => 'required|exists:groups,group_id',
            'reason' => 'required|string|max:500',
        ]);

        // Check if user already has a pending request
        $existingRequest = CertificateRequest::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending certificate request.',
            ], 422);
        }

        // Get course_id from the selected group
        $group = \App\Models\Group::find($request->group_id);
        $courseId = $request->course_id ?: $group->course_id;

        CertificateRequest::create([
            'user_id' => Auth::id(),
            'course_id' => $courseId,
            'group_id' => $request->group_id,
            'status' => 'pending',
            'remarks' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Certificate request submitted successfully.',
        ]);
    }

    public function approve(Request $request, CertificateRequest $certificateRequest)
    {
        $policy = new CertificateRequestPolicy;

        if (! $policy->approve(Auth::user(), $certificateRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to approve this request.',
            ], 403);
        }

        $certificateRequest->update(['status' => 'approved']);

        // Create certificate automatically
        $certificate = \App\Models\Certificate::create([
            'user_id' => $certificateRequest->user_id,
            'template_id' => 1, // Individual template
            'course_id' => $certificateRequest->course_id,
            'group_id' => $certificateRequest->group_id,
            'issued_by' => Auth::id(),
            'certificate_number' => 'CERT-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'issue_date' => now(),
            'status' => 'draft',
            'remarks' => $certificateRequest->remarks,
        ]);

        return redirect()->route('certificates.edit', $certificate->id)
            ->with('success', 'Certificate request approved | تم قبول طلب الشهادة وتجهيز المسودة.');
    }

    public function reject(Request $request, CertificateRequest $certificateRequest)
    {
        $policy = new CertificateRequestPolicy;

        if (! $policy->approve(Auth::user(), $certificateRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to reject this request.',
            ], 403);
        }

        $certificateRequest->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes
        ]);

        return redirect()->back()->with('success', 'Certificate request rejected | تم رفض طلب الشهادة.');
    }


    public function index()
    {
        $requests = CertificateRequest::with(['user', 'course', 'group'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('certificates.admin.requests', compact('requests'));
    }
}
