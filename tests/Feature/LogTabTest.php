<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LogTabTest extends TestCase
{

    public function test_halaman_log_bisa_diakses(): void
    {
        $this->get('/log')->assertOk()->assertSee('Log aktivitas');
    }

    public function test_nav_memuat_tautan_log(): void
    {
        $this->get('/')->assertOk()->assertSee('/log', false);
    }
}