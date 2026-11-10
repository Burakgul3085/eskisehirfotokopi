<?php

namespace Tests\Unit;

use App\Rules\TurkishPhone;
use PHPUnit\Framework\TestCase;

class TurkishPhoneTest extends TestCase
{
    public function test_normalize(): void
    {
        $this->assertSame('05321234567', TurkishPhone::normalize('0532 123 45 67'));
        $this->assertSame('05321234567', TurkishPhone::normalize('+90 (532) 123-45-67'));
        $this->assertSame('05321234567', TurkishPhone::normalize('5321234567'));
        $this->assertSame('02221234567', TurkishPhone::normalize('0222 123 45 67'));
        $this->assertNull(TurkishPhone::normalize('123'));
        $this->assertNull(TurkishPhone::normalize('0132 123 45 67'));
        $this->assertSame('0532 123 45 67', TurkishPhone::format('05321234567'));
    }
}
