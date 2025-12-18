<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|regex:/^08[0-9]\d{8,11}$/|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->nama,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Trigger email verification
        event(new Registered($user));

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil! Silakan cek email Anda untuk verifikasi.',
            'data' => [
                'user' => $user,
                'email_sent' => true
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!$token = auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        $user = auth()->user();

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            auth()->logout();
            return response()->json([
                'success' => false,
                'message' => 'Email Anda belum diverifikasi. Silakan cek email Anda.',
                'email_verified' => false
            ], 403);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return response()->json([
            'success' => true,
            'data' => auth()->user()
        ]);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout'
        ]);
    }

    protected function respondWithToken($token)
    {
        $user = auth()->user();
        $user->load('roles', 'permissions'); // Load relasi jika diperlukan

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => $user,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    // Resend verification email
    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email sudah terverifikasi'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Email verifikasi telah dikirim ulang'
        ]);
    }

    // Verify email
    public function verifyEmail(Request $request)
    {
        $user = User::findOrFail($request->route('id'));
        $userId = $request->route('id');
        $baseUrl = config('app.url');

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Link verifikasi tidak valid'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($baseUrl . "/email/verify/{$userId}/already-verified?message=Email sudah terverifikasi.");
        }

        $user->markEmailAsVerified();
        event(new \Illuminate\Auth\Events\Verified($user));

        return redirect($baseUrl . "/email/verify/{$userId}/already-verified?message=Email sudah terverifikasi.");
    }
}