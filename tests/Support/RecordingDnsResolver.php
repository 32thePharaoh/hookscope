<?php

namespace Tests\Support;

use App\Replay\DnsResolver;

final class RecordingDnsResolver implements DnsResolver
{
    /** @var list<string> */
    public array $queries = [];

    /**
     * @param  array<string, list<string>>  $records
     */
    public function __construct(private array $records) {}

    public function resolve(string $host): array
    {
        $this->queries[] = $host;

        return $this->records[$host] ?? [];
    }
}
