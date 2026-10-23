<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on a frontend build.
        $this->withoutVite();
    }

    /**
     * Also run the test-only fixture migrations (tables for models that exist only in
     * tests, e.g. a branch-scoped model to test BelongsToBranch).
     *
     * Creating tables inside a test is not an option on MySQL: DDL commits the
     * transaction that RefreshDatabase wraps every test in.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => false,
            '--drop-types' => false,
            '--seed' => false,
            '--path' => [
                database_path('migrations'),
                base_path('tests/Fixtures/migrations'),
            ],
            '--realpath' => true,
        ];
    }
}
