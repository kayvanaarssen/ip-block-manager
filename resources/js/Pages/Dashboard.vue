<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ stats: Object })

const actionLabels = {
    block_ip: 'Blocked IP',
    unblock_ip: 'Unblocked IP',
    create_server: 'Added server',
    delete_server: 'Removed server',
    update_server: 'Updated server',
    login: 'Logged in',
    logout: 'Logged out',
    install_script: 'Installed script',
    create_user: 'Created user',
    update_user: 'Updated user',
    delete_user: 'Deleted user',
    update_profile: 'Updated profile',
    change_password: 'Changed password',
    generate_ssh_key: 'Generated SSH key',
    reblock_ip: 'Re-blocked IP',
    unlink_telegram: 'Unlinked Telegram',
}
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                <div class="text-sm text-gray-500 dark:text-gray-400">Active Servers</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.totalServers }}</div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                <div class="text-sm text-gray-500 dark:text-gray-400">Blocked IPs</div>
                <div class="text-3xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.activelyBlockedIps }}</div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                <div class="text-sm text-gray-500 dark:text-gray-400">Pending / Failed</div>
                <div class="text-3xl font-bold mt-1">
                    <span v-if="stats.pendingIps" class="text-yellow-600 dark:text-yellow-400">{{ stats.pendingIps }}</span>
                    <span v-if="stats.pendingIps && stats.failedIps" class="text-gray-400 dark:text-gray-500 text-lg mx-1">/</span>
                    <span v-if="stats.failedIps" class="text-red-600 dark:text-red-400">{{ stats.failedIps }}</span>
                    <span v-if="!stats.pendingIps && !stats.failedIps" class="text-green-600 dark:text-green-400">0</span>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Records</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.totalBlocks }}</div>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <div v-for="activity in stats.recentActivity" :key="activity.id" class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ actionLabels[activity.action] || activity.action }}</span>
                        <span v-if="activity.metadata?.ip_address" class="text-sm text-gray-500 dark:text-gray-400 ml-1">{{ activity.metadata.ip_address }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500 ml-2">by {{ activity.user }}</span>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ activity.created_at }}</span>
                </div>
                <div v-if="!stats.recentActivity?.length" class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">No activity yet</div>
            </div>
        </div>
    </AppLayout>
</template>
