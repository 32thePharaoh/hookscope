<?php

namespace Tests\Unit\Replay;

use App\Replay\CappedBuffer;
use PHPUnit\Framework\TestCase;

class CappedBufferTest extends TestCase
{
    public function test_it_stops_writing_once_the_cap_is_hit_rather_than_buffering_the_rest(): void
    {
        $buffer = new CappedBuffer(8);

        $this->assertSame(4, $buffer->write('abcd'));
        $this->assertSame(0, $buffer->write('efghijkl'));
        $this->assertSame(0, $buffer->write('more'));
        $this->assertTrue($buffer->hitCap);
        $this->assertSame('abcdefgh', $buffer->contents());
        $this->assertSame(8, strlen($buffer->contents()));
    }

    public function test_a_chunk_that_fits_is_kept_in_full(): void
    {
        $buffer = new CappedBuffer(8);

        $this->assertSame(3, $buffer->write('hey'));
        $this->assertFalse($buffer->hitCap);
        $this->assertSame('hey', $buffer->contents());
    }
}
