<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({ blockedIp: Object, allServers: Array })

const servers = ref(props.blockedIp.servers)
let pollInterval = null
const showActionMenu = ref(false)

const isPolling = computed(() => servers.value.some(s => ['pending', 'blocking', 'unblocking'].includes(s.status)))

const pollStatus = async () => {
    try {
        const res = await fetch(route('blocked-ips.status', props.blockedIp.id))
        const data = await res.json()
        servers.value = data.servers
    } catch {}
}

onMounted(() => {
    pollInterval = setInterval(() => {
        if (isPolling.value) pollStatus()
    }, 2000)
    document.addEventListener('click', closeMenu)
})
onUnmounted(() => {
    clearInterval(pollInterval)
    document.removeEventListener('click', closeMenu)
})

const closeMenu = (e) => {
    if (!e.target.closest('.action-menu')) {
        showActionMenu.value = false
    }
}

const statusConfig = {
    pending: { color: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400', label: 'Pending' },
    blocking: { color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', label: 'Blocking...' },
    blocked: { color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', label: 'Blocked' },
    failed: { color: 'bg-red-200 text-red-800 dark:bg-red-900/50 dark:text-red-300', label: 'Failed' },
    unblocking: { color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400', label: 'Unblocking...' },
    unblocked: { color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', label: 'Unblocked' },
}

// Confirmation modal state
const confirmModal = ref({ show: false, title: '', message: '', variant: 'info', confirmText: 'Confirm', action: null })

const showConfirm = ({ title, message, variant = 'info', confirmText = 'Confirm', action }) => {
    confirmModal.value = { show: true, title, message, variant, confirmText, action }
}
const onConfirm = () => {
    confirmModal.value.action?.()
    confirmModal.value.show = false
}
const onCancel = () => {
    confirmModal.value.show = false
}

// Block actions
const blockServer = (server) => {
    showConfirm({
        title: `Block on ${server.name}`,
        message: `This will block ${props.blockedIp.ip_address} on ${server.name} via SSH (UFW, Fail2Ban, and NGINX).`,
        variant: 'danger',
        confirmText: 'Block',
        action: () => router.post(route('blocked-ips.block-server', [props.blockedIp.id, server.id])),
    })
}

const blockAll = () => {
    showActionMenu.value = false
    const serverIds = props.allServers.map(s => s.id)
    showConfirm({
        title: 'Block on all servers',
        message: `This will block ${props.blockedIp.ip_address} on all ${props.allServers.length} active server(s) via SSH.`,
        variant: 'danger',
        confirmText: 'Block All',
        action: () => router.post(route('blocked-ips.block', props.blockedIp.id), { server_ids: serverIds }),
    })
}

// Unblock actions
const unblockServer = (server) => {
    showConfirm({
        title: `Unblock from ${server.name}`,
        message: `This will unblock ${props.blockedIp.ip_address} from ${server.name} via SSH.`,
        variant: 'info',
        confirmText: 'Unblock',
        action: () => router.post(route('blocked-ips.unblock', props.blockedIp.id), { server_ids: [server.id] }),
    })
}

const unblockAll = () => {
    showActionMenu.value = false
    showConfirm({
        title: 'Unblock from all servers',
        message: `This will unblock ${props.blockedIp.ip_address} from all servers via SSH.`,
        variant: 'info',
        confirmText: 'Unblock All',
        action: () => router.delete(route('blocked-ips.destroy', props.blockedIp.id)),
    })
}

// Helpers
const unattachedServers = computed(() => {
    const attachedIds = servers.value.map(s => s.id)
    return props.allServers.filter(s => !attachedIds.includes(s.id))
})
</script>

<template>
    <Head :title="`IP ${blockedIp.ip_address}`" />
    <AppLayout>
        <div class="max-w-3xl">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('blocked-ips.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono">{{ blockedIp.ip_address }}</h1>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 mb-6">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Blocked by {{ blockedIp.blocked_by }} on {{ blockedIp.created_at }}</span>
                        <p v-if="blockedIp.reason" class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ blockedIp.reason }}</p>
                    </div>

                    <!-- Desktop: inline buttons -->
                    <div class="hidden sm:flex items-center gap-2 shrink-0">
                        <button @click="blockAll"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                            Block All
                        </button>
                        <button v-if="servers.length" @click="unblockAll"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                            Unblock All
                        </button>
                    </div>

                    <!-- Mobile: action menu -->
                    <div class="sm:hidden relative action-menu shrink-0">
                        <button @click.stop="showActionMenu = !showActionMenu"
                            class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                            </svg>
                        </button>
                        <Transition
                            enter-active-class="transition ease-out duration-100"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <div v-if="showActionMenu"
                                class="absolute right-0 top-full mt-1 w-52 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-lg z-20 py-1">
                                <button @click="blockAll"
                                    class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 font-medium">
                                    Block on all servers
                                </button>
                                <button v-if="servers.length" @click="unblockAll"
                                    class="w-full text-left px-4 py-2.5 text-sm text-green-600 dark:text-green-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 font-medium">
                                    Unblock from all servers
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <!-- Attached servers -->
                    <div v-for="server in servers" :key="server.id" class="px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 dark:text-white text-sm">{{ server.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate">{{ server.host }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                            <span :class="statusConfig[server.status]?.color" class="text-xs font-medium px-2 sm:px-3 py-1 rounded-full whitespace-nowrap">
                                {{ statusConfig[server.status]?.label || server.status }}
                            </span>
                            <button v-if="['unblocked', 'failed'].includes(server.status)" @click="blockServer(server)"
                                class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium whitespace-nowrap">
                                Block
                            </button>
                            <button v-if="['blocked', 'failed'].includes(server.status)" @click="unblockServer(server)"
                                class="text-xs text-green-600 dark:text-green-400 hover:underline font-medium whitespace-nowrap">
                                Unblock
                            </button>
                        </div>
                    </div>

                    <!-- Unattached servers -->
                    <div v-for="server in unattachedServers" :key="'new-' + server.id" class="px-5 py-4 flex items-center justify-between border-dashed">
                        <div class="flex items-center gap-3">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white text-sm">{{ server.name }}</div>
                            </div>
                            <span class="text-xs font-medium px-2 sm:px-3 py-1 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400 whitespace-nowrap">
                                not added
                            </span>
                        </div>
                        <button @click="blockServer(server)"
                            class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium whitespace-nowrap">
                            Block
                        </button>
                    </div>
                </div>
            </div>

            <!-- Error details -->
            <div v-for="server in servers.filter(s => s.error_message)" :key="'err-' + server.id"
                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 mb-3">
                <div class="text-sm font-medium text-red-800 dark:text-red-300">{{ server.name }} - Error</div>
                <p class="text-xs text-red-600 dark:text-red-400 mt-1 font-mono break-all">{{ server.error_message }}</p>
            </div>
        </div>

        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :variant="confirmModal.variant"
            :confirm-text="confirmModal.confirmText"
            @confirm="onConfirm"
            @cancel="onCancel"
        />
    </AppLayout>
</template>
