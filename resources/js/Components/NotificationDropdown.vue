<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from './AppIcon.vue';

const open = ref(false);
const activeTab = ref('all');
const loading = ref(false);
const items = ref([]);
const unreadCount = ref(0);
const dropdownRef = ref(null);
const notifiedIds = ref(new Set());
let pollTimer = null;

const iconTones = {
    warning: 'warning',
    danger: 'error',
    info: 'info',
    success: 'success',
};

async function fetchNotifications(isPolling = false) {
    if (!isPolling) {
        loading.value = true;
    }
    try {
        const res = await fetch('/api/notifications', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            const newItems = data.items || [];
            items.value = newItems;
            unreadCount.value = data.unread_count || 0;

            // Trigger Desktop Push Notification for new unread notifications from other users / actions
            if (typeof window !== 'undefined' && window.desktopAPI?.sendNotification) {
                newItems.forEach((item) => {
                    if (!item.read && !notifiedIds.value.has(item.id)) {
                        notifiedIds.value.add(item.id);
                        if (isPolling) {
                            window.desktopAPI.sendNotification({
                                title: item.title,
                                body: item.message,
                                url: item.target_url || '/dashboard',
                            });
                        }
                    }
                });
            }
        }
    } catch (e) {
        console.error('Failed to fetch notifications:', e);
    } finally {
        if (!isPolling) {
            loading.value = false;
        }
    }
}

const filteredItems = computed(() => {
    if (activeTab.value === 'unread') {
        return items.value.filter((item) => !item.read);
    }
    return items.value;
});

async function markAsRead(id = null) {
    const allItemIds = items.value.map((i) => i.id);
    if (id) {
        const target = items.value.find((i) => i.id === id);
        if (target) {
            target.read = true;
        }
    } else {
        items.value.forEach((i) => {
            i.read = true;
        });
    }
    unreadCount.value = items.value.filter((i) => !i.read).length;

    try {
        await fetch('/api/notifications/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ id, ids: id ? null : allItemIds }),
        });
    } catch (e) {
        console.error('Failed to mark read:', e);
    }
}

async function handleItemClick(item) {
    open.value = false;
    if (!item.read) {
        await markAsRead(item.id);
    }
    if (item.target_url) {
        router.visit(item.target_url);
    }
}

function toggleDropdown() {
    open.value = !open.value;
    if (open.value) {
        fetchNotifications();
    }
}

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => {
    fetchNotifications();
    document.addEventListener('click', handleClickOutside);

    // Poll every 45s for desktop / web live notification sync
    pollTimer = setInterval(() => {
        if (typeof document !== 'undefined' && !document.hidden) {
            fetchNotifications(true);
        }
    }, 45000);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <button
            type="button"
            class="relative grid size-10 shrink-0 place-items-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary focus:outline-none"
            aria-label="Notifikasi"
            :aria-expanded="open"
            @click="toggleDropdown"
        >
            <AppIcon name="notifications" class="text-2xl leading-none" />
            <span
                v-if="unreadCount > 0"
                class="absolute right-1 top-1 flex size-4 items-center justify-center rounded-full bg-error text-[10px] font-bold leading-none text-on-error shadow-sm animate-pulse"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform scale-95 opacity-0 -translate-y-1"
            enter-to-class="transform scale-100 opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform scale-100 opacity-100 translate-y-0"
            leave-to-class="transform scale-95 opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute right-0 top-12 z-50 w-80 sm:w-96 rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-xl overflow-hidden"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-outline-variant px-4 py-3 bg-surface-container-low/50">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-primary">Notifikasi</h3>
                        <span v-if="unreadCount > 0" class="rounded-full bg-error/10 px-2 py-0.5 text-xs font-bold text-error">
                            {{ unreadCount }} baru
                        </span>
                    </div>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs font-medium text-primary hover:underline focus:outline-none"
                        @click="markAsRead(null)"
                    >
                        Tandai semua dibaca
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-outline-variant px-2 bg-surface-container-lowest text-xs font-semibold">
                    <button
                        type="button"
                        class="flex-1 py-2 text-center transition-colors border-b-2"
                        :class="activeTab === 'all' ? 'border-primary text-primary font-bold' : 'border-transparent text-on-surface-variant hover:text-primary'"
                        @click="activeTab = 'all'"
                    >
                        Semua ({{ items.length }})
                    </button>
                    <button
                        type="button"
                        class="flex-1 py-2 text-center transition-colors border-b-2"
                        :class="activeTab === 'unread' ? 'border-primary text-primary font-bold' : 'border-transparent text-on-surface-variant hover:text-primary'"
                        @click="activeTab = 'unread'"
                    >
                        Belum Dibaca ({{ unreadCount }})
                    </button>
                </div>

                <!-- List Content -->
                <div class="max-h-80 overflow-y-auto divide-y divide-outline-variant/40">
                    <div v-if="loading && items.length === 0" class="py-8 text-center text-xs text-on-surface-variant">
                        Memuat notifikasi...
                    </div>
                    <div v-else-if="filteredItems.length === 0" class="py-8 text-center text-xs text-on-surface-variant">
                        Tidak ada notifikasi {{ activeTab === 'unread' ? 'belum dibaca' : '' }}.
                    </div>
                    <div
                        v-for="item in filteredItems"
                        :key="item.id"
                        class="flex cursor-pointer items-start gap-3 p-3.5 transition-colors hover:bg-surface-container-low/70"
                        :class="!item.read ? 'bg-primary-container/10' : ''"
                        @click="handleItemClick(item)"
                    >
                        <AppIcon
                            :name="item.icon || 'info'"
                            :tone="iconTones[item.variant] || 'info'"
                            container-size="9"
                            container-shape="pill"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-xs font-bold text-primary truncate">{{ item.title }}</p>
                                <span class="text-[10px] text-on-surface-variant shrink-0">{{ item.time }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-on-surface-variant line-clamp-2 leading-relaxed">{{ item.message }}</p>

                            <!-- Actor Attribution Chip ("oleh {siapa}") -->
                            <div v-if="item.actor" class="mt-1.5 flex items-center gap-1">
                                <span class="inline-flex items-center gap-1 rounded bg-surface-container-high/80 px-1.5 py-0.5 text-[10px] font-medium text-on-surface-variant">
                                    <AppIcon name="person" class="text-[11px] text-primary" />
                                    <span>oleh <strong class="font-semibold text-primary">{{ item.actor }}</strong></span>
                                </span>
                            </div>
                        </div>
                        <span v-if="!item.read" class="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-outline-variant bg-surface-container-low/50 px-4 py-2.5 text-center">
                    <Link
                        href="/notifications/billing"
                        class="text-xs font-semibold text-primary hover:underline flex items-center justify-center gap-1"
                        @click="open = false"
                    >
                        <AppIcon name="chat" class="text-base" />
                        Pusat Pengingat WhatsApp
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>