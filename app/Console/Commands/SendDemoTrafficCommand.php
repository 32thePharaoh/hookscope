<?php

namespace App\Console\Commands;

use App\Models\Endpoint;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

#[Signature('hookscope:demo-traffic {--base-url= : Origin that serves /in/{token}; must be nginx}')]
#[Description('Fire a short burst of sample webhooks at the Demo endpoint')]
class SendDemoTrafficCommand extends Command
{
    public function handle(): int
    {
        $endpoint = Endpoint::query()
            ->where('name', config('hookscope.demo.endpoint'))
            ->first();

        if ($endpoint === null) {
            $this->error('No Demo endpoint. Seed the database first.');

            return self::FAILURE;
        }

        RateLimiter::clear($this->captureLimiterKey($endpoint->token));

        $base = rtrim((string) ($this->option('base-url') ?: config('hookscope.demo.base_url')), '/');
        $url = $base.'/in/'.$endpoint->token;
        $oversized = str_repeat('x', ((int) config('hookscope.max_body_bytes')) + 64);

        $shots = [
            ['method' => 'GET'],
            ['method' => 'GET', 'query' => ['source' => 'demo']],
            ['method' => 'POST', 'body' => '{"ok":true}', 'contentType' => 'application/json'],
            ['method' => 'POST', 'body' => '{"event":"invoice.paid"}', 'contentType' => 'application/json'],
            ['method' => 'POST', 'body' => '{"event":"ping"}', 'contentType' => 'application/json'],
            ['method' => 'POST', 'body' => 'hello=world', 'contentType' => 'application/x-www-form-urlencoded'],
            ['method' => 'POST', 'body' => 'foo=bar&baz=1', 'contentType' => 'application/x-www-form-urlencoded'],
            ['method' => 'POST', 'body' => '<ping/>', 'contentType' => 'application/xml'],
            ['method' => 'POST', 'body' => '<note>demo</note>', 'contentType' => 'application/xml'],
            ['method' => 'POST', 'body' => $this->multipartBody('alpha'), 'contentType' => $this->multipartType('alpha')],
            ['method' => 'POST', 'body' => $this->multipartBody('beta'), 'contentType' => $this->multipartType('beta')],
            ['method' => 'PUT', 'body' => '{"replaced":true}', 'contentType' => 'application/json'],
            ['method' => 'PUT', 'body' => '{"id":1}', 'contentType' => 'application/json'],
            ['method' => 'PATCH', 'body' => '{"patched":true}', 'contentType' => 'application/json'],
            ['method' => 'PATCH', 'body' => '{"count":2}', 'contentType' => 'application/json'],
            ['method' => 'DELETE'],
            ['method' => 'POST', 'body' => 'odd', 'contentType' => 'text/plain', 'headers' => ['X-Odd' => "caf\xe9-\xff"]],
            ['method' => 'POST', 'body' => 'odd-2', 'contentType' => 'text/plain', 'headers' => ['X-Odd' => "caf\xe9-\xff"]],
            ['method' => 'POST', 'body' => "\x80\x81\xFF binary", 'contentType' => 'application/octet-stream'],
            ['method' => 'POST', 'body' => "\x00\xFF".'bin', 'contentType' => 'application/octet-stream'],
            ['method' => 'POST', 'body' => '{"again":true}', 'contentType' => 'application/json'],
            ['method' => 'POST', 'body' => 'again=1', 'contentType' => 'application/x-www-form-urlencoded'],
            ['method' => 'POST', 'body' => '<again/>', 'contentType' => 'application/xml'],
            ['method' => 'POST', 'body' => $oversized, 'contentType' => 'application/octet-stream', 'expect' => 413],
        ];

        foreach ($shots as $index => $shot) {
            $response = $this->fire($url, $shot);
            $expect = $shot['expect'] ?? 200;
            $label = sprintf('#%d %s', $index + 1, $shot['method']);

            if ($response->status() === $expect) {
                $this->line($label.' → '.$response->status());

                continue;
            }

            $this->warn($label.' → '.$response->status().' (expected '.$expect.')');
        }

        $this->info('Demo burst complete → '.$url);

        return self::SUCCESS;
    }

    /**
     * @param  array{method: string, query?: array<string, string>, body?: string, contentType?: string, headers?: array<string, string>, expect?: int}  $shot
     */
    private function fire(string $url, array $shot): Response
    {
        $request = Http::timeout(10)->withHeaders($shot['headers'] ?? []);

        if ($shot['method'] === 'GET') {
            return $request->get($url, $shot['query'] ?? []);
        }

        if ($shot['method'] === 'DELETE') {
            return $request->delete($url);
        }

        return $request
            ->withBody($shot['body'] ?? '', $shot['contentType'] ?? 'text/plain')
            ->send($shot['method'], $url);
    }

    private function captureLimiterKey(string $token): string
    {
        return md5('capture'.'endpoint:'.$token);
    }

    private function multipartBody(string $boundary): string
    {
        return "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"demo.txt\"\r\nContent-Type: text/plain\r\n\r\nhello\r\n--{$boundary}--\r\n";
    }

    private function multipartType(string $boundary): string
    {
        return 'multipart/form-data; boundary='.$boundary;
    }
}
