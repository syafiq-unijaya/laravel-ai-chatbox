<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SyafiqUnijaya\AiChatbox\Http\Controllers\ChatboxController;

class ErrorClassificationTest extends TestCase
{
    private ChatboxController $controller;
    private ReflectionMethod $classifyConnect;
    private ReflectionMethod $classifyStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller      = new ChatboxController();
        $this->classifyConnect = new ReflectionMethod(ChatboxController::class, 'classifyConnectException');
        $this->classifyStatus  = new ReflectionMethod(ChatboxController::class, 'classifyHttpStatus');
    }

    private function connectException(string $message): ConnectException
    {
        return new ConnectException($message, new Request('GET', '/'));
    }

    private function classifyConnect(string $message): string
    {
        return $this->classifyConnect->invoke($this->controller, $this->connectException($message));
    }

    private function classifyStatus(int $status): string
    {
        return $this->classifyStatus->invoke($this->controller, $status);
    }

    // ── classifyConnectException ──────────────────────────────────────────────

    public function test_classifies_dns_failure_as_e06(): void
    {
        $this->assertSame('E06', $this->classifyConnect('cURL error 6: Could not resolve host: nonexistent.invalid'));
    }

    public function test_classifies_name_not_known_as_e06(): void
    {
        $this->assertSame('E06', $this->classifyConnect('name or service not known'));
    }

    public function test_classifies_connection_refused_as_e07(): void
    {
        $this->assertSame('E07', $this->classifyConnect('cURL error 7: Failed to connect: Connection refused'));
    }

    public function test_classifies_operation_timed_out_as_e08(): void
    {
        $this->assertSame('E08', $this->classifyConnect('cURL error 28: Operation timed out after 5000ms'));
    }

    public function test_classifies_connection_timeout_as_e08(): void
    {
        $this->assertSame('E08', $this->classifyConnect('Connection timeout'));
    }

    public function test_classifies_ssl_error_as_e09(): void
    {
        $this->assertSame('E09', $this->classifyConnect('cURL error 60: SSL certificate problem'));
    }

    public function test_classifies_certificate_error_as_e09(): void
    {
        $this->assertSame('E09', $this->classifyConnect('certificate verify failed'));
    }

    public function test_classifies_tls_error_as_e09(): void
    {
        $this->assertSame('E09', $this->classifyConnect('TLS handshake failed'));
    }

    public function test_classifies_unknown_connect_error_as_e11(): void
    {
        $this->assertSame('E11', $this->classifyConnect('Some other network error'));
    }

    // ── classifyHttpStatus ────────────────────────────────────────────────────

    public function test_classifies_401_as_e12(): void
    {
        $this->assertSame('E12', $this->classifyStatus(401));
    }

    public function test_classifies_403_as_e13(): void
    {
        $this->assertSame('E13', $this->classifyStatus(403));
    }

    public function test_classifies_404_as_e14(): void
    {
        $this->assertSame('E14', $this->classifyStatus(404));
    }

    public function test_classifies_429_as_e15(): void
    {
        $this->assertSame('E15', $this->classifyStatus(429));
    }

    public function test_classifies_500_as_e16(): void
    {
        $this->assertSame('E16', $this->classifyStatus(500));
    }

    public function test_classifies_502_as_e16(): void
    {
        $this->assertSame('E16', $this->classifyStatus(502));
    }

    public function test_classifies_503_as_e16(): void
    {
        $this->assertSame('E16', $this->classifyStatus(503));
    }

    public function test_classifies_504_as_e16(): void
    {
        $this->assertSame('E16', $this->classifyStatus(504));
    }

    public function test_classifies_unexpected_status_as_e17(): void
    {
        $this->assertSame('E17', $this->classifyStatus(418));
        $this->assertSame('E17', $this->classifyStatus(301));
    }
}
