<?php

namespace App\Jobs;

use App\Models\Replay;
use App\Replay\CappedBuffer;
use App\Replay\ForwardedHeaderSet;
use App\Replay\HeaderForwarder;
use App\Replay\ReplayTargetRejected;
use App\Replay\ReplayTargetValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class ReplayCapturedRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $replayId,
        public bool $forwardSensitive = false,
    ) {}

    public function handle(ReplayTargetValidator $validator): void
    {
        $replay = Replay::query()->find($this->replayId);

        if ($replay === null) {
            return;
        }

        $capture = $replay->capturedRequest;
        $started = hrtime(true);

        try {
            $target = $validator->validate($replay->target_url);
            $headers = HeaderForwarder::forReplay($capture->headers, $this->forwardSensitive);
            $buffer = new CappedBuffer((int) config('hookscope.replay_snippet_bytes'));

            $contentType = self::contentType($headers);
            $requestHeaders = [];

            foreach ($headers->headers as $name => $values) {
                if (strtolower($name) !== 'content-type') {
                    $requestHeaders[$name] = $values;
                }
            }

            $response = Http::timeout((int) config('hookscope.replay_timeout'))
                ->connectTimeout((int) config('hookscope.replay_connect_timeout'))
                ->withOptions([
                    'allow_redirects' => false,
                    'decode_content' => false,
                    'http_errors' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => $target->curlResolve(),
                        CURLOPT_WRITEFUNCTION => static fn (mixed $_ch, string $data): int => $buffer->write($data),
                    ],
                ])
                ->withHeaders($requestHeaders)
                ->withBody($capture->body, $contentType)
                ->send($capture->method, $replay->target_url);

            $body = $buffer->contents() !== '' || $buffer->hitCap
                ? $buffer->contents()
                : (string) $response->body();

            $replay->forceFill([
                'status_code' => $response->status(),
                'duration_ms' => self::elapsedMs($started),
                'error' => null,
                'response_snippet' => base64_encode($body),
                'forwarded_headers' => $headers->forwarded,
            ])->save();
        } catch (ReplayTargetRejected $exception) {
            $replay->forceFill([
                'status_code' => null,
                'duration_ms' => self::elapsedMs($started),
                'error' => self::asciiError($exception->getMessage()),
                'response_snippet' => null,
                'forwarded_headers' => [],
            ])->save();
        } catch (Throwable $exception) {
            $replay->forceFill([
                'status_code' => null,
                'duration_ms' => self::elapsedMs($started),
                'error' => self::asciiError($exception->getMessage()),
                'response_snippet' => null,
            ])->save();
        }
    }

    private static function contentType(ForwardedHeaderSet $headers): string
    {
        foreach ($headers->headers as $name => $values) {
            if (strtolower($name) === 'content-type' && $values !== []) {
                return $values[0];
            }
        }

        return 'application/octet-stream';
    }

    private static function elapsedMs(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }

    private static function asciiError(string $message): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '', $message) ?? '';

        return $clean === '' ? 'Replay failed.' : $clean;
    }
}
