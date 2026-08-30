<?php

namespace Tests\Unit\Replay;

use App\Replay\CappedBuffer;
use PHPUnit\Framework\TestCase;

class CappedBufferTest extends TestCase
{
    public function test_it_discards_past_the_cap_without_aborting_the_transfer(): void
    {
        $buffer = new CappedBuffer(8);

        // Must report the whole chunk consumed: a short return is
        // CURLE_WRITE_ERROR, which aborts the transfer and loses the status code.
        $this->assertSame(4, $buffer->write('abcd'));
        $this->assertSame(8, $buffer->write('efghijkl'));
        $this->assertSame(4, $buffer->write('more'));
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
