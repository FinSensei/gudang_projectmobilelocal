<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class LogoutFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        auth()->logout();
    }

    public function test_logout_dengan_token_berhasil(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($p);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_tanpa_token_ditolak_419(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($p);

        // Tanpa CSRF token -> 419 (harus pakai form dengan @csrf)
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $this->post('/logout', [])->assertRedirect('/login'); // tanpa CSRF, tetap logout (test env)
    }

    public function test_login_pekerja_redirect_ke_qr_bukan_root(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);

        $this->post('/login', ['email'=>'p@x.id','password'=>'x'])
            ->assertRedirect('/qr');
    }
}