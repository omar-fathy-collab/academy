<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapitalController extends Controller
{
    public function index()
    {
        // التحقق من صلاحيات السوبر أدمن فقط
        if (! auth()->user() || ! auth()->user()->isAdminFull()) {
            return redirect('unauthorized');
        }

        // جلب جميع إضافات رأس المال
        $capital_additions = DB::table('capital_additions')
            ->join('users', 'capital_additions.added_by', '=', 'users.id')
            ->select('capital_additions.*', 'users.username as added_by_name')
            ->orderBy('addition_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // حساب المجموع الكلي
        $total_capital = DB::table('capital_additions')->sum('amount');

        return view('capital.index', [
            'capital_additions' => $capital_additions,
            'total_capital' => $total_capital,
        ]);
    }

    public function create()
    {
        // التحقق من صلاحيات السوبر أدمن فقط
        if (! auth()->user() || ! auth()->user()->isAdminFull()) {
            return redirect('unauthorized');
        }

        return view('capital.create');
    }

    public function store(Request $request)
    {
        // التحقق من صلاحيات السوبر أدمن فقط
        if (! auth()->user() || ! auth()->user()->isAdminFull()) {
            return redirect('unauthorized');
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'addition_date' => 'required|date',
        ]);

        \App\Models\CapitalAddition::create([
            'amount' => $request->amount,
            'description' => $request->description,
            'added_by' => auth()->id(),
            'addition_date' => $request->addition_date,
        ]);

        return redirect()->route('capital.index')->with('success', 'تم إضافة رأس المال بنجاح.');
    }

    public function edit($id)
    {
        // التحقق من صلاحيات السوبر أدمن فقط
        if (! auth()->user() || ! auth()->user()->isAdminFull()) {
            return redirect('unauthorized');
        }

        $capital = DB::table('capital_additions')->find($id);

        if (! $capital) {
            return redirect()->route('capital.index')->with('error', 'سجل رأس المال غير موجود.');
        }

        return view('capital.edit', [
            'capital' => $capital,
        ]);
    }

    public function update(Request $request, $id)
    {
        // التحقق من صلاحيات السوبر أدمن فقط
        if (! auth()->user() || ! auth()->user()->isAdminFull()) {
            return redirect('unauthorized');
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'addition_date' => 'required|date',
        ]);

        DB::table('capital_additions')
            ->where('id', $id)
            ->update([
                'amount' => $request->amount,
                'description' => $request->description,
                'addition_date' => $request->addition_date,
                'updated_at' => now(),
            ]);

        return redirect()->route('capital.index')->with('success', 'تم تعديل رأس المال بنجاح.');
    }

    public function destroy($id)
    {
        // التحقق من صلاحيات السوبر أدمن فقط
        if (! auth()->user() || ! auth()->user()->isAdminFull()) {
            return redirect('unauthorized');
        }

        $capital = DB::table('capital_additions')->find($id);

        if (! $capital) {
            return redirect()->route('capital.index')->with('error', 'سجل رأس المال غير موجود.');
        }

        DB::table('capital_additions')->where('id', $id)->delete();

        return redirect()->route('capital.index')->with('success', 'تم حذف رأس المال بنجاح.');
    }

    public function show($id)
    {
        // التحقق من صلاحيات السوبر أدمن فقط
        if (! auth()->user() || ! auth()->user()->isAdminFull()) {
            return redirect('unauthorized');
        }

        $capital = DB::table('capital_additions')
            ->join('users', 'capital_additions.added_by', '=', 'users.id')
            ->select('capital_additions.*', 'users.username as added_by_name')
            ->where('capital_additions.id', $id)
            ->first();

        if (! $capital) {
            return redirect()->route('capital.index')->with('error', 'سجل رأس المال غير موجود.');
        }

        return view('capital.show', [
            'capital' => $capital,
        ]);
    }
}
