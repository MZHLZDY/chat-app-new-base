<?php

namespace App\Models;

use App\Traits\Uuid; 
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Notifications\Notifiable;
use App\Models\ChatMessage;
use App\Models\Group;
use App\Models\GroupMessage;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class User extends Authenticatable implements MustVerifyEmail, JWTSubject
{
    use Uuid, HasRoles, HasApiTokens, HasPushSubscriptions, HasFactory, Notifiable;

    // --- TAMBAHAN PENTING: PAKSA SPATIE GUNAKAN GUARD API ---
    protected $guard_name = 'api';

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'photo', 'background_image_path', 'bio'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $appends = [
        'profile_photo_url', 'avatar_url', 'role', 'permission'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted()
    {
        static::deleted(function ($user) {
            if ($user->photo) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->photo));
            }
        });

        static::created(function ($user) {
            
            if (Role::where('name', 'member')->where('guard_name', 'api')->doesntExist()) {
                $roleMember = Role::create(['name' => 'member', 'guard_name' => 'api', 'full_name' => 'Member Chat']);
                
                $perm = Permission::firstOrCreate(['name' => 'dashboard', 'guard_name' => 'api']);
                
                $roleMember->givePermissionTo($perm);
            }
            $user->assignRole('member');
        });
    }

    // --- JWT ---
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    // --- ACCESSOR ---
    public function getRoleAttribute() { return $this->roles->first(); }
    public function getPermissionAttribute() { return $this->getAllPermissions()->pluck('name'); }
    
    public function getProfilePhotoUrlAttribute()
    {
        return $this->photo 
            ? asset('storage/' . $this->photo) 
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    public function getAvatarUrlAttribute()
    {
        return $this->profile_photo_url;
    }

/**
 * Accessor untuk avatar (fallback ke photo field)
 */
public function getAvatarAttribute()
{
    return $this->attributes['photo'] ?? null;
}

    // --- CHAT RELATIONS ---
    public function sentMessages() 
    { 
        return $this->hasMany(ChatMessage::class, 'sender_id'); 
    }
    
    public function receivedMessages() 
    { 
        return $this->hasMany(ChatMessage::class, 'receiver_id'); 
    }

    // --- GROUP RELATIONS ---
    /**
     * Groups yang user ikuti sebagai member
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withTimestamps();
    }

    /**
     * Groups yang user miliki/buat (sebagai owner)
     */
    public function ownedGroups()
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /**
     * Group messages yang dikirim user
     */
    public function groupMessages()
    {
        return $this->hasMany(GroupMessage::class, 'sender_id');
    }
}