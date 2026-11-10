<?php

namespace App\Domain\Print;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Belgenin sahibi: oturumda tutulan rastgele anahtar. Misafir de belge yükleyebilir; girişte oturum kimliği yenilense
 * de oturum verisi taşındığı için anahtar (ve yüklenen belgeler) korunur.
 */
class PrintOwner
{
    private const KEY = 'print.owner';

    public function __construct(private Session $session) {}

    /** Var olan anahtar (yoksa null; okuma işlemlerinde yeni anahtar üretilmez). */
    public function find(): ?string
    {
        return $this->session->get(self::KEY);
    }

    public function key(): string
    {
        $key = $this->find();
        if (! $key) {
            $key = Str::random(40);
            $this->session->put(self::KEY, $key);
        }

        return $key;
    }
}
