<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppSwitch from '../../Components/AppSwitch.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({
    orchestrator: { type: Object, required: true },
    defaults: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

const form = useForm({
    orchestrator_base_url: props.orchestrator.orchestrator_base_url || props.defaults.orchestrator_base_url || '',
    orchestrator_public_url: props.orchestrator.orchestrator_public_url || '',
    adapter_base_url: props.orchestrator.adapter_base_url || props.defaults.adapter_base_url || '',
    shared_secret: '',
    widget_enabled: props.orchestrator.widget_enabled ?? props.defaults.widget_enabled ?? false,
    signature_max_skew_seconds: Math.round(
        (props.orchestrator.signature_max_skew_ms || props.defaults.signature_max_skew_ms || 300000) / 1000
    ),
});

const showSecret = ref(false);
const testResult = ref(null);
const testLoading = ref(false);

function submit() {
    form.signature_max_skew_ms = Number(form.signature_max_skew_seconds) * 1000;
    form.put('/admin/integrations/orchestrator', { preserveScroll: true });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function testConnection() {
    testLoading.value = true;
    testResult.value = null;
    try {
        const response = await fetch('/admin/integrations/orchestrator/test', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        testResult.value = await response.json();
    } catch (e) {
        testResult.value = { success: false, status: 'client_error', message: e.message };
    } finally {
        testLoading.value = false;
    }
}

const tone = computed(() => {
    if (!testResult.value) return 'neutral';
    return testResult.value.success ? 'success' : 'warning';
});

// === Chat test (SSE) — model sama dengan AssistantWidget ===
const chatMessages = ref([]);
const chatInput = ref('');
const chatBusy = ref(false);
const chatError = ref(null);
const chatTyping = ref(false);
const chatTypingLabel = ref('Sedang mengetik');
const chatListEl = ref(null);
let chatAbort = null;
let chatSeq = 0;

function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatMessage(raw) {
    if (!raw) return '';
    let text = String(raw).replace(/\r\n/g, '\n');
    text = text.replace(/```([\s\S]*?)```/g, (_, code) =>
        `<pre class="my-2 overflow-x-auto rounded-md bg-on-surface/5 p-2 text-xs"><code>${escapeHtml(code.replace(/^\n|\n$/g, ''))}</code></pre>`);
    const parts = text.split(/(<pre[^>]*>[\s\S]*?<\/pre>)/);
    return parts.map((part) => {
        if (part.startsWith('<pre')) return part;
        let t = escapeHtml(part);
        t = t.replace(/`([^`]+)`/g, '<code class="rounded bg-on-surface/5 px-1 text-xs">$1</code>');
        t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/(?:^|\n)([-*] .+(?:\n|$))+/g, (block) => {
            const items = block.trim().split('\n').map((l) => l.replace(/^[-*]\s+/, ''));
            return `<ul class="my-1 list-disc pl-5">${items.map((i) => `<li>${i}</li>`).join('')}</ul>`;
        });
        t = t.replace(/\n/g, '<br>');
        return t;
    }).join('');
}

async function scrollChatBottom() {
    await nextTick();
    if (chatListEl.value) chatListEl.value.scrollTop = chatListEl.value.scrollHeight;
}

function pushChat(msg) {
    const id = ++chatSeq;
    chatMessages.value.push({ id, ...msg });
    scrollChatBottom();
    return chatMessages.value[chatMessages.value.length - 1];
}

function parseSseBuffer(buffer) {
    const blocks = buffer.split('\n\n');
    const rest = blocks.pop() ?? '';
    const events = [];
    for (const block of blocks) {
        let event = 'message';
        const dataLines = [];
        for (const line of block.split('\n')) {
            if (line.startsWith('event:')) event = line.slice(6).trim();
            else if (line.startsWith('data:')) dataLines.push(line.slice(5).trim());
        }
        if (!dataLines.length) continue;
        let data = dataLines.join('\n');
        try { data = JSON.parse(data); } catch { /* keep raw */ }
        events.push({ event, data });
    }
    return { events, rest };
}

async function readSseStream(response, onEvent) {
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${await response.text()}`);
    }
    const reader = response.body?.getReader();
    if (!reader) throw new Error('Stream tidak tersedia.');
    const decoder = new TextDecoder();
    let buffer = '';
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        const { events, rest } = parseSseBuffer(buffer);
        buffer = rest;
        for (const e of events) onEvent(e.event, e.data);
    }
    if (buffer.trim()) {
        const { events } = parseSseBuffer(buffer + '\n\n');
        for (const e of events) onEvent(e.event, e.data);
    }
}

function handleChatEvent(event, data, assistantMsg) {
    if (event === 'text') {
        const delta = typeof data === 'string' ? data : (data?.delta ?? '');
        if (delta) {
            chatTyping.value = false;
            assistantMsg.content += delta;
            scrollChatBottom();
        }
        return;
    }
    if (event === 'tool_use') {
        chatTyping.value = true;
        chatTypingLabel.value = 'Mencari data…';
        scrollChatBottom();
        return;
    }
    if (event === 'tool_result') {
        chatTyping.value = true;
        chatTypingLabel.value = data?.ok === false ? 'Menyusun jawaban…' : 'Menyusun jawaban…';
        scrollChatBottom();
        return;
    }
    if (event === 'error') {
        chatTyping.value = false;
        chatError.value = data?.message || 'Terjadi kesalahan.';
        return;
    }
}

