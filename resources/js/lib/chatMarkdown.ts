const escapeHtml = (value: string): string =>
    value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');

const inline = (value: string): string => {
    let text = escapeHtml(value);

    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    text = text.replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<em>$2</em>');
    text = text.replace(/(^|[\s(])_([^_\n]+)_/g, '$1<em>$2</em>');
    text = text.replace(
        /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
    );

    return text;
};

/**
 * Limited markdown for Command Center bubbles: emphasis, lists, code, links.
 * HTML in the source is escaped before formatting is applied.
 */
export const renderChatMarkdown = (source: string): string => {
    const fences: string[] = [];
    const withoutFences = source.replace(/```([\s\S]*?)```/g, (_match, code: string) => {
        const index = fences.push(`<pre><code>${escapeHtml(code.trim())}</code></pre>`) - 1;

        return `\u0000${index}\u0000`;
    });

    const blocks = withoutFences.replaceAll('\r\n', '\n').split(/\n{2,}/);
    const html = blocks
        .map((block) => {
            const trimmed = block.trim();

            if (trimmed === '') {
                return '';
            }

            if (/^\u0000\d+\u0000$/.test(trimmed)) {
                return trimmed;
            }

            const lines = trimmed.split('\n');
            const unordered = lines.every((line) => /^\s*[-*]\s+/.test(line));
            const ordered = lines.every((line) => /^\s*\d+\.\s+/.test(line));

            if (unordered) {
                const items = lines
                    .map((line) => `<li>${inline(line.replace(/^\s*[-*]\s+/, ''))}</li>`)
                    .join('');

                return `<ul>${items}</ul>`;
            }

            if (ordered) {
                const items = lines
                    .map((line) => `<li>${inline(line.replace(/^\s*\d+\.\s+/, ''))}</li>`)
                    .join('');

                return `<ol>${items}</ol>`;
            }

            return `<p>${lines.map((line) => inline(line)).join('<br>')}</p>`;
        })
        .filter(Boolean)
        .join('');

    return html.replace(/\u0000(\d+)\u0000/g, (_match, index: string) => fences[Number(index)] ?? '');
};
