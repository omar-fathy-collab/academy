<?php

// app/Http/Controllers/RoomController.php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Redirect to schedules page with rooms tab
        return redirect()->route('schedules.index', ['tab' => 'rooms']);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('rooms.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'room_name' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'location' => 'required|string|max:255',
            'facilities' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        Room::create([
            'room_name' => $request->room_name,
            'capacity' => $request->capacity,
            'location' => $request->location,
            'facilities' => $request->facilities,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('schedules.index', ['tab' => 'rooms'])->with('success', 'Room created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room)
    {
        return redirect()->route('schedules.index', ['tab' => 'rooms']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Room $room)
    {
        return view('rooms.edit', [
            'room' => $room,
            'activeSchedulesCount' => Schedule::where('room_id', $room->room_id)->where('is_active', 1)->count(),
            'totalSchedulesCount' => Schedule::where('room_id', $room->room_id)->count(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Room $room)
    {
        $request->validate([
            'room_name' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'location' => 'required|string|max:255',
            'facilities' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $room->update([
            'room_name' => $request->room_name,
            'capacity' => $request->capacity,
            'location' => $request->location,
            'facilities' => $request->facilities,
            'is_active' => $request->is_active,
        ]);

        return redirect()->route('schedules.index', ['tab' => 'rooms'])->with('success', 'Room updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room)
    {
        // Check if room has active schedules
        $activeSchedules = Schedule::where('room_id', $room->room_id)
            ->where('is_active', 1)
            ->count();

        if ($activeSchedules > 0) {
            return redirect()->route('schedules.index', ['tab' => 'rooms'])
                ->with('error', 'Cannot delete room with active schedules. Deactivate schedules first.');
        }

        $room->delete();

        return redirect()->route('schedules.index', ['tab' => 'rooms'])->with('success', 'Room deleted successfully');
    }

    /**
     * Toggle room status
     */
    public function toggleStatus(Room $room)
    {
        $room->update([
            'is_active' => ! $room->is_active,
        ]);

        $status = $room->is_active ? 'activated' : 'deactivated';

        return redirect()->route('schedules.index', ['tab' => 'rooms'])->with('success', "Room {$status} successfully");
    }
}
