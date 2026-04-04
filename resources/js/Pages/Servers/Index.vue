<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({ servers: Array, currentScriptVersion: String })

const selected = ref([])
const bulkLoading = ref(false)

// Mobile action sheet
const actionSheet = ref({ show: false, server: null })
const openActionSheet = (server) => {
    actionSheet.value = { show: true, server }
}
const closeActionSheet = () => {
    actionSheet.value = { show: false, server: null }
}

const allSelected = computed(() => props.servers.length > 0 && selected.value.length === props.servers.length)
const someSelected = computed(() => selected.value.length > 0 && selected.value.length < props.servers.length)

const toggleAll = () => {
    selected.value = allSelected.value ? [] : props.servers.map(s => s.id)
}

const needsUpdateCount = computed(() => props.servers.filter(s => s.needs_update).length)
const selectedServers = computed(() => props.servers.filter(s => selected.value.includes(s.id)))
const selectedNeedUpdate = computed(() => selectedServers.value.filter(s => s.needs_update).length)

// Confirm modal
const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', confirmText: 'Confirm', action: null })
const showConfirm = ({ title, message, variant = 'danger', confirmText = 'Confirm', action }) => {
    confirmModal.value = { show: true, title, message, variant, confirmText, action }
}
const onConfirm = () => { confirmModal.value.action?.(); confirmModal.value.show = false }
const onCancel = () => { confirmModal.value.show = false }

const confirmDelete = (server) => {
    closeActionSheet()
    showConfirm({
        title: 'Delete server',
        message: `Are you sure you want to delete "${server.name}"? This cannot be undone.`,
        variant: 'danger',
        confirmText: 'Delete',
        action: () => router.delete(route('servers.destroy', server.id)),
    })
}

const editServer = (server) => {
    closeActionSheet()
    router.visit(route('servers.edit', server.id))
}

const testConnection = (server) => {
    closeActionSheet()
    router.post(route('servers.test', server.id))
}

const updateScript = (server) => {
    closeActionSheet()
    showConfirm({
        title: `Update script on ${server.name}`,
        message: `This will upload blockip.sh v${props.currentScriptVersion} and migrate nginx config.`,
        variant: 'warning',
        confirmText: 'Update',
        action: () => router.post(route('servers.update-script', server.id)),
    })
}

// Bulk actions
const bulkUpdateScript = () => {
    const ids = selected.value
    showConfirm({
        title: 'Update script on selected servers',
        message: `This will upload the latest blockip.sh (v${props.currentScriptVersion}) and migrate nginx from 403 to 444 on ${ids.length} server(s).`,
        variant: 'warning',
        confirmText: 'Update All',
        action: () => {
            bulkLoading.value = true
            router.post(route('servers.bulk-update-script'), { server_ids: ids }, {
                onFinish: () => { bulkLoading.value = false; selected.value = [] },
            })
        },
    })
}

const bulkTestConnection = () => {
    bulkLoading.value = true
    router.post(route('servers.bulk-test-connection'), { server_ids: selected.value }, {
        onFinish: () => { bulkLoading.value = false; selected.value = [] },
    })
}

const bulkDelete = () => {
    showConfirm({
        title: 'Delete selected servers',
        message: `Are you sure you want to delete ${selected.value.length} server(s)? This cannot be undone.`,
        variant: 'danger',
        confirmText: 'Delete All',
        action: () => {
            bulkLoading.value = true
            router.post(route('servers.bulk-delete'), { server_ids: selected.value }, {
                onFinish: () => { bulkLoading.value = false; selected.value = [] },
            })
        },
    })
}
</script>

