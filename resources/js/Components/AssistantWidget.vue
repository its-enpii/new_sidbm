<script setup>
import { onBeforeUnmount, ref } from 'vue';
import AppIcon from './AppIcon.vue';

const ASSISTANT_NAME = 'Ariel';

const open = ref(false);
const loading = ref(false);
const error = ref(null);
const mountEl = ref(null);
const rootEl = ref(null);

let scriptEl = null;
let greetingObserver = null;

function onDocumentPointerDown(event) {
    if (!open.value || !rootEl.value) return;
    const target = event.target;
    if (target instanceof Node && rootEl.value.contains(target)) return;
    open.value = false;
}

document.addEventListener('pointerdown', onDocumentPointerDown, true);

function pickGreeting() {
    const hour = new Date().getHours();
    const salam = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 18 ? 'Selamat sore' : 'Selamat malam';

    const pool = [
        `${salam}, apa yang bisa ${ASSISTANT_NAME} bantu hari ini?`,
        `Butuh bantuan? ${ASSISTANT_NAME} siap membantu.`,
        `Perlu bantuan mencatat transaksi? Mungkin ${ASSISTANT_NAME} bisa bantu.`,
        `Halo! ${ASSISTANT_NAME} di sini. Ada data yang ingin dicari?`,
        `${salam}. ${ASSISTANT_NAME} siap bantu cek angsuran, jurnal, atau data anggota.`,
        `Ada yang bisa ${ASSISTANT_NAME} bantu? Langsung saja tulis kebutuhannya.`,
    ];

    return pool[Math.floor(Math.random() * pool.length)];
}

function injectGreetingBubble(text) {
    if (!text || !mountEl.value) return;

    const tryInject = () => {
        const list = mountEl.value?.querySelector('.enc-embed-list');
        if (!list || list.querySelector('[data-sidbm-greeting]')) return true;

        const bubble = document.createElement('div');
        bubble.className = 'enc-embed-msg assistant';
        bubble.setAttribute('data-sidbm-greeting', '1');
        bubble.textContent = text;
        list.prepend(bubble);
        return true;
    };

    if (tryInject()) return;

    greetingObserver?.disconnect();
    greetingObserver = new MutationObserver(() => {
        if (tryInject()) {
            greetingObserver?.disconnect();
            greetingObserver = null;
        }
    });
    greetingObserver.observe(mountEl.value, { childList: true, subtree: true });
}

async function ensureWidget() {
    if (scriptEl || !mountEl.value) return;
    loading.value = true;
    error.value = null;

    try {
        const res = await fetch('/api/assistant/embed-token', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || `HTTP ${res.status}`);
        }

        // SIDBM owns the visible greeting; persona greeting from Encompletion is system-facing.
        const greeting = pickGreeting();

        scriptEl = document.createElement('script');
        scriptEl.src = data.widget_script;
        scriptEl.defer = true;
        scriptEl.setAttribute('data-endpoint', data.endpoint);
        scriptEl.setAttribute('data-token', data.embed_token);
        scriptEl.setAttribute('data-mount', '#sidbm-encompletion-mount');
        scriptEl.onload = () => injectGreetingBubble(greeting);
        scriptEl.onerror = () => {
            error.value = 'Gagal memuat widget asisten.';
        };
        document.body.appendChild(scriptEl);

        setTimeout(() => injectGreetingBubble(greeting), 150);
        setTimeout(() => injectGreetingBubble(greeting), 500);
    } catch (e) {
        error.value = e?.message || 'Gagal menghubungkan asisten.';
    } finally {
        loading.value = false;
    }
}

async function toggle() {
    open.value = !open.value;
    if (open.value) {
        await ensureWidget();
    }
}

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown, true);
    greetingObserver?.disconnect();
    greetingObserver = null;
    if (scriptEl?.parentNode) {
        scriptEl.parentNode.removeChild(scriptEl);
    }
    scriptEl = null;
});
</script>

