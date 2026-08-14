<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppEmptyState from '../../Components/AppEmptyState.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import { useConfirm } from '../../composables/useConfirm';
import AppModal from '../../Components/AppModal.vue';
import AppSwitch from '../../Components/AppSwitch.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import ArtifactCard from '../../Components/AssistantComponents/ArtifactCard.vue';
import ArtifactModal from '../../Components/AssistantComponents/ArtifactModal.vue';
import ActionButton from '../../Components/AssistantComponents/ActionButton.vue';
import PollCard from '../../Components/AssistantComponents/PollCard.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({
    active_gateway: { type: String, default: 'duitku' },
    xendit: { type: Object, default: () => ({ secret_key: '', public_key: '', has_secret_key: false, mode: 'sandbox', default_method: 'QRIS' }) },
    duitku: { type: Object, default: () => ({ merchant_code: '', has_api_key: false, mode: 'sandbox', default_method: 'VC' }) },
    tripay: { type: Object, default: () => ({ merchant_code: '', has_api_key: false, has_private_key: false, mode: 'sandbox', default_method: 'QRIS2' }) },
    orchestrator: { type: Object, required: true },
    personas: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

// === Tripay Tab ===
const tripayModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];

const tripayMethodOptions = [
    { value: 'QRIS2', label: 'QRIS (Semua Bank & E-Wallet)' },
    { value: 'BCAVA', label: 'BCA Virtual Account' },
    { value: 'BRIVA', label: 'BRI Virtual Account (BRIVA)' },
    { value: 'BNIVA', label: 'BNI Virtual Account' },
    { value: 'MANDIRIVA', label: 'Mandiri Virtual Account' },
    { value: 'PERMATAVA', label: 'Permata Virtual Account' },
    { value: 'CIMBVA', label: 'CIMB Niaga Virtual Account' },
    { value: 'BSIVA', label: 'BSI Virtual Account' },
    { value: 'DANAMONVA', label: 'Danamon Virtual Account' },
];

const tripayForm = useForm({
    merchant_code: props.tripay?.merchant_code || '',
    api_key: '',
    private_key: '',
    mode: props.tripay?.mode || 'sandbox',
    default_method: props.tripay?.default_method || 'QRIS2',
});

const submitTripay = () => {
    tripayForm.post(route('admin.integrations.tripay'), {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Tripay tersimpan'),
    });
};

const tripayTestResult = ref(null);
const tripayTesting = ref(false);

const testTripayConnection = async () => {
    tripayTesting.value = true;
    tripayTestResult.value = null;
    try {
        const data = await apiCall('/admin/integrations/tripay/test', { method: 'POST' });
        tripayTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        tripayTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        tripayTesting.value = false;
    }
};



const gatewayForm = useForm({
    gateway: props.active_gateway || 'duitku',
});

const setGateway = (gw) => {
    gatewayForm.gateway = gw;
    gatewayForm.post(route('admin.integrations.active-gateway'), {
        preserveScroll: true,
        onSuccess: () => showToast(`Payment Gateway utama diubah ke ${gw.toUpperCase()}`),
    });
};


// === Xendit Tab ===
const xenditModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];

const xenditMethodOptions = [
    { value: 'QRIS', label: 'QRIS Xendit (Semua Bank & E-Wallet)' },
    { value: 'BCA', label: 'BCA Virtual Account' },
    { value: 'BRI', label: 'BRI Virtual Account' },
    { value: 'BNI', label: 'BNI Virtual Account' },
    { value: 'MANDIRI', label: 'Mandiri Virtual Account' },
    { value: 'PERMATA', label: 'Permata Virtual Account' },
    { value: 'CREDIT_CARD', label: 'Kartu Kredit / Debit (Visa/Mastercard)' },
];

const xenditForm = useForm({
    secret_key: '',
    public_key: props.xendit?.public_key || '',
    callback_token: '',
    mode: props.xendit?.mode || 'sandbox',
    default_method: props.xendit?.default_method || 'QRIS',
});

const submitXendit = () => {
    xenditForm.post(route('admin.integrations.xendit'), {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Xendit tersimpan'),
    });
};

const xenditTestResult = ref(null);
const xenditTesting = ref(false);

const testXenditConnection = async () => {
    xenditTesting.value = true;
    xenditTestResult.value = null;
    try {
        const data = await apiCall('/admin/integrations/xendit/test', { method: 'POST' });
        xenditTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        xenditTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        xenditTesting.value = false;
    }
};

// === Duitku Tab ===
const duitkuModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];

const duitkuMethodOptions = [
    { value: 'SP', label: 'ShopeePay / QRIS Duitku' },
    { value: 'BC', label: 'BCA Virtual Account' },
    { value: 'BR', label: 'BRI Virtual Account' },
    { value: 'BN', label: 'BNI Virtual Account' },
    { value: 'M2', label: 'Mandiri Virtual Account' },
    { value: 'VA', label: 'Permata Virtual Account' },
    { value: 'B1', label: 'CIMB Niaga Virtual Account' },
];

const duitkuForm = useForm({
    merchant_code: props.duitku?.merchant_code || '',
    api_key: '',
    mode: props.duitku?.mode || 'sandbox',
    default_method: props.duitku?.default_method || 'VC',
});

const submitDuitku = () => {
    duitkuForm.post(route('admin.integrations.duitku'), {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Duitku tersimpan'),
    });
};

const duitkuTestResult = ref(null);
const duitkuTesting = ref(false);

const testDuitkuConnection = async () => {
    duitkuTesting.value = true;
    duitkuTestResult.value = null;
    try {
        const data = await apiCall('/admin/integrations/duitku/test', { method: 'POST' });
        duitkuTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        duitkuTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        duitkuTesting.value = false;
    }
};

// === Tab state ===
const activeTab = ref('overview');

// === Toast ===
const toast = ref(null);
let toastTimer = null;

const { confirm: askConfirm } = useConfirm();
function showToast(message, tone = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { message, tone };
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}

// === CSRF ===
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// === Shared fetch helper ===
async function apiCall(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
        credentials: 'same-origin',
        ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
        throw new Error(data.message || `HTTP ${response.status}`);
    }
    return data;
}

// =====================================================================
// OVERVIEW TAB
// =====================================================================

const testResult = ref(null);
const testLoading = ref(false);
async function testConnection() {
    testLoading.value = true;
    testResult.value = null;
    try {
        testResult.value = await apiCall('/admin/integrations/orchestrator/test', { method: 'POST' });
    } catch (e) {
        testResult.value = { success: false, status: 'error', message: e.message };
    } finally {
        testLoading.value = false;
    }
}

// =====================================================================
// PERSONAS TAB
// =====================================================================

const personas = ref([...props.personas]);
const tools = ref([...props.tools]);
watch(() => props.personas, (v) => { personas.value = [...v]; });

