<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use App\Models\Book;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class BookController extends Controller
{
    public function store(Request $request, $sessionId)
    {
        $request->validate([
            'book' => 'required|mimes:pdf|max:20480', // 20MB max
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,private,group',
            'price' => 'nullable|numeric|min:0',
        ]);

        $session = Session::where('uuid', $sessionId)->orWhere('session_id', $sessionId)->firstOrFail();

        $file = $request->file('book');
        $fileName = time().'_'.$file->getClientOriginalName();
        $filePath = $file->storeAs('secure_books', $fileName, 'local');

        $book = Book::create([
            'uuid' => (string) Str::uuid(),
            'session_id' => $session->session_id,
            'group_id' => $session->group_id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $filePath,
            'visibility' => $request->visibility,
            'price' => $request->price ?? 0,
            'status' => 'ready',
        ]);

        return back()->with('success', 'Book uploaded successfully.');
    }

    public function view($id)
    {
        $book = Book::where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        
        if (!$this->authorizeBookAccess(Auth::user(), $book)) {
            abort(403, 'Unauthorized access to this book.');
        }

        $fileExists = Storage::disk('local')->exists($book->file_path);

        return view('admin.library.view_book', [
            'book' => $book,
            'pdfUrl' => URL::temporarySignedRoute(
                'student.books.stream',
                now()->addMinutes(60),
                ['id' => $book->id]
            ),
            'fileExists' => $fileExists
        ]);
    }

    public function stream($id)
    {
        if (!request()->hasValidSignature()) {
            abort(403);
        }

        $book = Book::findOrFail($id);
        
        if (!$this->authorizeBookAccess(Auth::user(), $book)) {
            abort(403, 'Unauthorized access to this book.');
        }

        if (!Storage::disk('local')->exists($book->file_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($book->file_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $book->title . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    protected function authorizeBookAccess($user, $book)
    {
        if (!$user) return false;

        if ($user->isAdmin() || $user->hasRole('teacher')) return true;

        $student = DB::table('students')->where('user_id', $user->id)->first();
        if (!$student) return false;

        // Check if book is public and free
        if ($book->visibility === 'public' && $book->price == 0) return true;

        // Check assigned groups
        $studentGroups = DB::table('student_group')
            ->where('student_id', $student->student_id)
            ->pluck('group_id')
            ->toArray();
            
        $isMember = DB::table('book_group')
            ->where('book_id', $book->id)
            ->whereIn('group_id', $studentGroups)
            ->exists();

        // Check access type logic
        if ($book->visibility === 'group' && !$isMember) {
            return false;
        }

        // Check for specific booking or payment if it's a paid book
        if ($book->price > 0) {
            // 1. Check direct library access
            $hasPaid = DB::table('student_library_access')
                ->where('student_id', $student->student_id)
                ->where('book_id', $book->id)
                ->exists();
                
            // 2. Fallback to session booking if applicable
            if (!$hasPaid && $book->session_id) {
                $hasPaid = DB::table('bookings')
                    ->where('student_id', $student->student_id)
                    ->where('session_id', $book->session_id)
                    ->where('payment_status', 'completed')
                    ->exists();
            }

            if (!$hasPaid) return false;
        }

        if ($book->visibility === 'private' && !$user->isAdmin()) return false;

        return true;
    }

    public function destroy($id)
    {
        $book = Book::findOrFail($id);
        
        $user = Auth::user();
        if (!$user->isAdmin()) {
            if ($book->session?->group?->teacher_id != $user->teacher?->teacher_id) {
                abort(403);
            }
        }

        Storage::disk('local')->delete($book->file_path);
        $book->delete();

        return back()->with('success', 'Book deleted successfully.');
    }
}
