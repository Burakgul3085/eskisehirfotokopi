<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Yönetim paneli kullanıcısı. Rol: super (her şey, yönetici ekleme dahil) | staff (sipariş, ürün, içerik).
 * Ardışık hatalı girişte hesap geçici kilitlenir (Admin\AuthController).
 */
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    public const ROLES = ['super' => 'Süper yönetici', 'staff' => 'Personel'];

    public const MAX_FAILED_LOGINS = 5;

    public const LOCK_MINUTES = 15;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'failed_logins', 'locked_until', 'last_login_at', 'last_login_ip'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuper(): bool
    {
        return $this->role === 'super';
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
