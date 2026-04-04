<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ logs: Object })

const actionLabels = {
    block_ip: { label: 'Block IP', color: 'text-red-600 dark:text-red-400' },
    unblock_ip: { label: 'Unblock IP', color: 'text-green-600 dark:text-green-400' },
    create_server: { label: 'Add Server', color: 'text-blue-600 dark:text-blue-400' },
    update_server: { label: 'Update Server', color: 'text-blue-600 dark:text-blue-400' },
    delete_server: { label: 'Delete Server', color: 'text-red-600 dark:text-red-400' },
    login: { label: 'Login', color: 'text-gray-600 dark:text-gray-400' },
    logout: { label: 'Logout', color: 'text-gray-600 dark:text-gray-400' },
    register_passkey: { label: 'Register Passkey', color: 'text-purple-600 dark:text-purple-400' },
    install_script: { label: 'Install Script', color: 'text-blue-600 dark:text-blue-400' },
}
</script>

<template>
    <Head title="Audit Log" />
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Audit Log</h1>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">Details</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">User</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">From IP</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                <span :class="actionLabels[log.action]?.color || 'text-gray-600 dark:text-gray-400'" class="font-medium">
                                    {{ actionLabels[log.action]?.label || log.action }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400 hidden sm:table-cell">
                                <span v-if="log.metadata?.ip_address" class="font-mono text-xs">{{ log.metadata.ip_address }}</span>
                                <span v-else-if="log.metadata?.name" class="text-xs">{{ log.metadata.name }}</span>
                                <span v-else-if="log.target_type" class="text-xs">{{ log.target_type }} #{{ log.target_id }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ log.user }}</td>
                            <td class="px-5 py-3 text-gray-400 dark:text-gray-500 font-mono text-xs hidden lg:table-cell">{{ log.ip_address }}</td>
                            <td class="px-5 py-3 text-gray-400 dark:text-gray-500 text-xs text-right whitespace-nowrap">{{ log.created_at }}</td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="5" class="px-5 py-12 text-center text-gray-400 dark:text-gray-500">No audit log entries</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="logs.links?.length > 3" class="px-5 py-3 border-t border-gray-200 dark:border-gray-800 flex justify-center gap-1">
                <template v-for="link in logs.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" v-html="link.label"
                        :class="[link.active ? 'bg-primary-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800']"
                        class="px-3 py-1 rounded-lg text-sm" />
                    <span v-else v-html="link.label" class="px-3 py-1 text-gray-300 dark:text-gray-600 text-sm" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
