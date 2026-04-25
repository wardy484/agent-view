/**
 * REQ-M6-029: tiny platform helpers used by the inline comment composer to
 * label the active "submit" keyboard shortcut. `navigator.platform` is
 * deprecated but still the most reliable signal for Mac detection in
 * browsers that ship it; we fall back to `userAgent` everywhere else.
 */

export function isMac(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const platform =
        (typeof navigator.platform === 'string' ? navigator.platform : '') ||
        (typeof navigator.userAgent === 'string' ? navigator.userAgent : '');

    return /Mac|iPhone|iPad|iPod/i.test(platform);
}

export function metaKeyLabel(): string {
    return isMac() ? '⌘' : 'Ctrl';
}

export function metaKeyShortcutLabel(): string {
    return isMac() ? '⌘↩' : 'Ctrl+↵';
}
