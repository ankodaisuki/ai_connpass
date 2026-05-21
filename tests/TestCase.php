<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ビルド済みアセット（Vite マニフェスト）に依存せずビューを描画できるようにする
        $this->withoutVite();
    }
}
