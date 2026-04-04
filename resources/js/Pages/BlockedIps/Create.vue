<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ servers: Array })

const form = useForm({
    ip_address: '',
    server_ids: [],
    reason: '',
})

const allSelected = computed({
    get: () => form.server_ids.length === props.servers.length && props.servers.length > 0,
    set: (val) => { form.server_ids = val ? props.servers.map(s => s.id) : [] }
})

const submit = () => form.post(route('blocked-ips.store'))
</script>

<template>
    <Head title="Block IP" />
    <AppLayout>
        <div class="max-w-2xl">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('blocked-ips.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Block IP Address</h1>
            </div>

            <form @submit.prevent="submit" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP Address or CIDR</label>
                    <input v-model="form.ip_address" type="text" required placeholder="e.g. 1.2.3.4 or 10.0.0.0/24 or 2001:db8::1"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                    <p v-if="form.errors.ip_address" class="text-red-500 text-xs mt-1">{{ form.errors.ip_address }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason (optional)</label>
                    <input v-model="form.reason" type="text" placeholder="e.g. DDoS attack, brute force, etc."
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Target Servers</label>
                        <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 cursor-pointer">
                            <input v-model="allSelected" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500" />
                            Select All
                        </label>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="server in servers" :key="server.id"
                            :class="[form.server_ids.includes(server.id) ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700']"
                            class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer hover:border-primary-400 transition-colors">
                            <input v-model="form.server_ids" :value="server.id" type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500" />
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ server.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ server.host }}</div>
                            </div>
                        </label>
                    </div>
                    <p v-if="form.errors.server_ids" class="text-red-500 text-xs mt-1">{{ form.errors.server_ids }}</p>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button type="submit" :disabled="form.processing || !form.server_ids.length"
                        class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm shadow-sm">
                        {{ form.processing ? 'Blocking...' : `Block on ${form.server_ids.length} Server(s)` }}
                    </button>
                    <Link :href="route('blocked-ips.index')" class="px-4 py-2.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm font-medium">Cancel</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
