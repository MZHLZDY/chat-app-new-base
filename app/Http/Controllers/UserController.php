<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return User::where('id', '!=', Auth::id())->get(['id', 'name', 'last_seen']);
    }

    /**
     * 2. STORE: Menyimpan User Baru (Untuk Tombol Tambah Kontak)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email', 
            'phone' => 'required|numeric|unique:users,phone',
        ],[
            'phone.unique' => 'Maaf, nomor telepon ini sudah terdaftar. Gunakan nomor lain.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'required' => 'Kolom :attribute wajib diisi.'
        ]);

        // Simpan ke Database
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make('12345678'), 
        ]);

        $user->assignRole('member');

        return response()->json([
            'message' => 'User berhasil ditambahkan',
            'data'    => $user
        ], 201);
    }
    
    public function get(Request $request) {
        return $request->user();
    }
}