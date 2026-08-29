export type HeaderValue =
    | string
    | {
          encoding: string;
          value: string;
      };

export function isEncodedHeader(
    value: HeaderValue,
): value is { encoding: string; value: string } {
    return typeof value === 'object' && value !== null && 'encoding' in value;
}

export function displayHeaderValue(value: HeaderValue): string {
    if (isEncodedHeader(value)) {
        return value.value;
    }

    return value;
}
