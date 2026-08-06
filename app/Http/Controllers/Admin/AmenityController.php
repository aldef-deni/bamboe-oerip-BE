<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use Illuminate\Http\Request;

class AmenityController extends Controller
{
    public function index()
    {
        return response()->json(Amenity::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(Amenity::create($data), 201);
    }

    public function update(Request $request, Amenity $amenity)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $amenity->update($data);

        return response()->json($amenity);
    }

    public function destroy(Amenity $amenity)
    {
        $amenity->delete();

        return response()->json(['message' => 'Fasilitas dihapus.']);
    }
}
