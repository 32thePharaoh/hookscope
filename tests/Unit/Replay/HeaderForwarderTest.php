<?php

namespace Tests\Unit\Replay;

use App\Replay\HeaderForwarder;
use PHPUnit\Framework\TestCase;

class HeaderForwarderTest extends TestCase
{
    public function test_content_type_comes_from_the_headers_column_not_a_lossy_scalar(): void
    {
        $raw = "application/caf\xe9";

        $result = HeaderForwarder::forReplay(
            [
                'content-type' => [[
                    'encoding' => 'base64',
                    'value' => base64_encode($raw),
                ]],
            ],
            forwardSensitive: false,
        );

        $this->assertSame($raw, $result->headers['content-type'][0]);
        $this->assertContains('content-type', $result->forwarded);
    }

    public function test_it_strips_auth_bearing_headers_by_default(): void
    {
        $result = HeaderForwarder::forReplay(
            [
                'Authorization' => ['Bearer secret'],
                'Cookie' => ['session=abc'],
                'Stripe-Signature' => ['t=1'],
                'X-Hub-Signature-256' => ['sha256=dead'],
                'X-Request-Id' => ['abc-123'],
            ],
            forwardSensitive: false,
        );

        $this->assertArrayNotHasKey('Authorization', $result->headers);
        $this->assertArrayNotHasKey('Cookie', $result->headers);
        $this->assertArrayNotHasKey('Stripe-Signature', $result->headers);
        $this->assertArrayNotHasKey('X-Hub-Signature-256', $result->headers);
        $this->assertSame(['abc-123'], $result->headers['X-Request-Id']);
        $this->assertSame(['X-Request-Id'], $result->forwarded);
    }

    public function test_opt_in_forwards_auth_headers_but_never_host_or_hop_by_hop(): void
    {
        $result = HeaderForwarder::forReplay(
            [
                'Host' => ['evil.example'],
                'Connection' => ['keep-alive'],
                'Content-Length' => ['99999'],
                'Authorization' => ['Bearer secret'],
                'Accept' => ['application/json'],
            ],
            forwardSensitive: true,
        );

        $this->assertArrayNotHasKey('Host', $result->headers);
        $this->assertArrayNotHasKey('Connection', $result->headers);
        $this->assertArrayNotHasKey('Content-Length', $result->headers);
        $this->assertSame(['Bearer secret'], $result->headers['Authorization']);
        $this->assertContains('Authorization', $result->forwarded);
        $this->assertContains('Accept', $result->forwarded);
    }

    public function test_string_header_values_from_the_factory_are_accepted(): void
    {
        $result = HeaderForwarder::forReplay(
            ['Content-Type' => 'application/json'],
            forwardSensitive: false,
        );

        $this->assertSame(['application/json'], $result->headers['Content-Type']);
    }
}
