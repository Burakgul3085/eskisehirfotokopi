<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// İlk (veya yeni) süper yöneticiyi oluşturur. Parola sorulur, komut satırı geçmişine düşmez.
Artisan::command('yonetici:olustur {email} {name}', function (string $email, string $name) {
    $email = mb_strtolower(trim($email));
    $password = $this->secret('Parola (en az 10 karakter, harf ve rakam)');
    $v = Validator::make(['email' => $email, 'password' => $password], [
        'email' => ['required', 'email', 'unique:admins,email'],
        'password' => ['required', Password::min(10)->letters()->numbers()],
    ]);
    if ($v->fails()) {
        foreach ($v->errors()->all() as $e) {
            $this->error($e);
        }

        return 1;
    }
    Admin::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'super', 'is_active' => true]);
    $this->info("Süper yönetici oluşturuldu: {$email}");

    return 0;
})->purpose('Süper yönetici hesabı oluşturur');
