<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppSwitch from '../../Components/AppSwitch.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import { formatMarkdown } from '../../composables/useMarkdown';
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

// === RAG personas & uploaded documents ===
const ragEnabled = computed(() => props.orchestrator.configured === true);
const personas = ref([]);
const personasLoading = ref(false);
const personasError = ref('');
const docs = ref({ loading: false, items: [], error: '' });

const personaOptions = computed(() => {
    const all = [{ value: '', label: 'Semua persona', description: 'Tampilkan tanpa filter' }];
    const named = personas.value.map((p) => ({
        value: p.id,
        label: p.is_default ? `${p.name} (default)` : p.name,
        description: p.slug,
    }));
    return [...all, ...named];
});

const uploadPersonaOptions = computed(() => {
    const opts = personas.value.map((p) => ({
        value: p.id,
        label: p.is_default ? `${p.name} (default)` : p.name,
        description: p.slug,
    }));

    return [{ value: '', label: '— Tanpa persona (semua) —', description: 'Dokumen tersedia global' }, ...opts];
});

async function fetchPersonas() {
    if (!ragEnabled.value) {
        personas.value = [];
        return;
    }
    personasLoading.value = true;
    personasError.value = '';
    try {
        const res = await fetch('/admin/integrations/orchestrator/personas', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
            personasError.value = data.message || `HTTP ${res.status}`;
            personas.value = [];
            return;
        }
        personas.value = Array.isArray(data.personas) ? data.personas : [];
    } catch (e) {
        personasError.value = e?.message || 'Gagal memuat persona.';
        personas.value = [];
    } finally {
        personasLoading.value = false;
    }
}

async function fetchDocuments() {
    if (!ragEnabled.value) {
        docs.value = { loading: false, items: [], error: '' };
        return;
    }
    docs.value.loading = true;
    docs.value.error = '';
    try {
        const params = new URLSearchParams();
        if (uploadForm.persona_id) params.set('persona_id', uploadForm.persona_id);
        const url = '/admin/integrations/orchestrator/documents'
            + (params.toString() ? `?${params.toString()}` : '');
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) {
            docs.value = {
                loading: false,
                items: [],
                error: data.message || `HTTP ${res.status}`,
            };
            return;
        }
        docs.value = {
            loading: false,
            items: Array.isArray(data.items) ? data.items : [],
            error: '',
            tenant: data.tenant ?? null,
        };
    } catch (e) {
        docs.value = {
            loading: false,
            items: [],
            error: e?.message || 'Gagal memuat dokumen.',
        };
    }
}

// === Upload RAG ===
const uploadForm = useForm({
    title: '',
    persona_id: '',
    file: null,
});
const uploadDragOver = ref(false);
const uploadFileName = ref('');
const uploadResult = ref(null);

watch(() => uploadForm.persona_id, () => {
    // Refetch documents list when the persona filter changes.
    fetchDocuments();
});

