<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({ blockedIps: Object, allServers: Array, filters: Object })

const expandedRow = ref(null)

// Filters
const search = ref(props.filters?.search || '')
const serverFilter = ref(props.filters?.server || '')
const statusFilter = ref(props.filters?.status || '')
let searchTimeout = null

const applyFilters = () => {
    const params = {}
    if (search.value) params.search = search.value
    if (serverFilter.value) params.server = serverFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    router.get(route('blocked-ips.index'), params, { preserveState: true, replace: true })
}

const onSearchInput = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
}

watch(serverFilter, applyFilters)
watch(statusFilter, applyFilters)

const clearFilters = () => {
    search.value = ''
    serverFilter.value = ''
    statusFilter.value = ''
    router.get(route('blocked-ips.index'), {}, { preserveState: true, replace: true })
}

const hasFilters = () => search.value || serverFilter.value || statusFilter.value

const selectedServerName = () => {
    if (!serverFilter.value) return null
    return props.allServers.find(s => s.id === serverFilter.value)?.name
}

// Mobile action sheet
const actionSheet = ref({ show: false, ip: null })
const openActionSheet = (ip) => {
    actionSheet.value = { show: true, ip }
}
const closeActionSheet = () => {
    actionSheet.value = { show: false, ip: null }
}

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    blocking: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    blocked: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    failed: 'bg-red-200 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    unblocking: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    unblocked: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
}

const toggleExpand = (id) => {
    expandedRow.value = expandedRow.value === id ? null : id
}

// Confirmation modal state
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', confirmText: 'Confirm', action: null })

