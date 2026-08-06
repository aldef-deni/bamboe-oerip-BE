<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $query = Promo::query();

        if ($search = $request->query('search')) {
            $query->where('code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 20)));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_popup'] = $request->boolean('is_popup');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('promos', 'public');
        }

        $promo = Promo::create($data);

        return response()->json($promo, 201);
    }

    public function update(Request $request, Promo $promo)
    {
        $data = $this->validateData($request, $promo);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_popup'] = $request->boolean('is_popup');

        if ($request->hasFile('image')) {
            if ($promo->image) {
                Storage::disk('public')->delete($promo->image);
            }
            $data['image'] = $request->file('image')->store('promos', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($promo->image) {
                Storage::disk('public')->delete($promo->image);
            }
            $data['image'] = null;
        }

        $promo->update($data);

        return response()->json($promo);
    }

    public function destroy(Promo $promo)
    {
        if ($promo->image) {
            Storage::disk('public')->delete($promo->image);
        }
        $promo->delete();

        return response()->json(['message' => 'Promo dihapus.']);
    }

    private function validateData(Request $request, ?Promo $promo = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('promos', 'code')->ignore($promo?->id)],
            'description' => ['required', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:4096'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'is_popup' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }
}
