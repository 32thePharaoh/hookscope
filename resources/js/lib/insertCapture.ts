import type { CaptureListItem } from '@/types';

export function compareCaptureOrder(
    a: CaptureListItem,
    b: CaptureListItem,
): number {
    const byTime = b.received_at.localeCompare(a.received_at);

    if (byTime !== 0) {
        return byTime;
    }

    return b.id - a.id;
}

export function insertCapture(
    rows: CaptureListItem[],
    incoming: CaptureListItem,
    perPage: number,
): CaptureListItem[] {
    if (rows.some((row) => row.id === incoming.id)) {
        return rows;
    }

    const next = [...rows, incoming].sort(compareCaptureOrder);

    return next.slice(0, perPage);
}
