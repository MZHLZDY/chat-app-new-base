<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->truncate();

        Setting::create([
            'app' => 'Chat App',
            'description' =>  'Aplikasi Chat sederhana',
            'logo' =>  '/media/logo.png',
            'bg_auth' =>  '/media/misc/bg.jpg',
            'banner' =>  '/media/misc/banner.jpg',
            'email' =>  '',
        ]);
    }
}
