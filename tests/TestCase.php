<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert behaviour, not bundled assets; this keeps them independent of `npm run build`.
        $this->withoutVite();
    }
}
