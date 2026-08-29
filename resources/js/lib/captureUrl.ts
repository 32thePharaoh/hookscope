export function captureUrl(token: string): string {
    const origin = typeof window === 'undefined' ? '' : window.location.origin;

    return `${origin}/in/${token}`;
}
