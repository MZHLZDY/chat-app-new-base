<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // 1. Ambil Data User Login
    public function show()
    {
        $user = Auth::user();
        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : null,
                'phone' => $user->phone,
                'bio' => $user->bio,
            ]
        ]);
    }

    // 2. Update Profile (Nama, Bio, HP, Foto)
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|max:2048', // Max 2MB
        ]);

        $data = $request->only(['name', 'phone', 'bio']);

        // Handle Upload Foto
        if ($request->hasFile('avatar')) {
            // Hapus foto lama jika ada (opsional)
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['photo'] = $path;
        }

        // Handle Hapus Foto
        if ($request->boolean('avatar_remove')) {
             if ($user->photo) Storage::disk('public')->delete($user->photo);
             $data['photo'] = null;
        }

        $user->update($data);

        return response()->json([
            'success' => true, 
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'photo' => $user->photo ? asset('storage/' . $user->photo) : null
            ]
        ]);
    }

    // 3. Update Email
    public function updateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . Auth::id(),
            'password' => 'required'
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password salah'], 422);
        }

        $user->update(['email' => $request->email]);

        return response()->json(['success' => true, 'message' => 'Email berhasil diubah']);
    }

    // 4. Update Password
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password saat ini salah'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah']);
    }
}