<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_is_accessible(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_about_page_is_accessible(): void
    {
        $response = $this->get('/about');
        $response->assertStatus(200);
    }

    public function test_services_page_is_accessible(): void
    {
        $response = $this->get('/services');
        $response->assertStatus(200);
    }

    public function test_contact_page_is_accessible(): void
    {
        $response = $this->get('/contact');
        $response->assertStatus(200);
    }
}
