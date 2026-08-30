<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function artisan($command, $parameters = []): PendingCommand
    {
        $pending = parent::artisan($command, $parameters);

        if (! $pending instanceof PendingCommand) {
            $this->fail('Expected a PendingCommand. Leave mockConsoleOutput enabled.');
        }

        return $pending;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    protected function html(TestResponse $response): string
    {
        $content = $response->getContent();

        if ($content === false) {
            $this->fail('Response has no body.');
        }

        return $content;
    }
}
