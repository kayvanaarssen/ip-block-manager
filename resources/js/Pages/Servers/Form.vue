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

const publicKey = ref('')
const authorizedKeysCmd = ref('')
const generatingKey = ref(false)
const copied = ref('')
const keyGenerated = ref(false)

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
        reader.onload = (ev) => {
            form.ssh_private_key = ev.target.result
            // Clear generated key display if uploading manually
            publicKey.value = ''
            authorizedKeysCmd.value = ''
            keyGenerated.value = false
        }
        reader.readAsText(file)
    }
}

const generateKey = async () => {
    generatingKey.value = true

    try {
        if (isEdit) {
            // For existing servers, generate + save on server side
            const res = await fetch(route('servers.generate-key', props.server.id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
            })
            const data = await res.json()
            publicKey.value = data.public_key || ''
            authorizedKeysCmd.value = data.command || ''
            keyGenerated.value = true
        } else {
            // For new servers, generate a preview key pair and put private key in form
            const res = await fetch(route('servers.generate-key-preview'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
            })
            const data = await res.json()
            form.ssh_private_key = data.private_key || ''
            publicKey.value = data.public_key || ''
            authorizedKeysCmd.value = data.command || ''
            keyGenerated.value = true
        }
    } catch (e) {
        console.error('Key generation failed:', e)
    } finally {
        generatingKey.value = false
    }
}

const fetchPublicKey = async () => {
    if (!isEdit) return
    try {
        const res = await fetch(route('servers.public-key', props.server.id))
        const data = await res.json()
        publicKey.value = data.public_key || ''
        authorizedKeysCmd.value = data.command || ''
        if (data.public_key) keyGenerated.value = true
    } catch {}
}

const copyToClipboard = async (text, label) => {
    await navigator.clipboard.writeText(text)
    copied.value = label
    setTimeout(() => copied.value = '', 2000)
}

// Fetch public key on load if editing and server has one
if (isEdit && props.server?.has_public_key) {
    fetchPublicKey()
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

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Server details -->
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
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

                    <label class="flex items-center gap-3">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500 w-5 h-5" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Server is active</span>
                    </label>
                </div>

                <!-- SSH Key Section -->
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            SSH Key Pair
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Generate a unique RSA 4096-bit key pair, then add the public key to your server.</p>
                    </div>

                    <!-- Generate key pair button -->
                    <button type="button" @click="generateKey" :disabled="generatingKey"
                        class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm">
                        <svg v-if="!generatingKey" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ generatingKey ? 'Generating RSA 4096-bit key pair...' : (keyGenerated ? 'Regenerate Key Pair' : 'Generate SSH Key Pair') }}
                    </button>

                    <!-- Show public key + copy command when generated -->
                    <div v-if="publicKey" class="space-y-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5">
                        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span class="text-sm font-semibold">Key pair generated successfully</span>
                        </div>

                        <!-- Public key -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Public Key</label>
                                <button type="button" @click="copyToClipboard(publicKey, 'key')"
                                    class="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    {{ copied === 'key' ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                            <textarea readonly :value="publicKey" rows="3"
                                class="w-full px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-700 dark:text-gray-300 text-xs font-mono resize-none"></textarea>
                        </div>

                        <!-- One-liner command -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Add to server (one-liner)</label>
                                <button type="button" @click="copyToClipboard(authorizedKeysCmd, 'cmd')"
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs font-semibold rounded-lg hover:bg-gray-800 dark:hover:bg-white transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    {{ copied === 'cmd' ? 'Copied!' : 'Copy command' }}
                                </button>
                            </div>
                            <div class="px-4 py-3 bg-gray-900 dark:bg-gray-950 rounded-lg">
                                <code class="text-xs text-green-400 break-all leading-relaxed">{{ authorizedKeysCmd }}</code>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">SSH into the server and run this command to authorize the generated key.</p>
                        </div>
                    </div>

                    <!-- Key status for edit mode without generated key showing -->
                    <div v-else-if="isEdit && server?.has_key && !keyGenerated" class="flex items-center gap-2 px-3 py-2.5 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <svg class="w-4 h-4 text-green-600 dark:text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-xs text-green-700 dark:text-green-400 font-medium">SSH key is configured. Generate a new key pair above to get the public key and setup command.</span>
                    </div>

                    <!-- Divider -->
                    <div class="relative py-1">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-700"></div></div>
                        <div class="relative flex justify-center text-xs"><span class="bg-white dark:bg-gray-900 px-3 text-gray-400">or upload a private key manually</span></div>
                    </div>

                    <!-- Manual key upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            SSH Private Key
                            <span v-if="isEdit && server?.has_key" class="text-gray-400 font-normal">(leave empty to keep current)</span>
                        </label>
                        <textarea v-model="form.ssh_private_key" rows="4" :placeholder="keyGenerated ? 'Key generated above - will be saved on submit' : (isEdit ? '' : '-----BEGIN OPENSSH PRIVATE KEY-----')"
                            :class="[keyGenerated ? 'bg-green-50 dark:bg-green-900/10 border-green-300 dark:border-green-700' : 'bg-gray-50 dark:bg-gray-800 border-gray-300 dark:border-gray-700']"
                            class="w-full px-3 py-2.5 border rounded-xl text-gray-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none resize-none"></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <label class="inline-flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400 cursor-pointer hover:underline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                Upload key file
                                <input type="file" class="hidden" accept=".pem,.key,.pub,*" @change="handleFileUpload" />
                            </label>
                            <span v-if="keyGenerated && form.ssh_private_key" class="text-xs text-green-600 dark:text-green-400 font-medium">Generated key loaded</span>
                        </div>
                        <p v-if="form.errors.ssh_private_key" class="text-red-500 text-xs mt-1">{{ form.errors.ssh_private_key }}</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
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
