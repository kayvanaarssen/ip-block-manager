<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ servers: Array })

const confirmDelete = (server) => {
    if (confirm(`Delete server "${server.name}"? This cannot be undone.`)) {
        router.delete(route('servers.destroy', server.id))
    }
}
</script>

<template>
    <Head title="Servers" />
    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Servers</h1>
            <Link :href="route('servers.create')" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                Add Server
            </Link>
        </div>

        <div v-if="servers.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="server in servers" :key="server.id" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ server.name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ server.host }}:{{ server.port }}</p>
                    </div>
                    <span :class="[server.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500']"
                        class="text-xs font-medium px-2 py-1 rounded-full">
                        {{ server.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mb-4">
                    <span>{{ server.ssh_user }}@</span>
                    <span :class="server.script_installed ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'">
                        {{ server.script_installed ? 'Script OK' : 'No script' }}
                    </span>
                    <span v-if="server.blocked_ips_count" class="text-red-600 dark:text-red-400">{{ server.blocked_ips_count }} blocked</span>
                </div>
                <div v-if="server.last_connected_at" class="text-xs text-gray-400 dark:text-gray-500 mb-3">Last connected {{ server.last_connected_at }}</div>
                <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <Link :href="route('servers.edit', server.id)" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">Edit</Link>
                    <button @click="router.post(route('servers.test', server.id))" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium">Test</button>
                    <button @click="router.post(route('servers.install-script', server.id))" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium">Install Script</button>
                    <button @click="confirmDelete(server)" class="text-xs text-red-500 hover:text-red-700 font-medium ml-auto">Delete</button>
                </div>
            </div>
        </div>

        <div v-else class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            <p class="text-gray-500 dark:text-gray-400 mb-4">No servers configured yet</p>
            <Link :href="route('servers.create')" class="text-primary-600 dark:text-primary-400 font-medium hover:underline">Add your first server</Link>
        </div>
    </AppLayout>
</template>
