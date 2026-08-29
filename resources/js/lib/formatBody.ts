export function prettyJson(body: string): string | null {
    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch {
        return null;
    }
}

export function hexDumpFromBase64(base64: string): string {
    const binary = atob(base64);
    const lines: string[] = [];

    for (let offset = 0; offset < binary.length; offset += 16) {
        const slice = binary.slice(offset, offset + 16);
        const hex = Array.from(slice, (char) =>
            char.charCodeAt(0).toString(16).padStart(2, '0'),
        ).join(' ');
        const ascii = Array.from(slice, (char) => {
            const code = char.charCodeAt(0);

            return code >= 32 && code < 127 ? char : '.';
        }).join('');

        lines.push(
            `${offset.toString(16).padStart(8, '0')}  ${hex.padEnd(47, ' ')}  ${ascii}`,
        );
    }

    return lines.join('\n');
}
