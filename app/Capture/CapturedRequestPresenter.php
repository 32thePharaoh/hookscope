<?php

namespace App\Capture;

use App\Models\CapturedRequest;

final class CapturedRequestPresenter
{
    public const LIST_COLUMNS = [
        'id',
        'endpoint_id',
        'method',
        'path',
        'query',
        'content_type',
        'ip',
        'size_bytes',
        'received_at',
    ];

    public const CONTENT_TYPE_TABLE_LIMIT = 48;

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
    public static function forList(CapturedRequest $capture): array
    {
        return [
            'id' => $capture->id,
            'method' => $capture->method,
            'path' => $capture->path,
            'query' => self::queryString($capture),
            'content_type' => self::truncateContentType($capture->content_type),
            'ip' => $capture->ip,
            'size_bytes' => $capture->size_bytes,
            'received_at' => $capture->received_at->toIso8601String(),
        ];
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
     *     received_at: string,
     *     headers: array<string, mixed>,
     *     body: string,
     *     body_encoding: string
     * }
     */
    public static function forDetail(CapturedRequest $capture): array
    {
        return [
            'id' => $capture->id,
            'method' => $capture->method,
            'path' => $capture->path,
            'query' => self::queryString($capture),
            'content_type' => $capture->content_type,
            'ip' => $capture->ip,
            'size_bytes' => $capture->size_bytes,
            'received_at' => $capture->received_at->toIso8601String(),
            'headers' => $capture->headers,
            ...self::encodeBody($capture->body, $capture->body_encoding),
        ];
    }

    /**
     * Reports the encoding actually applied rather than the stored one, so the
     * marker cannot disagree with the payload it labels.
     *
     * @return array{body: string, body_encoding: string}
     */
    public static function encodeBody(string $body, string $encoding): array
    {
        if ($encoding === 'utf-8' && mb_check_encoding($body, 'UTF-8')) {
            return ['body' => $body, 'body_encoding' => 'utf-8'];
        }

        return ['body' => base64_encode($body), 'body_encoding' => 'binary'];
    }

    private static function queryString(CapturedRequest $capture): ?string
    {
        $query = $capture->getAttribute('query');

        return is_string($query) ? $query : null;
    }

    private static function truncateContentType(?string $contentType): ?string
    {
        if ($contentType === null) {
            return null;
        }

        if (mb_strlen($contentType) <= self::CONTENT_TYPE_TABLE_LIMIT) {
            return $contentType;
        }

        return mb_substr($contentType, 0, self::CONTENT_TYPE_TABLE_LIMIT).'…';
    }
}