function formatBytes(n) {
    if (!n) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB'];
    let v = n;
    let i = 0;
    while (v >= 1024 && i < u.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toFixed(1)} ${u[i]}`;
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('id-ID', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    } catch {
        return iso;
    }
}

onMounted(() => {
    fetchPersonas();
    fetchDocuments();
});

function onUploadFile(e) {
    const f = e.target.files?.[0] ?? null;
    setUploadFile(f);
}
function onUploadDrop(e) {
    e.preventDefault();
    uploadDragOver.value = false;
    const f = e.dataTransfer?.files?.[0] ?? null;
    setUploadFile(f);
}
function setUploadFile(f) {
    if (!f) return;
    const allowed = ['text/plain', 'text/markdown', 'text/html', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (!allowed.includes(f.type) && !['txt', 'md', 'html', 'pdf', 'docx'].includes(ext)) {
        uploadResult.value = { ok: false, message: 'Tipe file tidak didukung.' };
        return;
    }
    uploadForm.file = f;
    uploadFileName.value = f.name;
    uploadResult.value = null;
    if (!uploadForm.title) uploadForm.title = f.name.replace(/\.[^.]+$/, '');
}

async function submitUpload() {
    uploadResult.value = null;
    try {
        const response = await fetch('/admin/integrations/orchestrator/upload', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: (() => {
                const fd = new FormData();
                if (uploadForm.title) fd.append('title', uploadForm.title);
                if (uploadForm.persona_id) fd.append('persona_id', uploadForm.persona_id);
                fd.append('file', uploadForm.file);
                return fd;
            })(),
        });
        const data = await response.json();
        uploadResult.value = response.ok
            ? { ok: true, message: 'Dokumen di-ingest. Tunggu sebentar lalu cek di daftar di bawah.' }
            : { ok: false, message: data.message || `HTTP ${response.status}` };
        if (response.ok) {
            uploadFileName.value = '';
            uploadForm.file = null;
            uploadForm.title = '';
            // Refresh the list so the user sees their new file.
            fetchDocuments();
        }
    } catch (e) {
        uploadResult.value = { ok: false, message: e.message };
    }
}

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

function formatMessage(raw) {
    return formatMarkdown(raw);
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
            // First non-empty delta: push the assistant bubble with content
            // and hide the typing chip in the same reactive batch so Vue
            // commits a single render (no empty bubble + chip together).
            if (assistantMsg.id === null) {
                assistantMsg.content = delta;
                chatTyping.value = false;
                const ref = pushChat({ role: 'assistant', content: assistantMsg.content });
                assistantMsg.id = ref.id;
            } else {
                assistantMsg.content += delta;
                const target = chatMessages.value.find((m) => m.id === assistantMsg.id);
                if (target) target.content = assistantMsg.content;
            }
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
        const msg = data?.message || 'Terjadi kesalahan.';
        chatError.value = msg;
        if (assistantMsg.id === null) {
            assistantMsg.content = msg;
            assistantMsg.id = chatMessages.value.length + 1;
            pushChat({ role: 'error', content: msg });
        }
        return;
    }
}

async function sendChat() {
    const content = chatInput.value.trim();
    if (!content || chatBusy.value) return;
    chatInput.value = '';
    chatError.value = null;
    pushChat({ role: 'user', content });
    // Don't push an empty assistant bubble yet — show the typing chip until
    // the first text delta arrives, so the user never sees two placeholders
    // (empty bubble + typing chip) at the same time.
    const assistantMsg = { id: null, content: '' };
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
        if (!assistantMsg.id) {
            // Stream finished without any text — surface a fallback bubble
            // so the UI doesn't stay stuck on the typing chip.
            assistantMsg.id = chatMessages.value.length + 1;
            pushChat({ role: 'assistant', content: assistantMsg.content || 'Tidak ada respon dari orchestrator.' });
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            const msg = e?.message || 'Gagal mengirim pesan.';
            chatError.value = msg;
            if (assistantMsg.id === null) {
                assistantMsg.content = msg;
                assistantMsg.id = chatMessages.value.length + 1;
                pushChat({ role: 'error', content: msg });
            }
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

            <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
                <AppCard class="min-w-0">
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

            <AppCard class="flex flex-col self-start lg:sticky lg:top-20">
                <header class="mb-3 flex items-center gap-2 border-b border-outline-variant pb-3">
                    <AppIcon name="smart_toy" class="text-xl text-primary" />
                    <div>
                        <h2 class="text-base font-bold text-primary">Test Chat</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">Kirim pertanyaan ke orchestrator.</p>
                    </div>
                </header>
                <div ref="chatListEl" class="min-h-40 max-h-96 flex-1 space-y-2 overflow-y-auto rounded-lg bg-surface p-3">
                    <p v-if="!chatMessages.length" class="text-center text-xs text-on-surface-variant">Belum ada percakapan.</p>
                    <div
                        v-for="msg in chatMessages"
                        :key="msg.id"
                        class="flex"
                        :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed"
                            :class="msg.role === 'user'
                                ? 'rounded-br-sm bg-primary text-on-primary whitespace-pre-wrap'
                                : msg.role === 'error'
                                    ? 'rounded-bl-sm border border-error/40 bg-error-container text-error'
                                    : 'rounded-bl-sm border border-outline-variant bg-surface-container-lowest text-primary'"
                        >
                            <template v-if="msg.role === 'user' || msg.role === 'error'">{{ msg.content }}</template>
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <div v-else v-html="formatMessage(msg.content) || ''" />
                        </div>
                    </div>
                    <div
                        v-if="chatTyping"
                        class="flex justify-start"
                        :aria-label="chatTypingLabel"
                    >
                        <div class="flex max-w-[85%] items-center gap-2 rounded-2xl rounded-bl-sm border border-outline-variant bg-surface-container-lowest px-3 py-2">
                            <span class="flex items-center gap-1">
                                <span class="inline-block size-1.5 animate-bounce rounded-full bg-on-surface/40" style="animation-delay:0s" />
                                <span class="inline-block size-1.5 animate-bounce rounded-full bg-on-surface/40" style="animation-delay:0.15s" />
                                <span class="inline-block size-1.5 animate-bounce rounded-full bg-on-surface/40" style="animation-delay:0.3s" />
                            </span>
                            <span class="text-xs text-on-surface-variant">{{ chatTypingLabel }}</span>
                        </div>
                    </div>
                </div>
                <p v-if="chatError" class="mt-2 text-xs text-error">{{ chatError }}</p>
                <form class="mt-3 flex items-end gap-2" @submit.prevent="sendChat">
                    <textarea
                        v-model="chatInput"
                        rows="1"
                        placeholder="Tulis pertanyaan…"
                        class="min-h-10 max-h-32 flex-1 resize-none rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm leading-5 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-50"
                        :disabled="chatBusy"
                        @keydown.enter.exact.prevent="sendChat"
                    />
                    <button
                        v-if="chatBusy"
                        type="button"
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-secondary text-on-secondary"
                        aria-label="Batal"
                        @click="cancelChat"
                    >
                        <AppIcon name="close" />
                    </button>
                    <button
                        v-else
                        type="submit"
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary text-on-primary disabled:opacity-50"
                        :disabled="!chatInput.trim()"
                        aria-label="Kirim"
                    >
                        <AppIcon name="send" />
                    </button>
                </form>
            </AppCard>
            </div>

            <AppCard>
                <header class="mb-4">
                    <h2 class="text-lg font-bold text-primary">Upload Bahan RAG</h2>
                    <p class="mt-0.5 text-xs text-on-surface-variant">Kirim file ke orchestrator untuk di-chunk dan di-embed.</p>
                </header>
                <form class="space-y-4" @submit.prevent="submitUpload">
                    <AppInput
                        v-model="uploadForm.title"
                        label="Judul (opsional)"
                        placeholder="Mis. Buku Pedoman Koperasi 2024"
                        :error="uploadForm.errors.title"
                    />
                    <div>
                        <SmartSelect
                            v-model="uploadForm.persona_id"
                            label="Persona (opsional)"
                            :options="uploadPersonaOptions"
                            placeholder="— Pilih persona —"
                            :loading="personasLoading"
                            :disabled="!ragEnabled"
                            :error="uploadForm.errors.persona_id"
                            hint="Dokumen akan di-scope ke persona ini. Kosongkan untuk global."
                            :clearable="false"
                        />
                        <p v-if="personasError" class="mt-1 text-xs text-error">{{ personasError }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-bold uppercase tracking-wider text-primary">File</label>
                        <label
                            class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-lowest px-4 py-6 text-center transition-colors hover:border-primary"
                            :class="uploadDragOver ? 'border-primary bg-primary/5' : ''"
                            @dragover.prevent="uploadDragOver = true"
                            @dragleave.prevent="uploadDragOver = false"
                            @drop="onUploadDrop"
                        >
                            <AppIcon name="upload_file" class="text-2xl text-on-surface-variant" />
                            <p class="text-sm text-on-surface-variant">
                                <span class="font-semibold text-primary">Klik untuk pilih</span> atau drop file di sini
                            </p>
                            <p class="text-xs text-on-surface-variant">PDF / DOCX / MD / HTML / TXT — maks 20 MB</p>
                            <input type="file" class="hidden" accept=".pdf,.docx,.md,.markdown,.html,.htm,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown,text/html" @change="onUploadFile" />
                        </label>
                        <p v-if="uploadFileName" class="mt-2 text-xs text-on-surface-variant">
                            Dipilih: <span class="font-semibold">{{ uploadFileName }}</span>
                        </p>
                        <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-error">{{ uploadForm.errors.file }}</p>
                    </div>
                    <p v-if="uploadResult" class="text-xs" :class="uploadResult.ok ? 'text-success' : 'text-error'">{{ uploadResult.message }}</p>
                    <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                        <AppButton type="submit" :loading="uploadForm.processing" :disabled="uploadForm.processing || !uploadForm.file" icon="upload">
                            Upload &amp; Ingest
                        </AppButton>
                    </div>
                </form>
            </AppCard>

            <AppCard>
                <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Dokumen Terupload</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">
                            File yang sudah di-ingest ke RAG.
                            <span v-if="docs.tenant" class="font-mono">tenant {{ docs.tenant.slug }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <SmartSelect
                            v-model="uploadForm.persona_id"
                            :options="personaOptions"
                            placeholder="Semua persona"
                            :loading="personasLoading"
                            hide-label
                            class="min-w-44"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-outline-variant bg-surface px-3 py-1.5 text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container"
                            :disabled="docs.loading"
                            @click="fetchDocuments"
                        >
                            <AppIcon name="refresh" />
                            <span>{{ docs.loading ? 'Memuat…' : 'Refresh' }}</span>
                        </button>
                    </div>
                </header>

                <div v-if="!ragEnabled" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">
                    Konfigurasikan URL server & shared secret untuk melihat dokumen.
                </div>
                <div v-else-if="docs.loading" class="rounded-xl bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">
                    Memuat…
                </div>
                <div v-else-if="docs.error" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ docs.error }}
                </div>
                <div v-else-if="!docs.items.length" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">
                    Belum ada dokumen
                    <span v-if="uploadForm.persona_id"> untuk persona ini</span>.
                </div>
                <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Judul</th>
                                <th class="px-4 py-2 text-left font-semibold">Format</th>
                                <th class="px-4 py-2 text-right font-semibold">Ukuran</th>
                                <th class="px-4 py-2 text-right font-semibold">Chunks</th>
                                <th class="px-4 py-2 text-left font-semibold">Diupload</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant bg-surface">
                            <tr v-for="d in docs.items" :key="d.id" class="hover:bg-surface-container-lowest">
                                <td class="px-4 py-2">
                                    <p class="font-semibold text-primary">{{ d.title }}</p>
                                    <p v-if="d.preview" class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ d.preview }}</p>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="rounded bg-surface-container-lowest px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">
                                        {{ d.format || '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatBytes(d.content_length) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ d.chunks_count }}</td>
                                <td class="px-4 py-2 text-xs text-on-surface-variant">{{ formatDate(d.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="docs.items.length" class="mt-2 text-xs text-on-surface-variant">
                    Total: <span class="font-semibold">{{ docs.items.length }}</span> dokumen.
                </p>
            </AppCard>
        </div>
    </AdminLayout>
</template>