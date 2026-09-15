<?php

namespace Tests\Feature;

use Tests\TestCase;

class CspReportTest extends TestCase
{
    public function test_csp_report_endpoint_returns_ok(): void
    {
        $response = $this->postJson('/csp-report', [
            'csp-report' => [
                'effective-directive' => 'script-src',
                'document-uri' => 'https://shop.laravel.tunispromo.tn/',
            ],
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_csp_report_accepts_malformed_payload(): void
    {
        $response = $this->postJson('/csp-report', ['unexpected' => 'payload']);

        $response->assertOk();
    }
}
