<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GetApiKeyTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_API_KEY'] = '';
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = '';
        $_GET = [];
        $_POST = [];
    }

    public function testReturnsXApiKeyHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = ' my-key ';
        $this->assertSame('my-key', getApiKey());
    }

    public function testReturnsQueryParamWhenNoHeader(): void
    {
        $_GET['api_key'] = ' query-key ';
        $this->assertSame('query-key', getApiKey());
    }

    public function testHeaderTakesPrecedenceOverQuery(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = ' header-key ';
        $_GET['api_key'] = ' query-key ';
        $this->assertSame('header-key', getApiKey());
    }

    public function testReturnsNullWhenMissing(): void
    {
        $this->assertNull(getApiKey());
    }

    public function testReturnsPostFormParamWhenPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['api_key'] = ' post-form-key ';
        $this->assertSame('post-form-key', getApiKey());
    }
}
