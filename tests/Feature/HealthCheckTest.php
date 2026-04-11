<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_the_health_endpoint_returns_successfully(): void
    {
        $this->get('/up')->assertOk();
    }
}
