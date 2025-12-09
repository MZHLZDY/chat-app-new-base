<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Get authenticated user
     */
    public function me()
    {
        return response()->json([
            'user' => auth()->user()
        ]);
    }

    /**
     * Login User
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->post(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        if (!$token = auth()->attempt($validator->validated())) {
            return response()->json([
                'status' => false,
                'message' => 'Email / Password salah!'
            ], 401);
        }

        return response()->json([
            'status' => true,
            'user' => auth()->user(),
            'token' => $token
        ]);
    }

    /**
     * Register User
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = User::create([
            'name'     => $request->nama,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // 4. Return Sukses
        return response()->json([
            'status' => true,
            'message' => 'Registrasi berhasil, silakan login.',
            'user'    => $user
        ], 201);
    }

    /**
     * Logout User
     */
    public function logout()
    {
        auth()->logout();
        return response()->json(['success' => true]);
    }
}