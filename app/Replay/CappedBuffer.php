<?php

namespace App\Replay;

final class CappedBuffer
{
    public bool $hitCap = false;

    private string $buffer = '';

    public function __construct(private int $maxBytes) {}

    public function write(string $chunk): int
    {
        if ($this->hitCap) {
            return 0;
        }

        $room = $this->maxBytes - strlen($this->buffer);

        if ($room <= 0) {
            $this->hitCap = true;

            return 0;
        }

        if (strlen($chunk) > $room) {
            $this->buffer .= substr($chunk, 0, $room);
            $this->hitCap = true;

            return 0;
        }

        $this->buffer .= $chunk;

        return strlen($chunk);
    }

    public function contents(): string
    {
        return $this->buffer;
    }
}