<template>
    <div ref="rootEl" class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end gap-3 sm:bottom-6 sm:right-6">
        <div
            v-show="open"
            class="assistant-panel pointer-events-auto flex h-[min(36rem,75vh)] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
            role="dialog"
            :aria-label="ASSISTANT_NAME"
        >
            <div class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant bg-primary px-4 py-3 text-on-primary">
                <div class="flex items-center gap-2">
                    <AppIcon name="smart_toy" class="text-xl" />
                    <span class="text-sm font-bold">{{ ASSISTANT_NAME }}</span>
                </div>
                <button type="button" class="rounded-lg p-1 hover:bg-on-primary/10" aria-label="Tutup asisten" @click="open = false">
                    <AppIcon name="close" class="text-xl" />
                </button>
            </div>

            <p v-if="loading" class="shrink-0 p-4 text-sm text-on-surface-variant">Menghubungkan…</p>
            <p v-else-if="error" class="shrink-0 p-4 text-sm text-error">{{ error }}</p>
            <div id="sidbm-encompletion-mount" ref="mountEl" class="assistant-mount min-h-0 flex-1 overflow-hidden" />
        </div>

        <button
            type="button"
            class="pointer-events-auto grid size-14 place-items-center rounded-full bg-primary text-on-primary shadow-lg transition hover:bg-primary-container focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            :aria-expanded="open"
            :aria-label="`Buka ${ASSISTANT_NAME}`"
            @click="toggle"
        >
            <AppIcon :name="open ? 'close' : 'smart_toy'" class="text-2xl" />
        </button>
    </div>
</template>

<style>
/* Embed fills SIDBM chrome; hide duplicate header. Tokens only. */
.assistant-mount,
.assistant-mount .enc-embed {
    height: 100% !important;
    max-width: none !important;
    max-height: none !important;
}

.assistant-mount .enc-embed {
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: var(--color-surface-container-lowest) !important;
    color: var(--color-on-surface) !important;
}

.assistant-mount .enc-embed-header {
    display: none !important;
}

.assistant-mount .enc-embed-list {
    gap: 0.75rem !important;
    padding: 1rem !important;
    background: var(--color-surface) !important;
}

/* Chat bubbles */
.assistant-mount .enc-embed-msg {
    max-width: 85% !important;
    padding: 0.625rem 0.875rem !important;
    border-radius: 1rem !important;
    line-height: 1.45 !important;
    font-size: 0.875rem !important;
    box-shadow: none !important;
}

.assistant-mount .enc-embed-msg.user {
    align-self: flex-end !important;
    border-bottom-right-radius: 0.25rem !important;
    background: var(--color-primary) !important;
    color: var(--color-on-primary) !important;
}

.assistant-mount .enc-embed-msg.assistant {
    align-self: flex-start !important;
    border-bottom-left-radius: 0.25rem !important;
    background: var(--color-surface-container-lowest) !important;
    color: var(--color-on-surface) !important;
    border: 1px solid var(--color-outline-variant) !important;
}

.assistant-mount .enc-embed-msg.error {
    align-self: flex-start !important;
    border-bottom-left-radius: 0.25rem !important;
    background: var(--color-error-container) !important;
    color: var(--color-on-error-container) !important;
    border: 1px solid transparent !important;
}

.assistant-mount .enc-embed-composer {
    background: var(--color-surface-container-lowest) !important;
    border-top: 1px solid var(--color-outline-variant) !important;
    padding: 0.75rem !important;
    gap: 0.5rem !important;
}

.assistant-mount .enc-embed-composer textarea {
    border: 1px solid var(--color-outline-variant) !important;
    border-radius: 0.75rem !important;
    background: var(--color-surface) !important;
    color: var(--color-on-surface) !important;
    padding: 0.5rem 0.75rem !important;
}

.assistant-mount .enc-embed-composer button {
    border-radius: 0.75rem !important;
    background: var(--color-primary) !important;
    color: var(--color-on-primary) !important;
    font-weight: 600 !important;
}
</style>
