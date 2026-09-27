<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaFallbackTest extends TestCase
{
    public function test_spa_entry_is_returned_for_root_and_nested_frontend_routes(): void
    {
        $this->get('/')->assertOk()->assertSee('id="app"', false);
        $this->get('/student/learning')->assertOk()->assertSee('id="app"', false);
        $this->get('/a/frontend/path')->assertOk()->assertSee('id="app"', false);
    }

    public function test_spa_fallback_does_not_capture_api_or_sanctum_paths(): void
    {
        $this->getJson('/api/v1/not-a-real-endpoint')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/sanctum/not-a-real-endpoint')->assertNotFound();
        $this->get('/storage/private-file.pdf')->assertForbidden()->assertDontSee('id="app"', false);
        $this->get('/assets/missing-file.png')->assertNotFound();
    }
}
