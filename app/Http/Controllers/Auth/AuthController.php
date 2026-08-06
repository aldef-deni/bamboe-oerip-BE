<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => 'user',
        ]);

        NotificationService::admin('customer', 'Pelanggan baru', "{$user->name} ({$user->email}) baru mendaftar.", '/admin/users');

        $token = $user->createToken('villabox')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $token = $user->createToken('villabox')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Login / daftar via Google (SSO) — HANYA untuk pelanggan (web customer).
     * Frontend mengirim ID token (credential) dari Google Identity Services,
     * lalu diverifikasi ke Google sebelum menerbitkan token Sanctum.
     */
    public function googleLogin(Request $request)
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $clientId = config('services.google.client_id');
        if (! $clientId) {
            return response()->json(['message' => 'Login Google belum dikonfigurasi di server.'], 500);
        }

        // Verifikasi ID token ke Google
        $resp = Http::asForm()->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $data['credential'],
        ]);

        if (! $resp->ok()) {
            throw ValidationException::withMessages(['credential' => ['Token Google tidak valid atau kedaluwarsa.']]);
        }

        $payload = $resp->json();

        // Pastikan token memang untuk aplikasi kita
        if (($payload['aud'] ?? null) !== $clientId) {
            throw ValidationException::withMessages(['credential' => ['Token Google tidak dikenali.']]);
        }

        $emailVerified = ($payload['email_verified'] ?? null);
        if ($emailVerified !== true && $emailVerified !== 'true') {
            throw ValidationException::withMessages(['credential' => ['Email Google belum terverifikasi.']]);
        }

        $googleId = $payload['sub'] ?? null;
        $email = strtolower(trim($payload['email'] ?? ''));
        $name = $payload['name'] ?? ($payload['given_name'] ?? 'Pengguna Google');
        $picture = $payload['picture'] ?? null;

        if (! $googleId || ! $email) {
            throw ValidationException::withMessages(['credential' => ['Data akun Google tidak lengkap.']]);
        }

        $user = User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        // SSO Google hanya untuk pelanggan — akun pengelola tetap lewat login pengelola
        if ($user && $user->role === 'admin') {
            return response()->json([
                'message' => 'Akun pengelola tidak dapat masuk melalui Google. Gunakan halaman login pengelola.',
            ], 403);
        }

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'google_avatar' => $picture,
                'role' => 'user',
                'email_verified_at' => now(),
            ]);

            NotificationService::admin('customer', 'Pelanggan baru (Google)', "{$user->name} ({$user->email}) mendaftar via Google.", '/admin/users');
        } else {
            // Tautkan akun email yang sudah ada dengan Google
            if (! $user->google_id) {
                $user->google_id = $googleId;
            }
            if ($picture && ! $user->avatar) {
                $user->google_avatar = $picture;
            }
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();
        }

        $token = $user->createToken('villabox')->plainTextToken;

        return response()->json([
            'user' => $user->fresh(),
            'token' => $token,
        ]);
    }

    public function adminLogin(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'username' => ['Akun ini tidak memiliki akses pengelola.'],
            ]);
        }

        $token = $user->createToken('villabox-admin')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', Password::min(6)],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        // Foto profil
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = null;
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }
        if (array_key_exists('username', $data)) {
            $user->username = $data['username'] ?: null;
        }
        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }

        $user->save();

        return response()->json($user->fresh());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }
}
