<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    /**
     * Submit a payment proof (screenshot) for an invoice.
     */
    public function submitPayment(Request $request, Invoice $invoice)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        // Ensure student owns the invoice
        if ($user->role_id == 3 && $user->student->student_id != $invoice->student_id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'payment_screenshot' => 'required|image|max:5120',
            'payment_notes' => 'nullable|string|max:1000',
        ]);

        if ($request->hasFile('payment_screenshot')) {
            // Delete old screenshot if exists
            if ($invoice->payment_screenshot) {
                Storage::disk('public')->delete($invoice->payment_screenshot);
            }

            $path = $request->file('payment_screenshot')->store('payments', 'public');
            $invoice->update([
                'payment_screenshot' => $path,
                'payment_notes' => $request->payment_notes,
                'status' => 'pending' // Mark as pending verification
            ]);
        }

        return redirect()->back()->with('success', 'Payment proof submitted successfully! Admin will verify it soon.');
    }
}
