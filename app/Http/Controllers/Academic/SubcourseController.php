<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubcourseController extends Controller
{
    public function index(Request $request, $courseId = null)
    {
        $course_id = $courseId ?? $request->input('course_id');
        
        if ($course_id) {
            $course = DB::table('courses')->where('course_id', $course_id)->first();
            $subcourses = DB::table('subcourses')
                ->where('course_id', $course_id)
                ->orderBy('subcourse_number')
                ->get();
        } else {
            $course = null;
            $subcourses = DB::table('subcourses')
                ->join('courses', 'subcourses.course_id', '=', 'courses.course_id')
                ->select('subcourses.*', 'courses.course_name')
                ->orderBy('courses.course_name')
                ->orderBy('subcourse_number')
                ->get();
        }

        return view('subcourses.index', [
            'course' => $course,
            'subcourses' => $subcourses,
        ]);
    }

    public function create(Request $request)
    {
        $course_id = $request->input('course_id');
        $courses = DB::table('courses')->orderBy('course_name')->get();

        return view('subcourses.create', [
            'courses' => $courses,
            'selected_course_id' => $course_id
        ]);
    }

    public function show($id)
    {
        // Fallback for when 'add' is treated as an ID
        if ($id === 'add') {
            return redirect()->route('subcourses.create');
        }

        return $this->edit($id);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => 'required|integer',
            'subcourse_name' => 'required|string',
            'subcourse_number' => 'required|integer',
            'description' => 'nullable|string',
            'duration_hours' => 'nullable|integer',
        ]);

        $exists = \App\Models\SubCourse::where('course_id', $data['course_id'])
            ->where('subcourse_number', $data['subcourse_number'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['subcourse_number' => 'Subcourse number already exists for this course.']);
        }

        \App\Models\SubCourse::create($data);

        // FIX: Change 'course_id' to 'courseId' to match route parameter
        return redirect()->route('subcourses', ['courseId' => $data['course_id']])->with('success', 'Subcourse added successfully!');
    }

    public function edit($id)
    {
        $subcourse = \App\Models\SubCourse::findOrFail($id);
        $courses = DB::table('courses')->orderBy('course_name')->get();

        return view('subcourses.edit', [
            'subcourse' => $subcourse,
            'courses' => $courses,
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'course_id' => 'required|integer',
            'subcourse_name' => 'required|string',
            'subcourse_number' => 'required|integer',
            'description' => 'nullable|string',
            'duration_hours' => 'nullable|integer',
        ]);

        // Check if subcourse number already exists for this course (excluding current record)
        $exists = \App\Models\SubCourse::where('course_id', $data['course_id'])
            ->where('subcourse_number', $data['subcourse_number'])
            ->where('subcourse_id', '!=', $id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['subcourse_number' => 'Subcourse number already exists for this course.']);
        }

        $subcourse = \App\Models\SubCourse::findOrFail($id);
        $subcourse->update($data);

        // Fix: Use 'courseId' instead of 'course_id'
        return redirect()->route('subcourses', ['courseId' => $data['course_id']])->with('success', 'Subcourse updated successfully!');
    }

    public function destroy($id)
    {
        $subcourse = \App\Models\SubCourse::findOrFail($id);
        $courseId = $subcourse->course_id;
        $subcourse->delete();

        // Fix: Use 'courseId' instead of 'course_id'
        return redirect()->route('subcourses', ['courseId' => $courseId])->with('success', 'Subcourse deleted successfully!');
    }
}
