<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_manage_admins(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get('/yonetim/yoneticiler')->assertForbidden();
        $this->get('/yonetim/islem-gunlugu')->assertForbidden();
    }

    public function test_super_admin_creates_updates_and_deletes(): void
    {
        $super = Admin::factory()->super()->create();
        $this->actingAs($super, 'admin');

        $this->get('/yonetim/yoneticiler')->assertOk();
        $this->post('/yonetim/yoneticiler', ['name' => 'Personel', 'email' => 'P@ornek.com', 'role' => 'staff', 'is_active' => '1',
            'password' => 'Personel1234', 'password_confirmation' => 'Personel1234'])->assertRedirect('/yonetim/yoneticiler');
        $staff = Admin::where('email', 'p@ornek.com')->firstOrFail();

        $this->put("/yonetim/yoneticiler/{$staff->id}", ['name' => 'Personel 2', 'email' => 'p@ornek.com', 'role' => 'staff'])->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active); // onay kutusu gönderilmedi → pasif
        $this->assertSame('Personel 2', $staff->fresh()->name);

        $this->delete("/yonetim/yoneticiler/{$staff->id}")->assertRedirect();
        $this->assertModelMissing($staff);
        $this->get('/yonetim/islem-gunlugu')->assertOk()->assertSee('Yönetici sildi');
    }

    public function test_last_super_admin_is_protected(): void
    {
        $super = Admin::factory()->super()->create();
        $this->actingAs($super, 'admin');

        $this->put("/yonetim/yoneticiler/{$super->id}", ['name' => $super->name, 'email' => $super->email, 'role' => 'staff', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($super->fresh()->isSuper());

        $this->delete("/yonetim/yoneticiler/{$super->id}")->assertSessionHasErrors('admin');
        $this->assertModelExists($super);
    }
}
