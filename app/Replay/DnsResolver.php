<?php

namespace App\Replay;

interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
