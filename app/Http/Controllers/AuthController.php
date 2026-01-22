<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

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

    // Di dalam class AuthController extends Controller

public function login(Request $request)
{
    // 1. Validasi Input (Ubah 'email' jadi 'identifier')
    $request->validate([
        'identifier' => 'required|string', // Bisa berisi nama, email, atau hp
        'password' => 'required|string',
    ]);

    // 2. Cari User Berdasarkan Email ATAU Phone ATAU Name
    $input = $request->identifier;
    
    $user = User::where('email', $input)
        ->orWhere('phone', $input)
        ->orWhere('name', $input)
        ->first();

    // 3. Cek Ketersediaan User & Password
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Login gagal. Nama, Email, No. Telepon, atau Password salah.'
        ], 401);
    }

    // 4. Cek Verifikasi Email (Logic bawaan Anda)
    if (!$user->hasVerifiedEmail()) {
        return response()->json([
            'success' => false,
            'message' => 'Email Anda belum diverifikasi. Silakan cek email Anda.',
            'email_verified' => false
        ], 403);
    }

    // 5. Generate Token Manual
    // Karena Anda pakai JWT (auth()->attempt), kita bisa generate token user ini secara langsung
    $token = auth()->login($user);

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

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $token = Str::random(64);

        // Simpan token ke tabel password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => $token, // Token plain text untuk simplifikasi API
                'created_at' => Carbon::now()
            ]
        );

        // TODO: Di production, kirim email berisi link: http://frontend-url/password-reset?token=$token&email=$request->email
        // Untuk testing sekarang, kita return tokennya di response JSON

        return response()->json([
            'success' => true,
            'message' => 'Link reset password telah digenerate (Cek console/response untuk token)',
            'token' => $token // HANYA UNTUK DEV/TESTING
        ]);
    }

    // 2. Eksekusi Reset Password
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek kecocokan token di database
        $checkToken = DB::table('password_reset_tokens')
            ->where([
                'email' => $request->email,
                'token' => $request->token
            ])
            ->first();

        if (!$checkToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau email salah.'
            ], 400);
        }

        // Update Password User
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Hapus token agar tidak bisa dipakai lagi
        DB::table('password_reset_tokens')->where(['email' => $request->email])->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah! Silakan login.'
        ]);
    }
    public function sendResetOtp(Request $request)
{
    $request->validate(['email' => 'required|email|exists:users,email']);

    // 1. Generate 6 Digit Code
    $otp = rand(100000, 999999);

    // 2. Simpan ke tabel password_reset_tokens (Update jika ada, Insert jika baru)
    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $request->email],
        [
            'token' => $otp, // Simpan OTP mentah (atau di-hash jika ingin lebih aman)
            'created_at' => Carbon::now()
        ]
    );

    // 3. Kirim Email (Gunakan Mail::raw biar simpel tanpa buat Class Mail baru)
    Mail::raw("Kode reset password Anda adalah: $otp", function ($message) use ($request) {
        $message->to($request->email)
                ->subject('Kode Verifikasi Reset Password');
    });

    return response()->json(['message' => 'Kode verifikasi telah dikirim ke email Anda.']);
}

public function resetWithOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
        'otp'   => 'required|numeric',
        'password' => 'required|string|min:8|confirmed',
    ]);

    // 1. Cek Token di Database
    $record = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

    // 2. Validasi: Ada token? Token cocok? Token belum expired (misal 15 menit)?
    if (!$record || $record->token != $request->otp) {
        return response()->json(['message' => 'Kode verifikasi salah atau tidak valid.'], 400);
    }

    // 3. Update Password User
    User::where('email', $request->email)->update([
        'password' => Hash::make($request->password)
    ]);

    // 4. Hapus Token agar tidak bisa dipakai lagi
    DB::table('password_reset_tokens')->where('email', $request->email)->delete();

    return response()->json(['success' => true, 'message' => 'Password berhasil diubah!']);
}
}