<?php

namespace App\Replay;

final readonly class ForwardedHeaderSet
{
    /**
     * @param  array<string, list<string>>  $headers
     * @param  list<string>  $forwarded
     */
    public function __construct(
        public array $headers,
        public array $forwarded,
    ) {}
}
