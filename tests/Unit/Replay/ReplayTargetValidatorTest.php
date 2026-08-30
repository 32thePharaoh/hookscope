<?php

namespace Tests\Unit\Replay;

use App\Replay\ReplayTargetRejected;
use App\Replay\ReplayTargetValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingDnsResolver;

class ReplayTargetValidatorTest extends TestCase
{
    public function test_it_rejects_non_http_schemes(): void
    {
        $validator = $this->validator(['evil.test' => ['1.1.1.1']]);

        $this->expectException(ReplayTargetRejected::class);
        $validator->validate('file:///etc/passwd');
    }

    public function test_it_rejects_userinfo_in_the_url(): void
    {
        $validator = $this->validator(['example.test' => ['1.1.1.1']]);

        $this->expectException(ReplayTargetRejected::class);
        $validator->validate('https://user:pass@example.test/hook');
    }

    public function test_it_pins_every_resolved_address_on_the_url_port(): void
    {
        $validator = $this->validator([
            'example.test' => ['1.1.1.1', '1.0.0.1'],
        ]);

        $target = $validator->validate('https://example.test:8443/webhook');

        $this->assertSame('example.test', $target->host);
        $this->assertSame(8443, $target->port);
        $this->assertSame(
            ['example.test:8443:1.1.1.1', 'example.test:8443:1.0.0.1'],
            $target->curlResolve(),
        );
    }

    public function test_https_defaults_to_port_443_not_a_hardcoded_pin_port(): void
    {
        $validator = $this->validator(['example.test' => ['1.1.1.1']]);

        $target = $validator->validate('https://example.test/webhook');

        $this->assertSame(['example.test:443:1.1.1.1'], $target->curlResolve());
    }

    public function test_http_defaults_to_port_80(): void
    {
        $validator = $this->validator(['example.test' => ['1.1.1.1']]);

        $target = $validator->validate('http://example.test/webhook');

        $this->assertSame(['example.test:80:1.1.1.1'], $target->curlResolve());
    }

    public function test_it_rejects_when_any_resolved_address_is_private(): void
    {
        $validator = $this->validator([
            'rebind.test' => ['1.1.1.1', '169.254.169.254'],
        ]);

        $this->expectException(ReplayTargetRejected::class);
        $validator->validate('https://rebind.test/');
    }

    public function test_it_rejects_loopback_ipv6_literals(): void
    {
        $validator = $this->validator([]);

        $this->expectException(ReplayTargetRejected::class);
        $validator->validate('http://[::1]/');
    }

    public function test_it_rejects_decimal_encoded_loopback_without_dns(): void
    {
        $resolver = new RecordingDnsResolver([]);
        $validator = new ReplayTargetValidator($resolver, false);

        try {
            $validator->validate('http://2130706433/');
            $this->fail('encoded loopback must be rejected');
        } catch (ReplayTargetRejected) {
            $this->assertSame([], $resolver->queries);
        }
    }

    public function test_it_rejects_octal_encoded_loopback(): void
    {
        $validator = $this->validator([]);

        $this->expectException(ReplayTargetRejected::class);
        $validator->validate('http://0177.0.0.1/');
    }

    public function test_it_rejects_the_cloud_metadata_ip(): void
    {
        $validator = $this->validator([]);

        $this->expectException(ReplayTargetRejected::class);
        $validator->validate('http://169.254.169.254/latest/meta-data/');
    }

    public function test_it_rejects_when_dns_returns_nothing(): void
    {
        $validator = $this->validator(['missing.test' => []]);

        $this->expectException(ReplayTargetRejected::class);
        $validator->validate('https://missing.test/');
    }

    public function test_allow_private_targets_accepts_rfc1918(): void
    {
        $validator = $this->validator(['nginx' => ['172.18.0.2']], allowPrivate: true);

        $target = $validator->validate('http://nginx/in/demo');

        $this->assertSame(['nginx:80:172.18.0.2'], $target->curlResolve());
    }

    public function test_public_ipv4_literals_skip_dns(): void
    {
        $resolver = new RecordingDnsResolver([]);
        $validator = new ReplayTargetValidator($resolver, false);

        $target = $validator->validate('https://1.1.1.1/webhook');

        $this->assertSame([], $resolver->queries);
        $this->assertSame(['1.1.1.1:443:1.1.1.1'], $target->curlResolve());
    }

    /**
     * @param  array<string, list<string>>  $records
     */
    private function validator(array $records, bool $allowPrivate = false): ReplayTargetValidator
    {
        return new ReplayTargetValidator(new RecordingDnsResolver($records), $allowPrivate);
    }
}
