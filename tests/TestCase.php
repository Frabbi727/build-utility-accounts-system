<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $_ENV['APP_BASE_PATH'] = dirname(__DIR__);

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert behaviour, not bundled assets; this keeps them independent of `npm run build`.
        $this->withoutVite();
    }
}