async function sendChat() {
    const content = chatInput.value.trim();
    if (!content || chatBusy.value) return;
    chatInput.value = '';
    chatError.value = null;
    pushChat({ role: 'user', content });
    const assistantMsg = pushChat({ role: 'assistant', content: '' });
    chatBusy.value = true;
    chatTyping.value = true;
    chatTypingLabel.value = 'Sedang mengetik';
    chatAbort = new AbortController();
    scrollChatBottom();
    try {
        const res = await fetch('/admin/integrations/orchestrator/chat', {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: content }),
            signal: chatAbort.signal,
        });
        await readSseStream(res, (event, data) => handleChatEvent(event, data, assistantMsg));
        if (!assistantMsg.content) {
            assistantMsg.content = 'Tidak ada respon dari orchestrator.';
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            chatError.value = e?.message || 'Gagal mengirim pesan.';
            assistantMsg.content = assistantMsg.content || chatError.value;
        }
    } finally {
        chatTyping.value = false;
        chatBusy.value = false;
        chatAbort = null;
        scrollChatBottom();
    }
}

function cancelChat() {
    chatAbort?.abort();
    chatBusy.value = false;
    chatTyping.value = false;
}
</script>

<template>
    <Head title="Integrasi Orchestrator" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">Integrasi Orchestrator AI</h1>
                <p class="mt-1 text-on-surface-variant">Sambungkan SIDBM dengan orchestrator AI.</p>
            </header>

            <AppCard v-if="flash">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-secondary-container text-secondary">✓</div>
                    <p class="font-bold text-primary">{{ flash.message }}</p>
                </div>
            </AppCard>

            <AppCard>
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-lg border border-outline-variant bg-surface-container-lowest p-3 text-sm">
                    <AppBadge :tone="orchestrator.configured ? 'success' : 'warning'">
                        {{ orchestrator.configured ? 'Terhubung' : 'Belum dikonfigurasi' }}
                    </AppBadge>
                    <span v-if="orchestrator.has_secret" class="text-on-surface-variant">Shared secret tersimpan (encrypted).</span>
                    <span v-else class="text-on-surface-variant">Shared secret belum diisi.</span>
                    <button
                        type="button"
                        class="ml-auto inline-flex items-center gap-1 rounded-md border border-outline-variant bg-surface px-3 py-1.5 text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container"
                        :disabled="testLoading"
                        @click="testConnection"
                    >
                        <AppIcon name="network_check" />
                        <span>{{ testLoading ? 'Menguji…' : 'Test Connection' }}</span>
                    </button>
                </div>

                <div v-if="testResult" class="mb-5 rounded-lg border p-3 text-sm"
                    :class="testResult.success ? 'border-green-300 bg-green-50 text-green-900' : 'border-amber-300 bg-amber-50 text-amber-900'">
                    <p class="font-semibold">{{ testResult.message }}</p>
                    <p v-if="testResult.latency_ms !== null && testResult.latency_ms !== undefined" class="mt-0.5 text-xs">
                        Latency: {{ testResult.latency_ms }} ms
                    </p>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <AppInput
                        v-model="form.orchestrator_base_url"
                        label="URL Server"
                        placeholder="http://orchestrator:8100"
                        hint="URL internal untuk SIDBM."
                        required
                        :error="form.errors.orchestrator_base_url"
                    />
                    <AppInput
                        v-model="form.orchestrator_public_url"
                        label="URL Widget"
                        placeholder="https://chat.koperasi.id"
                        hint="URL publik untuk widget browser."
                        :error="form.errors.orchestrator_public_url"
                    />
                    <AppInput
                        v-model="form.adapter_base_url"
                        label="URL Callback"
                        placeholder="http://nginx"
                        hint="URL untuk callback dari orchestrator."
                        :error="form.errors.adapter_base_url"
                    />

                    <AppInput
                        v-model="form.shared_secret"
                        :type="showSecret ? 'text' : 'password'"
                        label="Kunci Rahasia"
                        icon="key"
                        :placeholder="orchestrator.has_secret ? '•••••••• (sudah tersimpan)' : 'minimal 8 karakter'"
                        hint="Kosongkan bila tidak ingin mengubah."
                        :error="form.errors.shared_secret"
                    >
                        <template #trailing>
                            <button
                                type="button"
                                class="grid size-10 place-items-center rounded-md text-on-surface-variant hover:bg-surface-container"
                                @click="showSecret = !showSecret"
                            >
                                <AppIcon :name="showSecret ? 'visibility_off' : 'visibility'" />
                            </button>
                        </template>
                    </AppInput>

                    <AppInput
                        v-model.number="form.signature_max_skew_seconds"
                        label="Batas Perbedaan Waktu (detik)"
                        type="number"
                        :min="1"
                        :max="3600"
                        hint="Default 300 (5 menit)."
                        :error="form.errors.signature_max_skew_ms"
                    />
                    <AppSwitch v-model="form.widget_enabled" class="min-w-0" field label="Widget Chat AI" />

                    <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                        <AppButton type="submit" :loading="form.processing" :disabled="form.processing" icon="save">
                            Simpan Pengaturan
                        </AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>