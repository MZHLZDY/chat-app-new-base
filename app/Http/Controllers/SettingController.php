<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::first());
    }

    public function update(Request $request)
    {
        if (request()->wantsJson()) {
            // Validasi tetap nullable agar Frontend tidak error
            $request->validate([
                'app' => 'nullable|string',
                'description' => 'nullable|string',
                'email' => 'nullable|email',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'bg_auth' => 'nullable|image|mimes:jpeg,png,jpg|max:8192',
                'banner' => 'nullable|image|mimes:jpeg,png,jpg|max:8192',
            ]);

            $setting = Setting::first();
            $isNew = false;

            // Jika belum ada data, buat instance baru
            if (!$setting) {
                $setting = new Setting();
                $isNew = true;
            }

            // Ambil data input selain file
            $data = $request->except(['logo', 'bg_auth', 'banner']);

            // === PENTING: ISI DATA DEFAULT JIKA BARIS BARU ===
            // Ini mencegah error "Column cannot be null" saat insert pertama kali
            // if ($isNew) {
            //     $data['app'] = $data['app'] ?? 'Nama Aplikasi Default';
            //     $data['description'] = $data['description'] ?? 'Deskripsi Aplikasi Default';
            //     $data['email'] = $data['email'] ?? 'admin@example.com';
                
            //     // Pastikan kolom lain yang required di DB juga terisi (sesuai migration Anda)
            //     // Jika di migration kolom ini string/text dan not-null, wajib ada isinya
            //     if (!isset($data['logo'])) $data['logo'] = 'default-logo.png'; 
            //     if (!isset($data['banner'])) $data['banner'] = 'default-banner.png';
            // }

            // ================= LOGIKA LOGO =================
            if ($request->hasFile('logo')) {
                if ($setting->logo && strpos($setting->logo, '/storage/') !== false) {
                    $old = str_replace('/storage/', '', $setting->logo);
                    Storage::disk('public')->delete($old);
                }
                $data['logo'] = '/storage/' . $request->file('logo')->store('setting', 'public');
            } elseif ($isNew && !isset($data['logo'])) {
                 // Fallback untuk logo jika baris baru dan user tidak upload
                 $data['logo'] = 'media/logos/default-dark.svg'; 
            }

            // ================= LOGIKA BANNER =================
            if ($request->hasFile('banner')) {
                if ($setting->banner && strpos($setting->banner, '/storage/') !== false) {
                    $old = str_replace('/storage/', '', $setting->banner);
                    Storage::disk('public')->delete($old);
                }
                $data['banner'] = '/storage/' . $request->file('banner')->store('setting', 'public');
            } elseif ($isNew && !isset($data['banner'])) {
                $data['banner'] = 'media/misc/default-banner.png';
            }

            // ================= LOGIKA BACKGROUND (bg_auth) =================
            if ($request->hasFile('bg_auth')) {
                if ($setting->bg_auth && strpos($setting->bg_auth, '/storage/') !== false) {
                    $old = str_replace('/storage/', '', $setting->bg_auth);
                    Storage::disk('public')->delete($old);
                }
                $data['bg_auth'] = '/storage/' . $request->file('bg_auth')->store('setting', 'public');
            }

            // Simpan ke Database
            if ($setting->exists) {
                $setting->update($data);
            } else {
                $setting->fill($data);
                $setting->save();
            }

            return response()->json([
                'message' => 'Berhasil memperbarui data Konfigurasi Website',
                'data' => $setting
            ]);
        } else {
            abort(404);
        }
    }
}