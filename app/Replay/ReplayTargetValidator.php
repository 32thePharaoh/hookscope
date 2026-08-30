<?php

namespace App\Replay;

final class ReplayTargetValidator
{
    public function __construct(
        private DnsResolver $resolver,
        private bool $allowPrivateTargets,
    ) {}

    public function validate(string $url): ValidatedTarget
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new ReplayTargetRejected('Target URL is not valid.');
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new ReplayTargetRejected('Only http and https targets are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ReplayTargetRejected('Target URLs must not include credentials.');
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ips = $this->addressesFor($host);

        if ($ips === []) {
            throw new ReplayTargetRejected('Target hostname could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (! $this->allowPrivateTargets && ForbiddenIp::isForbidden($ip)) {
                throw new ReplayTargetRejected('Target resolves to a private or reserved address.');
            }
        }

        return new ValidatedTarget($url, $host, $port, $scheme, $ips);
    }

    /**
     * @return list<string>
     */
    private function addressesFor(string $host): array
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $encoded = ForbiddenIp::ipv4FromEncodedHost($host);

        if ($encoded !== null) {
            return [$encoded];
        }

        return array_values(array_unique($this->resolver->resolve($host)));
    }
}
