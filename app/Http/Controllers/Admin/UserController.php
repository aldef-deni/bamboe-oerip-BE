<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount('bookings');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 15)));
    }

    /**
     * Hapus permanen beberapa pengguna sekaligus (beserta pesanan & poin via cascade).
     * Tidak boleh menghapus akun sendiri.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_filter($data['ids'], fn ($id) => (int) $id !== (int) $request->user()->id));

        if (empty($ids)) {
            return response()->json(['message' => 'Tidak ada data yang dihapus (akun sendiri tidak bisa dihapus).', 'deleted' => 0]);
        }

        $count = User::whereIn('id', $ids)->delete();

        return response()->json(['message' => "{$count} pengguna dihapus permanen.", 'deleted' => $count]);
    }
}
