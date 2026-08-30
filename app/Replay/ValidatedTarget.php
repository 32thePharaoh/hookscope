<?php

namespace App\Replay;

final readonly class ValidatedTarget
{
    /**
     * @param  list<string>  $ips
     */
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $scheme,
        public array $ips,
    ) {}

    /**
     * @return list<string>
     */
    public function curlResolve(): array
    {
        $entries = [];

        foreach ($this->ips as $ip) {
            $entries[] = $this->host.':'.$this->port.':'.$ip;
        }

        return $entries;
    }
}
