<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    /**
     * Daftar promo aktif untuk ditampilkan ke customer (home + halaman promo).
     */
    public function index()
    {
        $promos = Promo::active()
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'description' => $p->description,
                'image_url' => $p->image_url,
                'type' => $p->type,
                'value' => $p->value,
                'min_spend' => $p->min_spend,
                'max_discount' => $p->max_discount,
                'expires_at' => $p->expires_at,
            ]);

        return response()->json($promos);
    }

    /**
     * Promo popup aktif (yang ditandai tampil sebagai popup).
     */
    public function popup()
    {
        $promo = Promo::active()->where('is_popup', true)->latest()->first();

        if (! $promo) {
            return response()->json(null);
        }

        return response()->json([
            'id' => $promo->id,
            'code' => $promo->code,
            'description' => $promo->description,
            'image_url' => $promo->image_url,
            'type' => $promo->type,
            'value' => $promo->value,
            'min_spend' => $promo->min_spend,
            'max_discount' => $promo->max_discount,
            'expires_at' => $promo->expires_at,
        ]);
    }

    /**
     * Validasi kode promo terhadap subtotal, kembalikan potongan bila valid.
     */
    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $promo = Promo::whereRaw('LOWER(code) = ?', [strtolower($data['code'])])->first();

        if (! $promo) {
            return response()->json(['valid' => false, 'message' => 'Kode promo tidak ditemukan.'], 422);
        }

        $error = $promo->validationError((float) $data['subtotal']);
        if ($error) {
            return response()->json(['valid' => false, 'message' => $error], 422);
        }

        $discount = $promo->discountFor((float) $data['subtotal']);

        return response()->json([
            'valid' => true,
            'code' => $promo->code,
            'description' => $promo->description,
            'type' => $promo->type,
            'value' => $promo->value,
            'discount' => $discount,
            'message' => 'Kode promo berhasil diterapkan.',
        ]);
    }
}
