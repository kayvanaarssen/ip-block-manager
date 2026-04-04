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
}
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                <div class="text-sm text-gray-500 dark:text-gray-400">Active Servers</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.totalServers }}</div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                <div class="text-sm text-gray-500 dark:text-gray-400">Blocked IPs</div>
                <div class="text-3xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.totalBlockedIps }}</div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 col-span-2 lg:col-span-1">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Block Records</div>
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