const personaModal = ref({ open: false, mode: 'create', data: null });
function openCreatePersona() {
    personaModal.value = {
        open: true,
        mode: 'create',
        data: {
            name: '', slug: '', system_prompt: '', is_default: false, is_active: true, tool_ids: [],
        },
    };
}
function openEditPersona(persona) {
    personaModal.value = {
        open: true,
        mode: 'edit',
        data: {
            id: persona.id,
            name: persona.name,
            slug: persona.slug,
            system_prompt: persona.system_prompt,
            is_default: persona.is_default,
            is_active: persona.is_active,
            tool_ids: persona.tools.map((t) => t.id),
        },
    };
}
async function submitPersona() {
    const payload = personaModal.value.data;
    const url = personaModal.value.mode === 'create'
        ? '/admin/integrations/orchestrator/personas/store'
        : `/admin/integrations/orchestrator/personas/${payload.id}`;
    const method = personaModal.value.mode === 'create' ? 'POST' : 'PUT';
    try {
        const data = await apiCall(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        personaModal.value.open = false;
        await refreshPersonas();
        showToast(data.message || 'Persona berhasil disimpan.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function deletePersona(persona) {
    const ok = await askConfirm({
        title: 'Hapus Persona',
        message: `Hapus persona "${persona.name}"? Tindakan ini tidak dapat dibatalkan.`,
    });
    if (!ok) return;
    try {
        const data = await apiCall(`/admin/integrations/orchestrator/personas/${persona.id}`, { method: 'DELETE' });
        await refreshPersonas();
        showToast(data.message || 'Persona berhasil dihapus.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function togglePersona(persona, field) {
    try {
        const data = await apiCall(`/admin/integrations/orchestrator/personas/${persona.id}/toggle`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ field }),
        });
        await refreshPersonas();
        showToast(data.message || 'Status diperbarui.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function refreshPersonas() {
    try {
        const data = await apiCall('/admin/integrations/orchestrator/personas', { method: 'GET' });
        personas.value = data.personas;
    } catch (e) {
        showToast(e.message, 'error');
    }
}

const personaToolOptions = computed(() => tools.value.map((t) => ({
    value: t.id, label: t.name, description: t.description?.slice(0, 60) ?? '',
})));

// =====================================================================
// TOOLS TAB
// =====================================================================

const toolSyncing = ref(false);
async function syncTools() {
    toolSyncing.value = true;
    try {
        const data = await apiCall('/admin/integrations/orchestrator/tools/sync', { method: 'POST' });
        showToast(data.message || 'Sinkronisasi selesai.');
        await refreshTools();
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        toolSyncing.value = false;
    }
}
async function toggleTool(tool, field) {
    try {
        const payload = { [field]: ! tool[field] };
        await apiCall(`/admin/integrations/orchestrator/tools/${tool.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        await refreshTools();
        showToast('Tool diperbarui.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function refreshTools() {
    try {
        const data = await apiCall('/admin/integrations/orchestrator/tools', { method: 'GET' });
        tools.value = data.tools;
    } catch (e) {
        showToast(e.message, 'error');
    }
}

const toolModal = ref({ open: false, tool: null });
function openToolDetail(tool) {
    toolModal.value = { open: true, tool };
}

// =====================================================================
// KNOWLEDGE BASE TAB
// =====================================================================

const docs = ref({ loading: false, items: [], error: '' });
const docModal = ref({ open: false, doc: null });
const uploadForm = reactive({ title: '', persona_id: '', file: null });
const uploadDragOver = ref(false);
const uploadFileName = ref('');
const uploadResult = ref(null);
const uploadLoading = ref(false);

async function fetchDocuments() {
    docs.value.loading = true;
    docs.value.error = '';
    try {
        const params = new URLSearchParams();
        if (uploadForm.persona_id) params.set('persona_id', uploadForm.persona_id);
        const url = '/admin/integrations/orchestrator/documents'
            + (params.toString() ? `?${params.toString()}` : '');
        const data = await apiCall(url, { method: 'GET' });
        docs.value = { loading: false, items: data.items, error: '' };
    } catch (e) {
        docs.value = { loading: false, items: [], error: e.message };
    }
}

const kbPersonaOptions = computed(() => [
    { value: '', label: '— Semua persona —', description: 'Tampilkan semua' },
    ...personas.value.map((p) => ({ value: p.id, label: p.name, description: p.slug })),
]);
const uploadPersonaOptions = computed(() => [
    { value: '', label: '— Tanpa persona (global) —', description: 'Dokumen tersedia global' },
    ...personas.value.map((p) => ({ value: p.id, label: p.name, description: p.slug })),
]);

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
    if (!uploadForm.file) return;
    uploadResult.value = null;
    uploadLoading.value = true;
    try {
        const fd = new FormData();
        if (uploadForm.title) fd.append('title', uploadForm.title);
        if (uploadForm.persona_id) fd.append('persona_id', uploadForm.persona_id);
        fd.append('file', uploadForm.file);
        const data = await apiCall('/admin/integrations/orchestrator/upload', {
            method: 'POST',
            body: fd,
        });
        uploadResult.value = { ok: true, message: `Berhasil ingest: ${data.document.title}` };
        uploadFileName.value = '';
        uploadForm.file = null;
        uploadForm.title = '';
        await fetchDocuments();
    } catch (e) {
        uploadResult.value = { ok: false, message: e.message };
    } finally {
        uploadLoading.value = false;
    }
}

async function viewDocument(doc) {
    try {
        const data = await apiCall(`/admin/integrations/orchestrator/documents/${doc.id}`, { method: 'GET' });
        docModal.value = { open: true, doc: data.document };
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function deleteDocument(doc) {
    const ok = await askConfirm({
        title: 'Hapus Dokumen',
        message: `Hapus dokumen "${doc.title}"? Knowledge source juga akan dihapus jika kosong.`,
    });
    if (!ok) return;
    try {
        const data = await apiCall(`/admin/integrations/orchestrator/documents/${doc.id}`, { method: 'DELETE' });
        showToast(data.message || 'Dokumen dihapus.');
        await fetchDocuments();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// =====================================================================
// TEST CHAT TAB
// =====================================================================

const chatMessages = ref([]);
const chatInput = ref('');
const chatBusy = ref(false);
const chatTyping = ref(false);
const chatTypingLabel = ref('Sedang mengetik');
const chatPersonaId = ref('');
const chatListEl = ref(null);
let chatAbort = null;
let chatSeq = 0;

const chatPersonaOptions = computed(() => [
    { value: '', label: '— Default persona —', description: 'Gunakan persona default sistem' },
    ...personas.value.filter((p) => p.is_active).map((p) => ({ value: p.id, label: p.name, description: p.slug })),
]);

const submittedComponentsTest = reactive(new Map());
const activeArtifactTest = ref(null);
const treeCacheTest = new WeakMap();
function blocksFor(msg) {
    if (!msg || !msg.content) return [];
    let tree = treeCacheTest.get(msg);
    if (!tree) {
        tree = parseMarkdownTree(msg.content);
        treeCacheTest.set(msg, tree);
    }
    return tree;
}
function openArtifact(block) {
    activeArtifactTest.value = block;
}
function onComponentSubmitTest(block, payload) {
    if (submittedComponentsTest.has(block.id)) return;
    submittedComponentsTest.set(block.id, payload);
    const text = payload === '__skip__' ? '(lewati)' : String(payload);
    pushChat({ role: 'user', content: text });
    sendChatTest(text);
}

function pushChat(msg) {
    const id = ++chatSeq;
    chatMessages.value.push({ id, ...msg });
    nextTick(scrollChatBottom);
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
    if (!response.ok) throw new Error(`HTTP ${response.status}: ${await response.text()}`);
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
            nextTick(scrollChatBottom);
        }
        return;
    }
    if (event === 'tool_use') {
        chatTyping.value = true;
        chatTypingLabel.value = 'Mencari data…';
        nextTick(scrollChatBottom);
        return;
    }
    if (event === 'tool_result') {
        chatTyping.value = true;
        chatTypingLabel.value = 'Menyusun jawaban…';
        nextTick(scrollChatBottom);
        return;
    }
    if (event === 'confirmation_required') {
        chatTyping.value = false;
        const summary = data?.summary ?? 'Aksi perlu konfirmasi.';
        pushChat({ role: 'assistant', content: `⚠️ **Konfirmasi diperlukan:** ${summary}` });
        return;
    }
    if (event === 'error') {
        chatTyping.value = false;
        const msg = data?.message || 'Terjadi kesalahan.';
        if (assistantMsg.id === null) {
            assistantMsg.content = msg;
            assistantMsg.id = chatMessages.value.length + 1;
            pushChat({ role: 'error', content: msg });
        }
        return;
    }
}

async function sendChatTest(content) {
    const assistantMsg = { id: null, content: '' };
    chatBusy.value = true;
    chatTyping.value = true;
    chatTypingLabel.value = 'Sedang mengetik';
    chatAbort = new AbortController();
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
            body: JSON.stringify({
                message: content,
                persona_slug: chatPersonaId.value ? personas.value.find((p) => p.id === chatPersonaId.value)?.slug : null,
            }),
            signal: chatAbort.signal,
        });
        await readSseStream(res, (event, data) => handleChatEvent(event, data, assistantMsg));
        if (!assistantMsg.id) {
            assistantMsg.id = chatMessages.value.length + 1;
            pushChat({ role: 'assistant', content: assistantMsg.content || 'Tidak ada respon.' });
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            const msg = e?.message || 'Gagal mengirim pesan.';
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
        nextTick(scrollChatBottom);
    }
}

async function scrollChatBottom() {
    await nextTick();
    if (chatListEl.value) chatListEl.value.scrollTop = chatListEl.value.scrollHeight;
}

async function sendChat() {
    const content = chatInput.value.trim();
    if (!content || chatBusy.value) return;
    chatInput.value = '';
    pushChat({ role: 'user', content });
    await sendChatTest(content);
}

function cancelChat() {
    chatAbort?.abort();
    chatBusy.value = false;
    chatTyping.value = false;
}

function clearChat() {
    chatMessages.value = [];
    submittedComponentsTest.clear();
}

// =====================================================================
// ACTIVITY TAB
// =====================================================================

const conversations = ref({ loading: false, items: [], error: '' });
const auditLogs = ref({ loading: false, items: [], error: '' });

async function fetchConversations() {
    conversations.value.loading = true;
    try {
        const data = await apiCall('/admin/integrations/orchestrator/conversations', { method: 'GET' });
        conversations.value = { loading: false, items: data.conversations, error: '' };
    } catch (e) {
        conversations.value = { loading: false, items: [], error: e.message };
    }
}
async function fetchAuditLogs() {
    auditLogs.value.loading = true;
    try {
        const data = await apiCall('/admin/integrations/orchestrator/audit-logs', { method: 'GET' });
        auditLogs.value = { loading: false, items: data.logs, error: '' };
    } catch (e) {
        auditLogs.value = { loading: false, items: [], error: e.message };
    }
}

// =====================================================================
// Lifecycle / watchers
// =====================================================================

watch(activeTab, (tab) => {
    if (tab === 'knowledge') fetchDocuments();
    if (tab === 'activity') { fetchConversations(); fetchAuditLogs(); }
    if (tab === 'chat') nextTick(scrollChatBottom);
});

watch(() => uploadForm.persona_id, () => {
    if (activeTab.value === 'knowledge') fetchDocuments();
});

function formatBytes(n) {
    if (!n) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB'];
    let v = n;
    let i = 0;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(1)} ${u[i]}`;
}
function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('id-ID', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    } catch { return iso; }
}
function formatTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch { return iso; }
}

const tabs = [
    { id: 'overview', label: 'Overview', icon: 'dashboard' },
    { id: 'tripay', label: 'Tripay Gateway', icon: 'payments' },
    { id: 'duitku', label: 'Duitku Gateway', icon: 'account_balance_wallet' },
    { id: 'xendit', label: 'Xendit Gateway', icon: 'credit_card' },
    { id: 'personas', label: 'Personas', icon: 'person' },
    { id: 'tools', label: 'Tools', icon: 'build' },
    { id: 'knowledge', label: 'Knowledge Base', icon: 'library_books' },
    { id: 'chat', label: 'Test Chat', icon: 'chat' },
    { id: 'activity', label: 'Activity', icon: 'history' },
];

onMounted(() => {
    fetchDocuments();
});
onBeforeUnmount(() => {
    if (toastTimer) clearTimeout(toastTimer);
    chatAbort?.abort();
});
</script>

<template>
    <Head title="Pengaturan Integrasi & AI Assistant" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">Pengaturan Integrasi & AI Assistant</h1>
                    <p class="mt-1 text-on-surface-variant">Kelola Payment Gateway (Duitku, Tripay, Xendit), AI Assistant, Personas, Tools, dan Knowledge Base.</p>
                </div>
                <div class="flex items-center gap-3">
                    <AppBadge tone="success">In-process</AppBadge>
                </div>
            </header>

            <!-- Flash success -->
            <AppCard v-if="flash">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-secondary-container text-secondary">✓</div>
                    <p class="font-bold text-primary">{{ flash.message }}</p>
                </div>
            </AppCard>

            <!-- Tabs -->
            <nav class="flex flex-wrap gap-1 rounded-xl border border-outline-variant bg-surface-container-lowest p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors"
                    :class="activeTab === tab.id
                        ? 'bg-primary text-on-primary shadow-sm'
                        : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary'"
                    @click="activeTab = tab.id"
                >
                    <AppIcon :name="tab.icon" class="text-lg" />
                    {{ tab.label }}
                </button>
            </nav>

            <!-- ============= OVERVIEW TAB ============= -->
            <div v-if="activeTab === 'overview'" class="space-y-6">

                <!-- Payment Gateway Switcher Card -->
                <AppCard class="border-2 border-primary/20">
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-primary">Payment Gateway Utama (In-App Billing)</h3>
                            <p class="text-xs text-on-surface-variant">Pilih payment gateway aktif yang digunakan untuk penagihan invoice sistem.</p>
                        </div>
                        <AppBadge tone="success">Aktif: {{ (props.active_gateway || 'duitku').toUpperCase() }}</AppBadge>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div 
                            class="flex cursor-pointer items-center justify-between rounded-xl border-2 p-4 transition"
                            :class="props.active_gateway === 'duitku' ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/40 bg-surface'"
                            @click="setGateway('duitku')"
                        >
                            <div>
                                <h4 class="font-bold text-sm text-primary">Duitku Payment Gateway</h4>
                                <p class="text-xs text-on-surface-variant">Metode: Virtual Account, QRIS (ShopeePay/Duitku), Credit Card, dll.</p>
                            </div>
                            <AppBadge :tone="props.active_gateway === 'duitku' ? 'primary' : 'neutral'">
                                {{ props.active_gateway === 'duitku' ? 'Aktif' : 'Pilih Duitku' }}
                            </AppBadge>
                        </div>
                        <div 
                            class="flex cursor-pointer items-center justify-between rounded-xl border-2 p-4 transition"
                            :class="props.active_gateway === 'tripay' ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/40 bg-surface'"
                            @click="setGateway('tripay')"
                        >
                            <div>
                                <h4 class="font-bold text-sm text-primary">Tripay Payment Gateway</h4>
                                <p class="text-xs text-on-surface-variant">Metode: QRIS, BCA VA, BRI VA, Mandiri VA, BNI VA, dll.</p>
                            </div>
                            <AppBadge :tone="props.active_gateway === 'tripay' ? 'primary' : 'neutral'">
                                {{ props.active_gateway === 'tripay' ? 'Aktif' : 'Pilih Tripay' }}
                            </AppBadge>
                        </div>
                        <div 
                            class="flex cursor-pointer items-center justify-between rounded-xl border-2 p-4 transition"
                            :class="props.active_gateway === 'xendit' ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/40 bg-surface'"
                            @click="setGateway('xendit')"
                        >
                            <div>
                                <h4 class="font-bold text-sm text-primary">Xendit Payment Gateway</h4>
                                <p class="text-xs text-on-surface-variant">Metode: QRIS, Virtual Account (BCA, BRI, BNI, Mandiri, Permata), Kartu Kredit, dll.</p>
                            </div>
                            <AppBadge :tone="props.active_gateway === 'xendit' ? 'primary' : 'neutral'">
                                {{ props.active_gateway === 'xendit' ? 'Aktif' : 'Pilih Xendit' }}
                            </AppBadge>
                        </div>
                    </div>
                </AppCard>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <AppCard>
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Personas</p>
                                <p class="mt-2 text-3xl font-bold text-primary">{{ props.stats.active_personas ?? 0 }}<span class="ml-1 text-base font-normal text-on-surface-variant">/ {{ props.stats.total_personas ?? 0 }}</span></p>
                            </div>
                            <div class="grid size-10 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="person" />
                            </div>
                        </div>
                    </AppCard>
                    <AppCard>
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tools</p>
                                <p class="mt-2 text-3xl font-bold text-primary">{{ props.stats.active_tools ?? 0 }}<span class="ml-1 text-base font-normal text-on-surface-variant">/ {{ props.stats.total_tools ?? 0 }}</span></p>
                            </div>
                            <div class="grid size-10 place-items-center rounded-xl bg-secondary-container text-secondary">
                                <AppIcon name="build" />
                            </div>
                        </div>
                    </AppCard>
                    <AppCard>
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Documents</p>
                                <p class="mt-2 text-3xl font-bold text-primary">{{ props.stats.total_documents ?? 0 }}</p>
                            </div>
                            <div class="grid size-10 place-items-center rounded-xl bg-tertiary-container text-on-tertiary-container">
                                <AppIcon name="library_books" />
                            </div>
                        </div>
                    </AppCard>
                    <AppCard>
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Conversations</p>
                                <p class="mt-2 text-3xl font-bold text-primary">{{ props.stats.total_conversations ?? 0 }}</p>
                            </div>
                            <div class="grid size-10 place-items-center rounded-xl bg-error-container text-error">
                                <AppIcon name="forum" />
                            </div>
                        </div>
                    </AppCard>
                </div>

                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Health Check</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Verifikasi koneksi LLM via ModelGateway langsung.</p>
                        </div>
                        <AppButton
                            icon="network_check"
                            :loading="testLoading"
                            @click="testConnection"
                        >Test Connection</AppButton>
                    </header>
                    <div v-if="testResult" class="rounded-lg border p-4"
                        :class="testResult.success ? 'border-green-300 bg-green-50 text-green-900' : 'border-amber-300 bg-amber-50 text-amber-900'">
                        <p class="font-semibold">{{ testResult.message }}</p>
                        <p v-if="testResult.latency_ms !== undefined" class="mt-1 text-xs">
                            Status: <span class="font-mono">{{ testResult.status }}</span> · Latency: {{ testResult.latency_ms }} ms
                        </p>
                    </div>
                </AppCard>

                <AppCard>
                    <h2 class="mb-3 text-lg font-bold text-primary">Arsitektur</h2>
                    <div class="grid gap-3 text-sm text-on-surface-variant sm:grid-cols-2">
                        <div class="flex items-start gap-2">
                            <AppIcon name="check_circle" class="mt-0.5 text-lg text-secondary" />
                            <span><code class="rounded bg-surface-container px-1 py-0.5 text-xs">enpii/assistant</code> package ter-install via composer path lokal</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <AppIcon name="check_circle" class="mt-0.5 text-lg text-secondary" />
                            <span>Tool dispatch via <code class="rounded bg-surface-container px-1 py-0.5 text-xs">ToolHandler</code> interface — tanpa HTTP hop</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <AppIcon name="check_circle" class="mt-0.5 text-lg text-secondary" />
                            <span>Single-tenant: identity via host app <code class="rounded bg-surface-container px-1 py-0.5 text-xs">TenantContext</code></span>
                        </div>
                        <div class="flex items-start gap-2">
                            <AppIcon name="check_circle" class="mt-0.5 text-lg text-secondary" />
                            <span>RAG: hybrid search (BM25 + embedding), document chunking, pgvector optional</span>
                        </div>
                    </div>
                </AppCard>
            </div>

                        
            <!-- ============= DUITKU TAB ============= -->
            <div v-else-if="activeTab === 'duitku'" class="space-y-6">
                <AppCard>
                    <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Kredensial & Pengaturan Duitku Payment Gateway</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">
                                Kelola Merchant Code dan API Key Duitku secara terpusat dari Superadmin dengan fallback otomatis ke file .env.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <AppBadge :tone="props.duitku?.has_api_key && props.duitku?.merchant_code ? 'success' : 'warning'">
                                {{ props.duitku?.has_api_key && props.duitku?.merchant_code ? 'Kredensial Aktif' : 'Kredensial Belum Lengkap' }}
                            </AppBadge>
                            <AppBadge tone="neutral">Mode: {{ (props.duitku?.mode || 'sandbox').toUpperCase() }}</AppBadge>
                        </div>
                    </header>

                    <form @submit.prevent="submitDuitku" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <SmartSelect
                                    v-model="duitkuForm.mode"
                                    label="Mode Lingkungan (Environment)"
                                    :options="duitkuModeOptions"
                                    :error="duitkuForm.errors.mode"
                                    required
                                />
                            </div>

                            <div>
                                <AppInput v-model="duitkuForm.merchant_code" label="Merchant Code" placeholder="Contoh: D12345" :error="duitkuForm.errors.merchant_code" required />
                            </div>

                            <div class="sm:col-span-2">
                                <AppInput v-model="duitkuForm.api_key" label="API Key (Secret)" type="password" :placeholder="props.duitku?.has_api_key ? '•••••••••••••••• (Tersimpan di database - isi jika ingin diubah)' : 'Masukkan API Key Duitku'" :error="duitkuForm.errors.api_key" />
                            </div>

                            <div class="sm:col-span-2">
                                <SmartSelect
                                    v-model="duitkuForm.default_method"
                                    label="Metode Pembayaran Default (In-App Billing)"
                                    :options="duitkuMethodOptions"
                                    :error="duitkuForm.errors.default_method"
                                    required
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-outline-variant pt-4">
                            <AppButton type="button" variant="secondary" :disabled="duitkuTesting" @click="testDuitkuConnection">
                                <AppIcon name="network_check" class="mr-1" />
                                <span>{{ duitkuTesting ? 'Menguji Koneksi...' : 'Uji Koneksi Duitku API' }}</span>
                            </AppButton>

                            <AppButton type="submit" variant="primary" :disabled="duitkuForm.processing">
                                Simpan Kredensial Duitku
                            </AppButton>
                        </div>
                    </form>

                    <!-- Test Result Output -->
                    <div v-if="duitkuTestResult" class="mt-6 rounded-xl border p-4" :class="duitkuTestResult.ok ? 'border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-rose-200 bg-rose-50/50 dark:bg-rose-950/20'">
                        <h4 class="font-bold text-sm" :class="duitkuTestResult.ok ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-200'">
                            {{ duitkuTestResult.ok ? '✓ ' : '✕ ' }}{{ duitkuTestResult.message }}
                        </h4>
                        <div v-if="duitkuTestResult.channels && duitkuTestResult.channels.length" class="mt-3">
                            <span class="text-xs font-semibold text-on-surface-variant">Saluran Pembayaran Aktif:</span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span v-for="ch in duitkuTestResult.channels" :key="ch.code" class="inline-flex items-center gap-1 rounded-md bg-surface border border-outline-variant px-2.5 py-1 text-xs font-medium text-primary">
                                    <img v-if="ch.icon_url" :src="ch.icon_url" :alt="ch.name" class="h-3.5 w-auto" />
                                    {{ ch.name }} ({{ ch.code }})
                                </span>
                            </div>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- ============= TRIPAY TAB ============= -->
            <div v-else-if="activeTab === 'tripay'" class="space-y-6">
                <AppCard>
                    <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Kredensial & Pengaturan Tripay Payment Gateway</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">
                                Kelola Merchant Code, API Key, dan Private Key Tripay secara terpusat dari Superadmin tanpa perlu mengubah file .env.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <AppBadge :tone="props.tripay?.has_api_key && props.tripay?.has_private_key ? 'success' : 'warning'">
                                {{ props.tripay?.has_api_key && props.tripay?.has_private_key ? 'Kredensial Aktif' : 'Kredensial Belum Lengkap' }}
                            </AppBadge>
                            <AppBadge tone="neutral">Mode: {{ (props.tripay?.mode || 'sandbox').toUpperCase() }}</AppBadge>
                        </div>
                    </header>

                    <form @submit.prevent="submitTripay" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <SmartSelect
                                    v-model="tripayForm.mode"
                                    label="Mode Lingkungan (Environment)"
                                    :options="tripayModeOptions"
                                    :error="tripayForm.errors.mode"
                                    required
                                />
                            </div>

                            <div>
                                <AppInput v-model="tripayForm.merchant_code" label="Merchant Code" placeholder="Contoh: T12345" :error="tripayForm.errors.merchant_code" required />
                            </div>

                            <div>
                                <AppInput v-model="tripayForm.api_key" label="API Key (Secret)" type="password" :placeholder="props.tripay?.has_api_key ? '���������������� (Tersimpan di database - isi jika ingin diubah)' : 'Masukkan API Key Tripay'" :error="tripayForm.errors.api_key" />
                            </div>

                            <div>
                                <AppInput v-model="tripayForm.private_key" label="Private Key (Secret Signature)" type="password" :placeholder="props.tripay?.has_private_key ? '���������������� (Tersimpan di database - isi jika ingin diubah)' : 'Masukkan Private Key Tripay'" :error="tripayForm.errors.private_key" />
                            </div>

                            <div class="sm:col-span-2">
                                <SmartSelect
                                    v-model="tripayForm.default_method"
                                    label="Metode Pembayaran Default (In-App Billing)"
                                    :options="tripayMethodOptions"
                                    :error="tripayForm.errors.default_method"
                                    required
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-outline-variant pt-4">
                            <AppButton type="button" variant="secondary" :disabled="tripayTesting" @click="testTripayConnection">
                                <AppIcon name="network_check" class="mr-1" />
                                <span>{{ tripayTesting ? 'Menguji Koneksi...' : 'Uji Koneksi Tripay API' }}</span>
                            </AppButton>

                            <AppButton type="submit" variant="primary" :disabled="tripayForm.processing">
                                Simpan Kredensial Tripay
                            </AppButton>
                        </div>
                    </form>

                    <!-- Test Result Output -->
                    <div v-if="tripayTestResult" class="mt-6 rounded-xl border p-4" :class="tripayTestResult.ok ? 'border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-rose-200 bg-rose-50/50 dark:bg-rose-950/20'">
                        <h4 class="font-bold text-sm" :class="tripayTestResult.ok ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-200'">
                            {{ tripayTestResult.ok ? '? ' : '? ' }}{{ tripayTestResult.message }}
                        </h4>
                        <div v-if="tripayTestResult.channels && tripayTestResult.channels.length" class="mt-3">
                            <span class="text-xs font-semibold text-on-surface-variant">Saluran Pembayaran Aktif:</span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span v-for="ch in tripayTestResult.channels" :key="ch.code" class="inline-flex items-center gap-1 rounded-md bg-surface border border-outline-variant px-2.5 py-1 text-xs font-medium text-primary">
                                    <img v-if="ch.icon_url" :src="ch.icon_url" :alt="ch.name" class="h-3.5 w-auto" />
                                    {{ ch.name }} ({{ ch.code }})
                                </span>
                            </div>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- ============= XENDIT TAB ============= -->
            <div v-else-if="activeTab === 'xendit'" class="space-y-6">
                <AppCard>
                    <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Kredensial & Pengaturan Xendit Payment Gateway</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">
                                Kelola Secret Key, Public Key, dan Verification Token Xendit secara terpusat dari Superadmin.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <AppBadge :tone="props.xendit?.has_secret_key ? 'success' : 'warning'">
                                {{ props.xendit?.has_secret_key ? 'Kredensial Aktif' : 'Kredensial Belum Lengkap' }}
                            </AppBadge>
                            <AppBadge tone="neutral">Mode: {{ (props.xendit?.mode || 'sandbox').toUpperCase() }}</AppBadge>
                        </div>
                    </header>

                    <form @submit.prevent="submitXendit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <SmartSelect
                                    v-model="xenditForm.mode"
                                    label="Mode Lingkungan (Environment)"
                                    :options="xenditModeOptions"
                                    :error="xenditForm.errors.mode"
                                    required
                                />
                            </div>

                            <div>
                                <AppInput v-model="xenditForm.public_key" label="Public Key (Optional)" placeholder="xnd_public_..." :error="xenditForm.errors.public_key" />
                            </div>

                            <div class="sm:col-span-2">
                                <AppInput v-model="xenditForm.secret_key" label="Secret Key (API Key)" type="password" :placeholder="props.xendit?.has_secret_key ? '•••••••••••••••• (Tersimpan di database - isi jika ingin diubah)' : 'xnd_development_... / xnd_production_...'" :error="xenditForm.errors.secret_key" />
                            </div>

                            <div class="sm:col-span-2">
                                <AppInput v-model="xenditForm.callback_token" label="Webhook Verification Token (x-callback-token)" type="password" placeholder="Masukkan verification token webhook Xendit" :error="xenditForm.errors.callback_token" />
                            </div>

                            <div class="sm:col-span-2">
                                <SmartSelect
                                    v-model="xenditForm.default_method"
                                    label="Metode Pembayaran Default (In-App Billing)"
                                    :options="xenditMethodOptions"
                                    :error="xenditForm.errors.default_method"
                                    required
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-outline-variant pt-4">
                            <AppButton type="button" variant="secondary" :disabled="xenditTesting" @click="testXenditConnection">
                                <AppIcon name="network_check" class="mr-1" />
                                <span>{{ xenditTesting ? 'Menguji Koneksi...' : 'Uji Koneksi Xendit API' }}</span>
                            </AppButton>

                            <AppButton type="submit" variant="primary" :disabled="xenditForm.processing">
                                Simpan Kredensial Xendit
                            </AppButton>
                        </div>
                    </form>

                    <!-- Test Result Output -->
                    <div v-if="xenditTestResult" class="mt-6 rounded-xl border p-4" :class="xenditTestResult.ok ? 'border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-rose-200 bg-rose-50/50 dark:bg-rose-950/20'">
                        <h4 class="font-bold text-sm" :class="xenditTestResult.ok ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-200'">
                            {{ xenditTestResult.ok ? '✓ ' : '✕ ' }}{{ xenditTestResult.message }}
                        </h4>
                        <div v-if="xenditTestResult.channels && xenditTestResult.channels.length" class="mt-3">
                            <span class="text-xs font-semibold text-on-surface-variant">Saluran Pembayaran Aktif:</span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span v-for="ch in xenditTestResult.channels" :key="ch.code" class="inline-flex items-center gap-1 rounded-md bg-surface border border-outline-variant px-2.5 py-1 text-xs font-medium text-primary">
                                    <img v-if="ch.icon_url" :src="ch.icon_url" :alt="ch.name" class="h-3.5 w-auto" />
                                    {{ ch.name }} ({{ ch.code }})
                                </span>
                            </div>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- ============= PERSONAS TAB ============= -->
            <div v-else-if="activeTab === 'personas'" class="space-y-6">
                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Daftar Persona</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">System prompt + tool scope per persona.</p>
                        </div>
                        <AppButton icon="add" @click="openCreatePersona">Persona Baru</AppButton>
                    </header>

                    <div v-if="personas.length === 0" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-10 text-center">
                        <AppIcon name="person" class="text-4xl text-on-surface-variant" />
                        <p class="mt-2 text-sm text-on-surface-variant">Belum ada persona. Buat satu untuk mulai.</p>
                    </div>
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Persona</th>
                                    <th class="px-4 py-3 text-left font-semibold">Slug</th>
                                    <th class="px-4 py-3 text-right font-semibold">Tools</th>
                                    <th class="px-4 py-3 text-center font-semibold">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="p in personas" :key="p.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-primary">{{ p.name }}</span>
                                            <AppBadge v-if="p.is_default" tone="primary">Default</AppBadge>
                                        </div>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ p.system_prompt }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-on-surface-variant">{{ p.slug }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <AppBadge tone="neutral">{{ p.tools_count }}</AppBadge>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <AppBadge :tone="p.is_active ? 'success' : 'neutral'">{{ p.is_active ? 'Aktif' : 'Nonaktif' }}</AppBadge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" class="grid size-9 place-items-center rounded-lg text-on-surface-variant hover:bg-surface-container-low hover:text-primary" title="Edit" @click="openEditPersona(p)">
                                                <AppIcon name="edit" />
                                            </button>
                                            <button v-if="!p.is_default" type="button" class="grid size-9 place-items-center rounded-lg text-on-surface-variant hover:bg-surface-container-low hover:text-primary" title="Set as default" @click="togglePersona(p, 'is_default')">
                                                <AppIcon name="star" />
                                            </button>
                                            <button type="button" class="grid size-9 place-items-center rounded-lg text-on-surface-variant hover:bg-surface-container-low hover:text-primary" :title="p.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="togglePersona(p, 'is_active')">
                                                <AppIcon :name="p.is_active ? 'toggle_on' : 'toggle_off'" />
                                            </button>
                                            <button v-if="!p.is_default" type="button" class="grid size-9 place-items-center rounded-lg text-on-surface-variant hover:bg-error-container hover:text-error" title="Hapus" @click="deletePersona(p)">
                                                <AppIcon name="delete" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- ============= TOOLS TAB ============= -->
            <div v-else-if="activeTab === 'tools'" class="space-y-6">
                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Tool Registry & Sinkronisasi</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Tools ter-register via <code class="rounded bg-surface-container px-1 py-0.5 text-xs">ToolHandler</code>. Sync dari codebase untuk materialisasi DB row.</p>
                        </div>
                        <AppButton icon="sync" :loading="toolSyncing" @click="syncTools">Sinkronkan dari Registry</AppButton>
                    </header>

                    <div v-if="tools.length === 0" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-10 text-center">
                        <AppIcon name="build" class="text-4xl text-on-surface-variant" />
                        <p class="mt-2 text-sm text-on-surface-variant">Belum ada tool di DB. Klik "Sinkronkan" untuk menarik dari registry.</p>
                    </div>
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Tool</th>
                                    <th class="px-4 py-3 text-center font-semibold">Registry</th>
                                    <th class="px-4 py-3 text-center font-semibold">Konfirmasi</th>
                                    <th class="px-4 py-3 text-center font-semibold">Aktif</th>
                                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="t in tools" :key="t.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-3">
                                        <p class="font-mono text-xs font-bold text-primary">{{ t.name }}</p>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ t.description }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <AppBadge :tone="t.is_registered ? 'success' : 'warning'">
                                            {{ t.is_registered ? 'Terdaftar' : 'Tertinggal' }}
                                        </AppBadge>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" class="grid size-10 place-items-center rounded-lg" :class="t.requires_confirmation ? 'text-amber-600' : 'text-on-surface-variant'" @click="toggleTool(t, 'requires_confirmation')">
                                            <AppIcon :name="t.requires_confirmation ? 'lock' : 'lock_open'" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" class="grid size-10 place-items-center rounded-lg" :class="t.is_active ? 'text-secondary' : 'text-on-surface-variant'" @click="toggleTool(t, 'is_active')">
                                            <AppIcon :name="t.is_active ? 'toggle_on' : 'toggle_off'" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" class="grid size-9 place-items-center rounded-lg text-on-surface-variant hover:bg-surface-container-low hover:text-primary" title="Detail" @click="openToolDetail(t)">
                                                <AppIcon name="info" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- ============= KNOWLEDGE BASE TAB ============= -->
            <div v-else-if="activeTab === 'knowledge'" class="space-y-6">
                <AppCard>
                    <header class="mb-4">
                        <h2 class="text-lg font-bold text-primary">Upload Dokumen RAG</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">File akan di-chunk dan di-embed untuk retrieval.</p>
                    </header>
                    <form class="space-y-4" @submit.prevent="submitUpload">
                        <AppInput v-model="uploadForm.title" label="Judul (opsional)" placeholder="Mis. Buku Pedoman Koperasi 2024" />
                        <div>
                            <SmartSelect
                                v-model="uploadForm.persona_id"
                                label="Persona (opsional)"
                                :options="uploadPersonaOptions"
                                placeholder="— Pilih persona —"
                                hint="Dokumen akan di-scope ke persona ini. Kosongkan untuk global."
                                :clearable="false"
                            />
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
                                <input type="file" class="hidden" accept=".pdf,.docx,.md,.markdown,.html,.htm,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown,text/html" @change="onUploadFile">
                            </label>
                            <p v-if="uploadFileName" class="mt-2 text-xs text-on-surface-variant">
                                Dipilih: <span class="font-semibold">{{ uploadFileName }}</span>
                            </p>
                        </div>
                        <p v-if="uploadResult" class="text-xs" :class="uploadResult.ok ? 'text-success' : 'text-error'">{{ uploadResult.message }}</p>
                        <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                            <AppButton type="submit" icon="upload" :loading="uploadLoading" :disabled="!uploadForm.file">Upload &amp; Ingest</AppButton>
                        </div>
                    </form>
                </AppCard>

                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Dokumen Terupload</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Total {{ docs.items.length }} dokumen aktif.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <SmartSelect v-model="uploadForm.persona_id" :options="kbPersonaOptions" placeholder="Semua persona" hide-label class="min-w-44" />
                            <button type="button" class="inline-flex items-center gap-1 rounded-md border border-outline-variant bg-surface px-3 py-1.5 text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container" :disabled="docs.loading" @click="fetchDocuments">
                                <AppIcon name="refresh" />
                                <span>{{ docs.loading ? 'Memuat…' : 'Refresh' }}</span>
                            </button>
                        </div>
                    </header>

                    <div v-if="docs.loading" class="rounded-xl bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">Memuat…</div>
                    <div v-else-if="docs.error" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ docs.error }}</div>
                    <AppEmptyState v-else-if="!docs.items.length" icon="library_books" title="Belum ada dokumen" description="Upload file di atas untuk mulai membangun knowledge base." />
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Judul</th>
                                    <th class="px-4 py-2 text-left font-semibold">Persona</th>
                                    <th class="px-4 py-2 text-left font-semibold">Format</th>
                                    <th class="px-4 py-2 text-right font-semibold">Ukuran</th>
                                    <th class="px-4 py-2 text-right font-semibold">Chunks</th>
                                    <th class="px-4 py-2 text-left font-semibold">Diupload</th>
                                    <th class="px-4 py-2 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="d in docs.items" :key="d.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-2">
                                        <p class="font-semibold text-primary">{{ d.title }}</p>
                                        <p v-if="d.preview" class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ d.preview }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ d.persona_name ?? 'Global' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="rounded bg-surface-container-lowest px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ d.format || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ formatBytes(d.content_length) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ d.chunks_count }}</td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ formatDate(d.created_at) }}</td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" class="grid size-8 place-items-center rounded-lg text-on-surface-variant hover:bg-surface-container-low hover:text-primary" title="Lihat detail" @click="viewDocument(d)">
                                                <AppIcon name="visibility" />
                                            </button>
                                            <button type="button" class="grid size-8 place-items-center rounded-lg text-on-surface-variant hover:bg-error-container hover:text-error" title="Hapus" @click="deleteDocument(d)">
                                                <AppIcon name="delete" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- ============= TEST CHAT TAB ============= -->
            <div v-else-if="activeTab === 'chat'" class="space-y-4">
                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Test Chat</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Streaming SSE ke AgentLoop in-process.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <SmartSelect v-model="chatPersonaId" :options="chatPersonaOptions" hide-label placeholder="Pilih persona" class="min-w-56" />
                            <button v-if="chatMessages.length" type="button" class="inline-flex items-center gap-1 rounded-md border border-outline-variant bg-surface px-3 py-2 text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container" @click="clearChat">
                                <AppIcon name="delete_sweep" />
                                <span>Reset</span>
                            </button>
                        </div>
                    </header>

                    <div ref="chatListEl" class="min-h-96 max-h-[28rem] space-y-2 overflow-y-auto rounded-lg bg-surface p-3">
                        <p v-if="!chatMessages.length" class="text-center text-xs text-on-surface-variant">Belum ada percakapan.</p>
                        <div v-for="msg in chatMessages" :key="msg.id" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed"
                                :class="msg.role === 'user'
                                    ? 'rounded-br-sm bg-primary text-on-primary whitespace-pre-wrap'
                                    : msg.role === 'error'
                                        ? 'rounded-bl-sm border border-error/40 bg-error-container text-error'
                                        : 'rounded-bl-sm border border-outline-variant bg-surface-container-lowest text-primary'"
                            >
                                <template v-if="msg.role === 'user' || msg.role === 'error'">{{ msg.content }}</template>
                                <div v-else class="flex flex-col gap-2">
                                    <template v-for="block in blocksFor(msg)" :key="block.id">
                                        <h1 v-if="block.type === 'heading' && block.level === 1" class="text-base font-bold">{{ block.text }}</h1>
                                        <h2 v-else-if="block.type === 'heading' && block.level === 2" class="text-sm font-bold">{{ block.text }}</h2>
                                        <h3 v-else-if="block.type === 'heading' && block.level === 3" class="text-sm font-semibold">{{ block.text }}</h3>
                                        <!-- eslint-disable-next-line vue/no-v-html -->
                                        <div v-else-if="block.type === 'paragraph' || block.type === 'code'" class="assistant-md-body" v-html="block.html" />
                                        <ArtifactCard v-else-if="block.type === 'artifact'" :block="block" @open="openArtifact(block)" />
                                        <ActionButton v-else-if="block.type === 'button'" :block="block" @submit="(payload) => onComponentSubmitTest(block, payload)" />
                                        <PollCard v-else-if="block.type === 'poll'" :block="block" :submitted="submittedComponentsTest.get(block.id) ?? null" @submit="(payload) => onComponentSubmitTest(block, payload)" />
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-if="chatTyping" class="flex justify-start" :aria-label="chatTypingLabel">
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

                    <form class="mt-3 flex items-end gap-2" @submit.prevent="sendChat">
                        <textarea
                            v-model="chatInput"
                            rows="2"
                            placeholder="Tulis pertanyaan…"
                            class="min-h-12 max-h-32 flex-1 resize-none rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm leading-5 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-50"
                            :disabled="chatBusy"
                            @keydown.enter.exact.prevent="sendChat"
                        />
                        <button v-if="chatBusy" type="button" class="grid size-12 shrink-0 place-items-center rounded-xl bg-secondary text-on-secondary" aria-label="Batal" @click="cancelChat">
                            <AppIcon name="close" />
                        </button>
                        <button v-else type="submit" class="grid size-12 shrink-0 place-items-center rounded-xl bg-primary text-on-primary disabled:opacity-50" :disabled="!chatInput.trim()" aria-label="Kirim">
                            <AppIcon name="send" />
                        </button>
                    </form>
                </AppCard>
            </div>

            <!-- ============= ACTIVITY TAB ============= -->
            <div v-else-if="activeTab === 'activity'" class="space-y-6">
                <AppCard>
                    <header class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-primary">Percakapan Terbaru</h2>
                        <button type="button" class="inline-flex items-center gap-1 rounded-md border border-outline-variant bg-surface px-3 py-1.5 text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container" :disabled="conversations.loading" @click="fetchConversations">
                            <AppIcon name="refresh" />
                            <span>{{ conversations.loading ? 'Memuat…' : 'Refresh' }}</span>
                        </button>
                    </header>
                    <div v-if="conversations.loading" class="rounded-xl bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">Memuat…</div>
                    <AppEmptyState v-else-if="!conversations.items.length" icon="forum" title="Belum ada percakapan" />
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Persona</th>
                                    <th class="px-4 py-2 text-left font-semibold">Channel</th>
                                    <th class="px-4 py-2 text-right font-semibold">Pesan</th>
                                    <th class="px-4 py-2 text-left font-semibold">Mulai</th>
                                    <th class="px-4 py-2 text-left font-semibold">Aktivitas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="c in conversations.items" :key="c.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-2">{{ c.persona_name }}</td>
                                    <td class="px-4 py-2"><AppBadge tone="neutral">{{ c.channel }}</AppBadge></td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ c.messages_count }}</td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ formatDate(c.started_at) }}</td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ formatDate(c.last_activity_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>

                <AppCard>
                    <header class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-primary">Audit Log</h2>
                        <button type="button" class="inline-flex items-center gap-1 rounded-md border border-outline-variant bg-surface px-3 py-1.5 text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container" :disabled="auditLogs.loading" @click="fetchAuditLogs">
                            <AppIcon name="refresh" />
                            <span>{{ auditLogs.loading ? 'Memuat…' : 'Refresh' }}</span>
                        </button>
                    </header>
                    <div v-if="auditLogs.loading" class="rounded-xl bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">Memuat…</div>
                    <AppEmptyState v-else-if="!auditLogs.items.length" icon="history" title="Belum ada audit log" />
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Waktu</th>
                                    <th class="px-4 py-2 text-left font-semibold">Aktor</th>
                                    <th class="px-4 py-2 text-left font-semibold">Action</th>
                                    <th class="px-4 py-2 text-left font-semibold">Entity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="log in auditLogs.items" :key="log.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-2 font-mono text-xs text-on-surface-variant">{{ formatTime(log.created_at) }}</td>
                                    <td class="px-4 py-2 text-xs">{{ log.actor }}</td>
                                    <td class="px-4 py-2">
                                        <AppBadge :tone="log.action.includes('failed') || log.action.includes('error') ? 'error' : log.action.includes('executed') ? 'success' : 'neutral'">
                                            {{ log.action }}
                                        </AppBadge>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs text-on-surface-variant">{{ log.entity_type }} #{{ log.entity_id?.slice(0, 8) ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- Person modal -->
            <AppModal v-model="personaModal.open" :title="personaModal.mode === 'create' ? 'Buat Persona' : 'Edit Persona'" size="lg">
                <div class="space-y-4">
                    <AppInput v-model="personaModal.data.name" label="Nama" placeholder="Contoh: Koperasi Assistant" />
                    <AppInput v-model="personaModal.data.slug" label="Slug" placeholder="Contoh: koperasi-assistant (otomatis dari nama jika kosong)" />
                    <AppTextarea v-model="personaModal.data.system_prompt" label="System Prompt" placeholder="Deskripsikan kepribadian, gaya bahasa, dan batasan persona ini…" :rows="8" />
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-wider text-primary">Tool Scope (kosongkan = semua tools)</label>
                        <div class="grid max-h-56 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-outline-variant bg-surface-container-lowest p-3 sm:grid-cols-2">
                            <label v-for="t in tools" :key="t.id" class="flex items-start gap-2 rounded-lg p-2 hover:bg-surface-container-low">
                                <input
                                    type="checkbox"
                                    :value="t.id"
                                    :checked="personaModal.data.tool_ids.includes(t.id)"
                                    class="mt-1 size-4 rounded border-outline-variant text-primary focus:ring-primary"
                                    @change="(e) => {
                                        const checked = e.target.checked;
                                        if (checked && !personaModal.data.tool_ids.includes(t.id)) {
                                            personaModal.data.tool_ids.push(t.id);
                                        } else if (!checked) {
                                            personaModal.data.tool_ids = personaModal.data.tool_ids.filter((id) => id !== t.id);
                                        }
                                    }"
                                >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-mono text-xs font-bold text-primary">{{ t.name }}</p>
                                    <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ t.description }}</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <AppSwitch v-model="personaModal.data.is_active" label="Aktif" description="Persona dapat dipilih di chat" />
                        <AppSwitch v-model="personaModal.data.is_default" label="Default" description="Persona fallback" />
                    </div>
                </div>
                <template #footer>
                    <AppButton variant="ghost" @click="personaModal.open = false">Batal</AppButton>
                    <AppButton icon="save" @click="submitPersona">{{ personaModal.mode === 'create' ? 'Buat' : 'Simpan' }}</AppButton>
                </template>
            </AppModal>

            <!-- Tool detail modal -->
            <AppModal v-if="toolModal.tool" v-model="toolModal.open" :title="toolModal.tool.name" size="lg">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Description</p>
                        <p class="mt-1 text-sm">{{ toolModal.tool.description }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Registry</p>
                            <p class="mt-1"><AppBadge :tone="toolModal.tool.is_registered ? 'success' : 'warning'">{{ toolModal.tool.is_registered ? 'Terdaftar' : 'Tertinggal' }}</AppBadge></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Konfirmasi</p>
                            <p class="mt-1"><AppBadge :tone="toolModal.tool.requires_confirmation ? 'warning' : 'neutral'">{{ toolModal.tool.requires_confirmation ? 'Wajib konfirmasi' : 'Otomatis' }}</AppBadge></p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">JSON Schema</p>
                        <pre class="mt-2 max-h-80 overflow-auto rounded-xl border border-outline-variant bg-surface-container-lowest p-3 font-mono text-xs">{{ JSON.stringify(toolModal.tool.json_schema, null, 2) }}</pre>
                    </div>
                </div>
                <template #footer>
                    <AppButton @click="toolModal.open = false">Tutup</AppButton>
                </template>
            </AppModal>

            <!-- Document detail modal -->
            <AppModal v-if="docModal.doc" v-model="docModal.open" :title="docModal.doc.title" size="lg">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Format</p>
                            <p class="mt-1 font-mono">{{ docModal.doc.format }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Persona</p>
                            <p class="mt-1">{{ docModal.doc.persona_name ?? 'Global' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Chunks ({{ docModal.doc.chunks.length }})</p>
                        <div class="mt-2 max-h-96 space-y-2 overflow-y-auto rounded-xl border border-outline-variant bg-surface-container-lowest p-3">
                            <div v-for="chunk in docModal.doc.chunks" :key="chunk.id" class="rounded-lg border border-outline-variant bg-surface p-3">
                                <div class="flex items-center justify-between text-xs text-on-surface-variant">
                                    <span>Chunk #{{ chunk.chunk_index }}</span>
                                    <AppBadge :tone="chunk.has_embedding ? 'success' : 'warning'">{{ chunk.has_embedding ? 'Embedded' : 'Pending' }}</AppBadge>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-xs">{{ chunk.chunk_text }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <template #footer>
                    <AppButton @click="docModal.open = false">Tutup</AppButton>
                </template>
            </AppModal>

            <!-- Artifact modal -->
            <ArtifactModal :block="activeArtifactTest" @close="activeArtifactTest = null" />

            <!-- Toast -->
            <Transition name="fade">
                <div v-if="toast" class="fixed bottom-6 right-6 z-50 max-w-sm rounded-xl border p-4 shadow-lg"
                    :class="toast.tone === 'error' ? 'border-error/30 bg-error-container text-error' : 'border-secondary/30 bg-secondary-container text-secondary'">
                    <p class="text-sm font-semibold">{{ toast.message }}</p>
                </div>
            </Transition>
        </div>
    </AdminLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 200ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>