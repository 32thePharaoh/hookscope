<?php

namespace App\Replay;

final class CappedBuffer
{
    public bool $hitCap = false;

    private string $buffer = '';

    public function __construct(private int $maxBytes) {}

    /**
     * Buffers up to the cap and discards the rest, always reporting the full
     * chunk as consumed. Returning less than strlen($chunk) is CURLE_WRITE_ERROR
     * to curl, which aborts the transfer and loses the status code, so the cap
     * has to bound memory rather than the request. The replay timeout bounds
     * how long a large body can keep streaming.
     */
    public function write(string $chunk): int
    {
        $length = strlen($chunk);
        $room = $this->maxBytes - strlen($this->buffer);

        if ($room > 0) {
            $this->buffer .= substr($chunk, 0, $room);
        }

        if ($length > max($room, 0)) {
            $this->hitCap = true;
        }

        return $length;
    }

    public function contents(): string
    {
        return $this->buffer;
    }
}
