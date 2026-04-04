<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({ blockedIp: Object })

const servers = ref(props.blockedIp.servers)
let pollInterval = null

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
})
onUnmounted(() => clearInterval(pollInterval))

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
    showConfirm({
        title: 'Unblock from all servers',
        message: `This will unblock ${props.blockedIp.ip_address} from all servers via SSH.`,
        variant: 'info',
        confirmText: 'Unblock All',
        action: () => router.delete(route('blocked-ips.destroy', props.blockedIp.id)),
    })
}
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
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Blocked by {{ blockedIp.blocked_by }} on {{ blockedIp.created_at }}</span>
                        <p v-if="blockedIp.reason" class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ blockedIp.reason }}</p>
                    </div>
                    <button @click="unblockAll"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                        Unblock All
                    </button>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="server in servers" :key="server.id" class="px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white text-sm">{{ server.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ server.host }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="statusConfig[server.status]?.color" class="text-xs font-medium px-3 py-1 rounded-full">
                                {{ statusConfig[server.status]?.label || server.status }}
                            </span>
                            <button v-if="['blocked', 'failed'].includes(server.status)" @click="unblockServer(server)"
                                class="text-xs text-green-600 dark:text-green-400 hover:underline font-medium">
                                Unblock
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error details -->
            <div v-for="server in servers.filter(s => s.error_message)" :key="'err-' + server.id"
                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 mb-3">
                <div class="text-sm font-medium text-red-800 dark:text-red-300">{{ server.name }} - Error</div>
                <p class="text-xs text-red-600 dark:text-red-400 mt-1 font-mono">{{ server.error_message }}</p>
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
