<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\User;
use App\Models\Kategori;

/**
 * Simulasi celah keamanan & akses.
 */
class SimulasiAksesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        auth()->logout();
    }

    private function admin(): User
    {
        return User::create(['name'=>'A','email'=>'a@x.id','password'=>bcrypt('x'),'role'=>'admin']);
    }

    private function pekerja(): User
    {
        return User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
    }

    // ---------- 1. Pekerja tidak bisa akses endpoint tulis ----------
    public function test_pekerja_tidak_bisa_post_acara(): void
    {
        $this->actingAs($this->pekerja());
        $this->post('/acara', ['nama_acara'=>'X','items'=>[]])->assertForbidden();
    }

    public function test_pekerja_tidak_bisa_restock(): void
    {
        $this->actingAs($this->pekerja());
        $this->post('/restock', ['jumlah'=>10])->assertForbidden();
    }

    public function test_pekerja_tidak_bisa_edit_barang(): void
    {
        $b = Barang::create(['nama_barang'=>'S','satuan'=>'pcs','kode_qr'=>'K','stok_total'=>1,'stok_baik'=>1,'stok_rusak'=>0]);
        $this->actingAs($this->pekerja());
        $this->get("/barang/{$b->id}/edit")->assertForbidden();
    }

    public function test_pekerja_tidak_bisa_buat_user(): void
    {
        $this->actingAs($this->pekerja());
        $this->post('/users', ['name'=>'X','email'=>'x@x.id','password'=>'123456','role'=>'admin'])
            ->assertForbidden();
    }

    // ---------- 2. Tamu tidak bisa akses apa pun kecuali login ----------
    public function test_tamu_tidak_bisa_akses_scan_page(): void
    {
        $this->get('/scan')->assertRedirect('/login');
    }

    public function test_tamu_tidak_bisa_akses_log(): void
    {
        $this->get('/log')->assertRedirect('/login');
    }

    // ---------- 3. Admin tidak bisa hapus user lain yang sedang login? (harus bisa) ----------
    public function test_admin_bisa_hapus_user_lain(): void
    {
        $this->actingAs($this->admin());
        $p = $this->pekerja();

        $this->delete("/users/{$p->id}")->assertRedirect();
        $this->assertSoftDeleted('users', ['id'=>$p->id]);
    }

    // ---------- 4. Email unik ----------
    public function test_email_user_tidak_boleh_duplikat(): void
    {
        $this->actingAs($this->admin());
        $this->post('/users', ['name'=>'A','email'=>'a@x.id','password'=>'123456','role'=>'admin'])
            ->assertSessionHasErrors('email');
    }

    // ---------- 5. Kategori duplikat ----------
    public function test_kategori_duplikat_boleh_atau_tidak(): void
    {
        $this->actingAs($this->admin());
        Kategori::create(['nama_kategori'=>'Makan']);
        $this->post('/kategori', ['nama_kategori'=>'Makan'])->assertRedirect();
        // tidak crash; duplikat dibiarkan (tidak ada unique constraint)
        $this->assertSame(2, Kategori::count());
    }

    // ---------- 6. QR page admin saja ----------
    public function test_qr_hanya_admin(): void
    {
        $this->actingAs($this->pekerja());
        $this->get('/qr')->assertForbidden();
    }

    public function test_admin_bisa_qr(): void
    {
        $this->actingAs($this->admin());
        $this->get('/qr')->assertOk();
    }
}