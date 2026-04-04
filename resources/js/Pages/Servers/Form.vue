<script setup>
import { ref } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ server: { type: Object, default: null } })
const isEdit = !!props.server

const form = useForm({
    name: props.server?.name || '',
    host: props.server?.host || '',
    port: props.server?.port || 22,
    ssh_user: props.server?.ssh_user || 'root',
    ssh_private_key: '',
    is_active: props.server?.is_active ?? true,
})

const submit = () => {
    if (isEdit) {
        form.put(route('servers.update', props.server.id))
    } else {
        form.post(route('servers.store'))
    }
}

const handleFileUpload = (e) => {
    const file = e.target.files[0]
    if (file) {
        const reader = new FileReader()
        reader.onload = (ev) => form.ssh_private_key = ev.target.result
        reader.readAsText(file)
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Server' : 'Add Server'" />
    <AppLayout>
        <div class="max-w-2xl">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('servers.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ isEdit ? 'Edit Server' : 'Add Server' }}</h1>
            </div>

            <form @submit.prevent="submit" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Server Name</label>
                    <input v-model="form.name" type="text" required placeholder="e.g. web-01"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Host</label>
                        <input v-model="form.host" type="text" required placeholder="192.168.1.1 or hostname"
                            class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                        <p v-if="form.errors.host" class="text-red-500 text-xs mt-1">{{ form.errors.host }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Port</label>
                        <input v-model.number="form.port" type="number" required min="1" max="65535"
                            class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SSH User</label>
                    <input v-model="form.ssh_user" type="text" required
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        SSH Private Key
                        <span v-if="isEdit && server.has_key" class="text-gray-400 font-normal">(leave empty to keep current)</span>
                    </label>
                    <textarea v-model="form.ssh_private_key" rows="4" :placeholder="isEdit ? '' : '-----BEGIN OPENSSH PRIVATE KEY-----'"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none resize-none"></textarea>
                    <div class="mt-2">
                        <label class="inline-flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400 cursor-pointer hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            Upload key file
                            <input type="file" class="hidden" accept=".pem,.key,.pub,*" @change="handleFileUpload" />
                        </label>
                    </div>
                    <p v-if="form.errors.ssh_private_key" class="text-red-500 text-xs mt-1">{{ form.errors.ssh_private_key }}</p>
                </div>

                <label class="flex items-center gap-3">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500 w-5 h-5" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Server is active</span>
                </label>

                <div class="flex items-center gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm shadow-sm">
                        {{ form.processing ? 'Saving...' : (isEdit ? 'Update Server' : 'Add Server') }}
                    </button>
                    <Link :href="route('servers.index')" class="px-4 py-2.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm font-medium">Cancel</Link>

                    <template v-if="isEdit">
                        <button type="button" @click="router.post(route('servers.test', server.id))"
                            class="ml-auto px-4 py-2 text-xs font-medium text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Test Connection
                        </button>
                    </template>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
