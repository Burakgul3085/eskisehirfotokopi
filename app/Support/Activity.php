<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Yönetici işlemlerini günlüğe yazar. Parola ve gizli alanlar hiçbir zaman yazılmaz. */
class Activity
{
    private const HIDDEN = ['password', 'remember_token', 'mail_password'];

    public static function log(string $action, ?Model $subject = null, array $changes = []): void
    {
        foreach (self::HIDDEN as $key) {
            if (array_key_exists($key, $changes)) {
                $changes[$key] = '••••';
            }
        }

        ActivityLog::create([
            'admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
