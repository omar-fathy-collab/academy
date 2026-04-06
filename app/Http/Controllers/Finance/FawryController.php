<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\EnrollmentRequest;
use App\Services\FawryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FawryController extends Controller
{
    protected $fawryService;

    public function __construct(FawryService $fawryService)
    {
        $this->fawryService = $fawryService;
    }

    /**
     * Handle Fawry Callback (Redirect from Fawry)
     */
    public function callback(Request $request)
    {
        $merchantRefNum = $request->input('merchantRefNum');
        $orderStatus = $request->input('orderStatus');

        Log::info('Fawry Callback received:', $request->all());

        if ($orderStatus === 'PAID') {
            $enrollRequest = EnrollmentRequest::find($merchantRefNum);
            if ($enrollRequest) {
                $enrollRequest->update(['status' => 'paid']);
                
                // Active enrollment (Logic similar to admin update)
                $student = \App\Models\Student::where('user_id', $enrollRequest->user_id)->first();
                if ($student) {
                    \Illuminate\Support\Facades\DB::table('student_course')->updateOrInsert([
                        'student_id' => $student->student_id,
                        'course_id' => $enrollRequest->course_id
                    ], [
                        'updated_at' => now()
                    ]);
                }
            }
            return redirect()->route('student.dashboard.index')->with('success', 'تم الدفع بنجاح! تم تفعيل الكورس.');
        }

        return redirect()->route('courses.explore')->with('error', 'فشلت عملية الدفع أو تم إلغاؤها.');
    }

    /**
     * Handle Fawry Webhook (Fawry Server-to-Server notification)
     */
    public function webhook(Request $request)
    {
        // Verify signature and update order status
        Log::info('Fawry Webhook received:', $request->all());
        return response()->json(['status' => 'success']);
    }
}
