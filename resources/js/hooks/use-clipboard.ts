// Credit: https://usehooks-ts.com/
import { useState } from 'react';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
export type UseClipboardReturn = [CopiedValue, CopyFn];

/**
 * Copy to the clipboard, falling back to a hidden textarea when the
 * asynchronous clipboard API is unavailable outside a secure context.
 */
function legacyCopy(text: string): boolean {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.opacity = '0';

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, text.length);

    try {
        return document.execCommand('copy');
    } finally {
        document.body.removeChild(textarea);
    }
}

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);

    const copy: CopyFn = async (text) => {
        let copied = false;

        try {
            if (window.isSecureContext && navigator?.clipboard) {
                await navigator.clipboard.writeText(text);
                copied = true;
            }
        } catch {
            copied = false;
        }

        if (!copied) {
            try {
                copied = legacyCopy(text);
            } catch {
                copied = false;
            }
        }

        setCopiedText(copied ? text : null);

        return copied;
    };

    return [copiedText, copy];
}