const showConfirm = ({ title, message, variant = 'danger', confirmText = 'Confirm', action }) => {
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
const confirmBlockAll = (ip) => {
    closeActionSheet()
    const serverIds = props.allServers.map(s => s.id)
    showConfirm({
        title: 'Block on all servers',
        message: `This will block ${ip.ip_address} on all ${props.allServers.length} active server(s) via SSH.`,
        variant: 'danger',
        confirmText: 'Block All',
        action: () => router.post(route('blocked-ips.block', ip.id), { server_ids: serverIds }),
    })
}

const confirmBlockServer = (ip, server) => {
    showConfirm({
        title: `Block on ${server.name}`,
        message: `This will block ${ip.ip_address} on ${server.name} via SSH (UFW, Fail2Ban, and NGINX).`,
        variant: 'danger',
        confirmText: 'Block',
        action: () => router.post(route('blocked-ips.block-server', [ip.id, server.id])),
    })
}

// Unblock actions
const confirmUnblockAll = (ip) => {
    closeActionSheet()
    showConfirm({
        title: 'Unblock from all servers',
        message: `This will unblock ${ip.ip_address} from all servers via SSH. The IP will be removed from UFW, Fail2Ban, and NGINX on each server.`,
        variant: 'info',
        confirmText: 'Unblock All',
        action: () => router.delete(route('blocked-ips.destroy', ip.id)),
    })
}

const confirmUnblockServer = (ip, server) => {
    showConfirm({
        title: `Unblock from ${server.name}`,
        message: `This will unblock ${ip.ip_address} from ${server.name} only. Other servers will not be affected.`,
        variant: 'info',
        confirmText: 'Unblock',
        action: () => router.post(route('blocked-ips.unblock-server', [ip.id, server.id])),
    })
}

const confirmDeleteEntry = (ip) => {
    closeActionSheet()
    showConfirm({
        title: 'Delete entry',
        message: `Remove ${ip.ip_address} from the database. This will NOT unblock it on any servers — it only removes the record from the interface.`,
        variant: 'danger',
        confirmText: 'Delete',
        action: () => router.delete(route('blocked-ips.force-delete', ip.id)),
    })
}

const viewFromSheet = (ip) => {
    closeActionSheet()
    router.visit(route('blocked-ips.show', ip.id))
}

const toggleExpandFromSheet = (ip) => {
    closeActionSheet()
    toggleExpand(ip.id)
}

const getUnattachedServers = (ip) => {
    const attachedIds = ip.servers?.map(s => s.id) || []
    return props.allServers.filter(s => !attachedIds.includes(s.id))
}
</script>

<template>
    <Head title="Blocked IPs" />
    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Blocked IPs</h1>
            <Link :href="route('blocked-ips.create')" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                Block IP
            </Link>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-2 sm:gap-3">
            <div class="relative flex-1 min-w-[140px] max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="search" @input="onSearchInput" type="text" placeholder="Search IP..."
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
            </div>
            <select v-model="serverFilter"
                class="text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white py-2 px-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">All servers</option>
                <option v-for="s in allServers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="statusFilter"
                class="text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white py-2 px-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">All statuses</option>
                <option value="blocked">Blocked</option>
                <option value="unblocked">Unblocked</option>
                <option value="failed">Failed</option>
                <option value="pending">Pending</option>
            </select>
            <button v-if="hasFilters()" @click="clearFilters" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium whitespace-nowrap">
                Clear filters
            </button>
        </div>

        <!-- Active filter badge -->
        <div v-if="selectedServerName()" class="mb-3 flex items-center gap-2">
            <span class="text-xs bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 px-2.5 py-1 rounded-full font-medium inline-flex items-center gap-1">
                Server: {{ selectedServerName() }}
                <button @click="serverFilter = ''" class="hover:text-primary-900 dark:hover:text-primary-200">&times;</button>
            </span>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">Reason</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Servers</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">By</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">When</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template v-for="ip in blockedIps.data" :key="ip.id">
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-5 py-3">
                                    <Link :href="route('blocked-ips.show', ip.id)" class="font-mono font-semibold text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                        {{ ip.ip_address }}
                                    </Link>
                                </td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 max-w-[200px] truncate hidden sm:table-cell">{{ ip.reason || '-' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="s in ip.servers.slice(0, 2)" :key="s.id" :class="statusColors[s.status]"
                                            class="text-xs font-medium px-2 py-0.5 rounded-full" :title="s.error_message || s.status">
                                            {{ s.name }}
                                        </span>
                                        <Link v-if="ip.servers.length > 2" :href="route('blocked-ips.show', ip.id)"
                                            class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                            :title="`${ip.servers.length - 2} more server(s)`">
                                            +{{ ip.servers.length - 2 }}
                                        </Link>
                                        <span v-if="!ip.servers?.length" class="text-xs text-gray-400 dark:text-gray-500 italic">No servers</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ ip.blocked_by }}</td>
                                <td class="px-5 py-3 text-gray-400 dark:text-gray-500 text-xs hidden lg:table-cell">{{ ip.created_at }}</td>
                                <td class="px-5 py-3 text-right">
                                    <!-- Desktop: inline buttons -->
                                    <div class="hidden md:flex items-center justify-end gap-2">
                                        <Link :href="route('blocked-ips.show', ip.id)" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">View</Link>
                                        <button v-if="ip.servers?.length" @click="toggleExpand(ip.id)" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                            {{ expandedRow === ip.id ? 'Hide' : 'Servers' }}
                                        </button>
                                        <button @click="confirmBlockAll(ip)" class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium">Block All</button>
                                        <button v-if="ip.servers?.length" @click="confirmUnblockAll(ip)" class="text-xs text-green-600 dark:text-green-400 hover:underline font-medium">Unblock All</button>
                                        <button @click="confirmDeleteEntry(ip)" class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium">Delete</button>
                                    </div>

                                    <!-- Mobile: three-dot trigger for action sheet -->
                                    <button @click="openActionSheet(ip)" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <!-- Expanded per-server row -->
                            <tr v-if="expandedRow === ip.id">
                                <td colspan="6" class="bg-gray-50 dark:bg-gray-800/30 px-5 py-3">
                                    <div class="space-y-2">
                                        <!-- Attached servers -->
                                        <div v-for="server in ip.servers" :key="server.id"
                                            class="flex items-center justify-between bg-white dark:bg-gray-900 rounded-lg px-4 py-2.5 border border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center gap-3">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ server.name }}</span>
                                                <span :class="statusColors[server.status]" class="text-xs font-medium px-2 py-0.5 rounded-full">
                                                    {{ server.status }}
                                                </span>
                                                <span v-if="server.error_message" class="text-xs text-red-500 dark:text-red-400 truncate max-w-[200px]" :title="server.error_message">
                                                    {{ server.error_message }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button v-if="['unblocked', 'failed'].includes(server.status)" @click="confirmBlockServer(ip, server)"
                                                    class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium whitespace-nowrap">
                                                    Block
                                                </button>
                                                <button v-if="['blocked', 'failed'].includes(server.status)" @click="confirmUnblockServer(ip, server)"
                                                    class="text-xs text-green-600 dark:text-green-400 hover:underline font-medium whitespace-nowrap">
                                                    Unblock
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Unattached servers -->
                                        <div v-for="server in getUnattachedServers(ip)" :key="'new-' + server.id"
                                            class="flex items-center justify-between bg-white dark:bg-gray-900 rounded-lg px-4 py-2.5 border border-gray-200 dark:border-gray-700 border-dashed">
                                            <div class="flex items-center gap-3">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ server.name }}</span>
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                    not added
                                                </span>
                                            </div>
                                            <button @click="confirmBlockServer(ip, server)"
                                                class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium whitespace-nowrap">
                                                Block
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!blockedIps.data?.length">
                            <td colspan="6" class="px-5 py-12 text-center text-gray-400 dark:text-gray-500">No blocked IPs</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="blockedIps.links?.length > 3" class="px-5 py-3 border-t border-gray-200 dark:border-gray-800 flex justify-center gap-1">
                <template v-for="link in blockedIps.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" v-html="link.label"
                        :class="[link.active ? 'bg-primary-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800']"
                        class="px-3 py-1 rounded-lg text-sm" />
                    <span v-else v-html="link.label" class="px-3 py-1 text-gray-300 dark:text-gray-600 text-sm" />
                </template>
            </div>
        </div>

        <!-- Mobile action sheet (teleported to body) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="actionSheet.show" class="fixed inset-0 z-50 md:hidden" @click.self="closeActionSheet">
                    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeActionSheet" />
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="translate-y-full"
                        enter-to-class="translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="translate-y-0"
                        leave-to-class="translate-y-full"
                        appear
                    >
                        <div v-if="actionSheet.show" class="absolute bottom-0 left-0 right-0 bg-white dark:bg-gray-800 rounded-t-2xl shadow-xl safe-bottom">
                            <div class="flex justify-center pt-3 pb-1">
                                <div class="w-10 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                            </div>
                            <div class="px-5 pb-2">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white font-mono">{{ actionSheet.ip?.ip_address }}</p>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="viewFromSheet(actionSheet.ip)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    View details
                                </button>
                                <button v-if="actionSheet.ip?.servers?.length" @click="toggleExpandFromSheet(actionSheet.ip)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-blue-600 dark:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" /></svg>
                                    {{ expandedRow === actionSheet.ip?.id ? 'Hide servers' : 'Show servers' }}
                                </button>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="confirmBlockAll(actionSheet.ip)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3 font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    Block on all servers
                                </button>
                                <button v-if="actionSheet.ip?.servers?.length" @click="confirmUnblockAll(actionSheet.ip)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-green-600 dark:text-green-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3 font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Unblock from all servers
                                </button>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="confirmDeleteEntry(actionSheet.ip)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3 font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    Delete entry
                                </button>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-700 p-3">
                                <button @click="closeActionSheet"
                                    class="w-full py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

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

<style scoped>
.safe-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0.75rem);
}
</style>
