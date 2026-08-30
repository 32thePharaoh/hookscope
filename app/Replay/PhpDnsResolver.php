<?php

namespace App\Replay;

final class PhpDnsResolver implements DnsResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];

        $a = dns_get_record($host, DNS_A);

        if (is_array($a)) {
            foreach ($a as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
            }
        }

        $aaaa = dns_get_record($host, DNS_AAAA);

        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
