<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ blockedIps: Object, filters: Object })

const search = ref(props.filters?.search || '')

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    blocking: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    blocked: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    failed: 'bg-red-200 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    unblocking: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    unblocked: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
}

const confirmUnblockAll = (ip) => {
    if (confirm(`Unblock ${ip.ip_address} from ALL servers?`)) {
        router.delete(route('blocked-ips.destroy', ip.id))
    }
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
                        <tr v-for="ip in blockedIps.data" :key="ip.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                <Link :href="route('blocked-ips.show', ip.id)" class="font-mono font-semibold text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ ip.ip_address }}
                                </Link>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400 max-w-[200px] truncate hidden sm:table-cell">{{ ip.reason || '-' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="s in ip.servers" :key="s.id" :class="statusColors[s.status]"
                                        class="text-xs font-medium px-2 py-0.5 rounded-full" :title="s.error_message || s.status">
                                        {{ s.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ ip.blocked_by }}</td>
                            <td class="px-5 py-3 text-gray-400 dark:text-gray-500 text-xs hidden lg:table-cell">{{ ip.created_at }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('blocked-ips.show', ip.id)" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">View</Link>
                                    <button @click="confirmUnblockAll(ip)" class="text-xs text-green-600 dark:text-green-400 hover:underline font-medium">Unblock All</button>
                                </div>
                            </td>
                        </tr>
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
    </AppLayout>
</template>
