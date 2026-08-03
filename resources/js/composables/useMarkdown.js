/**
 * Lightweight markdown → safe HTML. Pure JS, no new deps.
 *
 * Supports:
 *   - fenced code blocks
 *   - inline code, bold, italic
 *   - headings (h1, h2, h3)
 *   - bullet & numbered lists
 *   - tables (header + separator + body rows)
 *   - paragraphs (double newlines)
 *   - soft line breaks (single newline → <br>)
 *
 * All input is HTML-escaped first, then structural markdown is replaced.
 */

export function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function parseTableRow(line) {
    const trimmed = line.trim();
    // separator row ---|---|--- → drop
    if (/^\|?[\s:|-]+\|?$/.test(trimmed) && /-/.test(trimmed) && !/[A-Za-z0-9]/.test(trimmed)) {
        return null;
    }
    let inner = trimmed;
    if (inner.startsWith('|')) inner = inner.slice(1);
    if (inner.endsWith('|')) inner = inner.slice(0, -1);
    return inner.split('|').map(c => c.trim());
}

/**
 * Render markdown to safe HTML. Returns a string (use with v-html).
 */
export function formatMarkdown(raw) {
    if (!raw) return '';
    let text = String(raw).replace(/\r\n/g, '\n');

    // fenced code (extract first so list/table rules don't touch it)
    text = text.replace(/```([\s\S]*?)```/g, (_, code) =>
        `<pre class="md-pre"><code>${escapeHtml(code.replace(/^\n|\n$/g, ''))}</code></pre>`);

    const blocks = text.split(/(<pre class="md-pre">[\s\S]*?<\/pre>)/);
    return blocks.map((block) => {
        if (block.startsWith('<pre class="md-pre">')) return block;

        // Inline " - a. - b." → newlines before list markers (common LLM habit)
        let t = block.replace(/([:;])\s+[-–—]\s+/g, '$1\n- ').replace(/\s+[-–—]\s+(?=[A-Z0-9*])/g, '\n- ');

        // escape once, then reapply structural HTML for markdown
        t = escapeHtml(t);

        t = t.replace(/`([^`]+)`/g, '<code class="md-code">$1</code>');
        t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
        t = t.replace(/^### (.+)$/gm, '<div class="md-h3">$1</div>');
        t = t.replace(/^## (.+)$/gm, '<div class="md-h2">$1</div>');
        t = t.replace(/^# (.+)$/gm, '<div class="md-h2">$1</div>');

        // Markdown table: header row + separator + body rows → <table>
        t = t.replace(
            /(?:^\|?[ \t]*:?-+:?[ \t]*(\|[ \t]*:?-+:?[ \t]*)+\|?[ \t]*\n)((?:^\|?.+\n?)+)/gm,
            (match, _sep, rowsBlock) => {
                const rows = rowsBlock.trim().split('\n').map(parseTableRow).filter(r => r !== null);
                if (rows.length === 0) return match;
                const headerCells = rows.shift();
                const headerHtml = '<tr>' + headerCells.map(c => `<th>${c}</th>`).join('') + '</tr>';
                const bodyHtml = rows.map(r =>
                    '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>'
                ).join('');
                return `<table class="md-table"><thead>${headerHtml}</thead><tbody>${bodyHtml}</tbody></table>`;
            }
        );

        // Bullet & numbered lists
        t = t.replace(/(?:^(?:[-*]|\d+\.) .+(?:\n|$))+/gm, (block2) => {
            const items = block2.trim().split('\n').map((line) =>
                line.replace(/^(?:[-*]|\d+\.)\s+/, ''));
            return `<ul class="md-ul">${items.map((i) => `<li>${i}</li>`).join('')}</ul>`;
        });

        t = t.replace(/\n\n+/g, '</p><p class="md-p">');
        t = t.replace(/\n/g, '<br>');

        if (!t.startsWith('<ul') && !t.startsWith('<div') && !t.startsWith('<pre') && !t.startsWith('<table')) {
            t = `<p class="md-p">${t}</p>`;
        }
        return t;
    }).join('');
}

/**
 * Composable wrapper for ergonomics inside Vue components.
 */
export function useMarkdown() {
    return { formatMessage: formatMarkdown, escapeHtml };
}
