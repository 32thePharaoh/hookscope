<?php

namespace Tests\Unit\Replay;

use App\Replay\ForbiddenIp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ForbiddenIpTest extends TestCase
{
    #[DataProvider('forbiddenAddresses')]
    public function test_it_rejects_ssrf_bypass_addresses(string $ip): void
    {
        $this->assertTrue(ForbiddenIp::isForbidden($ip), $ip.' should be forbidden');
    }

    #[DataProvider('allowedAddresses')]
    public function test_it_allows_public_unicast_addresses(string $ip): void
    {
        $this->assertFalse(ForbiddenIp::isForbidden($ip), $ip.' should be allowed');
    }

    public function test_unparseable_addresses_are_forbidden(): void
    {
        $this->assertTrue(ForbiddenIp::isForbidden('not-an-ip'));
        $this->assertTrue(ForbiddenIp::isForbidden(''));
    }

    public function test_decimal_and_octal_hosts_decode_to_loopback(): void
    {
        $this->assertSame('127.0.0.1', ForbiddenIp::ipv4FromEncodedHost('2130706433'));
        $this->assertSame('127.0.0.1', ForbiddenIp::ipv4FromEncodedHost('0177.0.0.1'));
        $this->assertSame('127.0.0.1', ForbiddenIp::ipv4FromEncodedHost('127.1'));
        $this->assertSame('127.0.0.1', ForbiddenIp::ipv4FromEncodedHost('0x7f.0.0.1'));
    }

    public function test_hostnames_are_not_treated_as_encoded_ipv4(): void
    {
        $this->assertNull(ForbiddenIp::ipv4FromEncodedHost('example.test'));
        $this->assertNull(ForbiddenIp::ipv4FromEncodedHost('169.254.169.254.nip.io'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'loopback-net' => ['127.255.255.255'],
            'unspecified-v4' => ['0.0.0.0'],
            'this-network' => ['0.1.2.3'],
            'rfc1918-10' => ['10.0.0.1'],
            'rfc1918-172' => ['172.16.0.1'],
            'rfc1918-172-end' => ['172.31.255.255'],
            'rfc1918-192' => ['192.168.1.1'],
            'link-local' => ['169.254.1.1'],
            'metadata' => ['169.254.169.254'],
            'v6-loopback' => ['::1'],
            'v6-unspecified' => ['::'],
            'v6-ula' => ['fc00::1'],
            'v6-ula-fd' => ['fd12:3456:789a::1'],
            'v6-link-local' => ['fe80::1'],
            'v4-mapped-loopback' => ['::ffff:127.0.0.1'],
            'v4-mapped-metadata' => ['::ffff:169.254.169.254'],
            'v4-mapped-10' => ['::ffff:10.0.0.1'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedAddresses(): array
    {
        return [
            'example-docs-v4' => ['93.184.216.34'],
            'one-one-one-one' => ['1.1.1.1'],
            'public-v6' => ['2001:4860:4860::8888'],
        ];
    }
}
