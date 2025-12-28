<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'phone',
        'address',
        'role',
        'status',
        'email_verified_at'
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_STAFF = 'stuff';
    const ROLE_MEMBER = 'member';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relationship with Challan
    public function challans()
    {
        return $this->hasMany(Challan::class, 'user_id');
    }

    // Relationship with Order
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    // Define relationship with Activity
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
