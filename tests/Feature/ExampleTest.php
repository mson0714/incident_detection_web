<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_exposes_its_liveness_probe(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_test_suite_cannot_target_the_development_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNull(config('database.connections.sqlite.url'));
    }
}
