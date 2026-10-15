<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isOwner()) {
            return response()->json(['message' => 'Akses ditolak. Khusus owner properti.'], 403);
        }

        // Akun dinonaktifkan superadmin -> cabut sesi yang sedang dipakai
        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Akun owner Anda dinonaktifkan. Hubungi admin Bamboe Oerip.'], 401);
        }

        return $next($request);
    }
}
