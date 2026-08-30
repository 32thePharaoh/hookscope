<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class RequestCaptured implements ShouldBroadcast
{
    use Dispatchable;

    /**
     * @param  array{
     *     id: int,
     *     method: string,
     *     path: string,
     *     query: string|null,
     *     content_type: string|null,
     *     ip: string|null,
     *     size_bytes: int,
     *     received_at: string
     * }  $payload
     */
    public function __construct(
        public int $endpointId,
        public array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('endpoints.'.$this->endpointId);
    }

    public function broadcastAs(): string
    {
        return 'RequestCaptured';
    }

    /**
     * @return array{
     *     id: int,
     *     method: string,
     *     path: string,
     *     query: string|null,
     *     content_type: string|null,
     *     ip: string|null,
     *     size_bytes: int,
     *     received_at: string
     * }
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
