import type { HeaderValue } from '@/lib/headerValue';

export type EndpointSummary = {
    id: number;
    name: string;
    token: string;
    retention_days: number;
    created_at?: string | null;
};

export type CaptureListItem = {
    id: number;
    method: string;
    path: string;
    query: string | null;
    content_type: string | null;
    ip: string | null;
    size_bytes: number;
    received_at: string;
};

export type CursorPage<T> = {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    per_page: number;
};

export type CaptureDetail = CaptureListItem & {
    headers: Record<string, HeaderValue[]>;
    body: string;
    body_encoding: string;
};