<template>
    <Head title="Servers" />
    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Servers</h1>
                <p v-if="needsUpdateCount" class="text-sm text-yellow-600 dark:text-yellow-400 mt-1">
                    {{ needsUpdateCount }} server(s) need a script update
                </p>
            </div>
            <Link :href="route('servers.create')" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                Add Server
            </Link>
        </div>

        <!-- Bulk actions bar -->
        <Transition enter-active-class="duration-150 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
            leave-active-class="duration-100 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
            <div v-if="selected.length" class="mb-4 flex flex-wrap items-center gap-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl px-2 sm:px-4 py-2 sm:py-3">
                <span class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ selected.length }} selected</span>
                <div class="h-4 w-px bg-primary-300 dark:bg-primary-700" />
                <button @click="bulkTestConnection" :disabled="bulkLoading"
                    class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 disabled:opacity-50">
                    Test Connection
                </button>
                <button v-if="selectedNeedUpdate" @click="bulkUpdateScript" :disabled="bulkLoading"
                    class="text-sm font-medium text-yellow-700 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 disabled:opacity-50">
                    Update Script ({{ selectedNeedUpdate }})
                </button>
                <button @click="bulkUpdateScript" v-else :disabled="bulkLoading"
                    class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 disabled:opacity-50">
                    Reinstall Script
                </button>
                <div class="flex-1" />
                <button @click="bulkDelete" :disabled="bulkLoading"
                    class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 disabled:opacity-50">
                    Delete
                </button>
            </div>
        </Transition>

        <div v-if="servers.length" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="w-10 px-2 sm:px-2 sm:px-4 py-2 sm:py-3">
                                <input type="checkbox" :checked="allSelected" :indeterminate="someSelected" @change="toggleAll"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" />
                            </th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Server</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">Connection</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Script</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Blocked</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Last Connected</th>
                            <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="server in servers" :key="server.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            :class="{ 'bg-primary-50/50 dark:bg-primary-900/10': selected.includes(server.id) }">
                            <td class="px-2 sm:px-4 py-2 sm:py-3">
                                <input type="checkbox" :value="server.id" v-model="selected"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" />
                            </td>
                            <td class="px-2 sm:px-4 py-2 sm:py-3">
                                <Link :href="route('servers.edit', server.id)" class="group">
                                    <div class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ server.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ server.ssh_user }}@{{ server.host }}:{{ server.port }}</div>
                                </Link>
                            </td>
                            <td class="px-2 sm:px-4 py-2 sm:py-3 hidden sm:table-cell">
                                <span :class="[server.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500']"
                                    class="text-xs font-medium px-2 py-0.5 rounded-full">
                                    {{ server.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-2 sm:px-4 py-2 sm:py-3">
                                <div class="flex items-center gap-1.5">
                                    <template v-if="server.script_installed">
                                        <span v-if="server.needs_update"
                                            class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            v{{ server.script_version || '?' }}
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            v{{ server.script_version }}
                                        </span>
                                    </template>
                                    <span v-else class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Not installed
                                    </span>
                                </div>
                            </td>
                            <td class="px-2 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                                <Link v-if="server.blocked_ips_count" :href="route('blocked-ips.index', { server: server.id })"
                                    class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">
                                    {{ server.blocked_ips_count }}
                                </Link>
                                <span v-else class="text-xs text-gray-400">0</span>
                            </td>
                            <td class="px-2 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ server.last_connected_at || 'Never' }}</span>
                            </td>
                            <td class="px-2 sm:px-4 py-2 sm:py-3 text-right">
                                <!-- Desktop: inline buttons -->
                                <div class="hidden md:flex items-center justify-end gap-2">
                                    <Link :href="route('servers.edit', server.id)" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">Edit</Link>
                                    <button v-if="server.needs_update" @click="updateScript(server)" class="text-xs text-yellow-600 dark:text-yellow-400 hover:underline font-medium">Update</button>
                                    <button @click="confirmDelete(server)" class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-300 font-medium">Delete</button>
                                </div>

                                <!-- Mobile: three-dot trigger -->
                                <button @click="openActionSheet(server)" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            <p class="text-gray-500 dark:text-gray-400 mb-4">No servers configured yet</p>
            <Link :href="route('servers.create')" class="text-primary-600 dark:text-primary-400 font-medium hover:underline">Add your first server</Link>
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
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ actionSheet.server?.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ actionSheet.server?.ssh_user }}@{{ actionSheet.server?.host }}:{{ actionSheet.server?.port }}</p>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="editServer(actionSheet.server)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Edit server
                                </button>
                                <button @click="testConnection(actionSheet.server)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    Test connection
                                </button>
                                <button v-if="actionSheet.server?.needs_update" @click="updateScript(actionSheet.server)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-yellow-600 dark:text-yellow-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3 font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Update script to v{{ currentScriptVersion }}
                                </button>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="confirmDelete(actionSheet.server)"
                                    class="w-full text-left px-5 py-3.5 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-3 font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete server
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
