<?php

namespace Tests\Unit;

use App\Models\Publisher;
use App\Support\Money;
use App\Support\Text;
use PHPUnit\Framework\TestCase;

class TextAndMoneyTest extends TestCase
{
    public function test_text_key_and_case(): void
    {
        $this->assertSame('hiz yayinlari', Text::key('HIZ YAYINLARI'));
        $this->assertSame('hiz yayinlari', Text::key('Hız  Yayınları'));
        $this->assertSame('bogazici universitesi', Text::key('BOĞAZİÇİ ÜNİVERSİTESİ'));
        $this->assertSame('istanbul', Text::lower('İSTANBUL') === 'istanbul' ? 'istanbul' : 'x');
        $this->assertSame('ISPARTA', Text::upper('ısparta') === 'ISPARTA' ? 'ISPARTA' : Text::upper('ısparta'));
        $this->assertSame('Almanya\'nın Kısa Tarihi', Text::title("ALMANYA'NIN KISA TARİHİ"));
        $this->assertSame('Soru Bankası ve Deneme', Text::title('SORU BANKASI VE DENEME'));
    }

    public function test_publisher_match_key_strips_suffixes(): void
    {
        $this->assertSame('bogazici universitesi', Publisher::matchKey('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI EKRAN GÖRÜNTÜLERİ'));
        $this->assertSame('bogazici universitesi', Publisher::matchKey('Boğaziçi Üniversitesi Yayınevi'));
        $this->assertSame('hiz', Publisher::matchKey('Hız Yayınları'));
        $this->assertSame('3d', Publisher::matchKey('3D YAYINLARI EKRAN GÖRÜNTÜLERİ'));
        $this->assertSame('yayinlari', Publisher::matchKey('Yayınları')); // tamamı ek ise ad korunur
    }

    public function test_money_parse(): void
    {
        $this->assertSame('149.90', Money::parse('149,90'));
        $this->assertSame('149.90', Money::parse('149.9'));
        $this->assertSame('1250.00', Money::parse('1.250'));
        $this->assertSame('1250.50', Money::parse('1.250,50'));
        $this->assertSame('1250.50', Money::parse('1,250.50'));
        $this->assertSame('99.00', Money::parse('₺ 99'));
        $this->assertSame('99.90', Money::parse(99.9));
        $this->assertSame('120.00', Money::parse(120));
        $this->assertNull(Money::parse('abc'));
        $this->assertNull(Money::parse('-5'));
        $this->assertSame('1.234,50 ₺', Money::format('1234.5'));
    }
}
