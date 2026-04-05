<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({ user: Object })

const page = usePage()
const flash = computed(() => page.props.flash || {})
const telegramToken = ref(null)

// Watch flash for telegram token
watch(() => page.props.flash?.telegram_token, (token) => {
    if (token) telegramToken.value = token
}, { immediate: true })

const generateTelegramToken = () => {
    router.post(route('profile.telegram-token'), {}, { preserveScroll: true })
}

const unlinkTelegram = () => {
    confirmModal.value = {
        show: true,
        title: 'Unlink Telegram',
        message: 'Are you sure? You will no longer be able to use bot commands until you link again.',
        variant: 'danger',
        confirmText: 'Unlink',
        action: () => router.delete(route('profile.unlink-telegram'), { preserveScroll: true }),
    }
}

const copyToken = () => {
    if (telegramToken.value) {
        navigator.clipboard.writeText(`/link ${telegramToken.value}`)
    }
}
const auth = computed(() => page.props.auth)
const passkeys = computed(() => auth.value?.user?.passkeys || [])
const supportsPasskeys = typeof window !== 'undefined' && window.browserSupportsWebAuthn?.()

const passkeyRegistering = ref(false)
const passkeyError = ref('')

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const themeForm = useForm({
    theme_preference: props.user.theme_preference || 'auto',
})

const updateProfile = () => {
    profileForm.put(route('profile.update'), { preserveScroll: true })
}

const updatePassword = () => {
    passwordForm.put(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

watch(() => themeForm.theme_preference, (value) => {
    themeForm.put(route('profile.theme'), { preserveScroll: true })
})

const addPasskey = async () => {
    passkeyRegistering.value = true
    passkeyError.value = ''
    try {
        const optionsRes = await fetch(route('passkeys.register-options'))
        const options = await optionsRes.json()
        const registration = await window.startRegistration({ optionsJSON: options })
        const form = useForm({ options: JSON.stringify(options), passkey: JSON.stringify(registration) })
        form.post(route('passkeys.store'), {
            preserveScroll: true,
            onSuccess: () => { passkeyError.value = '' },
            onError: () => { passkeyError.value = 'Failed to register passkey.' },
        })
    } catch (e) {
        if (e.name !== 'NotAllowedError') {
            passkeyError.value = e.message || 'Failed to register passkey.'
        }
    } finally {
        passkeyRegistering.value = false
    }
}

const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', confirmText: 'Remove', action: null })
const onConfirm = () => { confirmModal.value.action?.(); confirmModal.value.show = false }
const onCancel = () => { confirmModal.value.show = false }

const deletePasskey = (id) => {
    confirmModal.value = {
        show: true,
        title: 'Remove passkey',
        message: 'Are you sure? You won\'t be able to sign in with this passkey anymore.',
        variant: 'danger',
        confirmText: 'Remove',
        action: () => router.delete(route('passkeys.destroy', id), { preserveScroll: true }),
    }
}
</script>

<template>
    <Head title="Profile Settings" />
    <AppLayout>
        <div class="max-w-2xl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Profile Settings</h1>

            <!-- Profile info -->
            <form @submit.prevent="updateProfile" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profile Information</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                    <input v-model="profileForm.name" type="text" required
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                    <p v-if="profileForm.errors.name" class="text-red-500 text-xs mt-1">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input v-model="profileForm.email" type="email" required
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                    <p v-if="profileForm.errors.email" class="text-red-500 text-xs mt-1">{{ profileForm.errors.email }}</p>
                </div>
                <button type="submit" :disabled="profileForm.processing"
                    class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm">
                    {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </form>

            <!-- Change password -->
            <form @submit.prevent="updatePassword" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Change Password</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                    <input v-model="passwordForm.current_password" type="password" required autocomplete="current-password"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                    <p v-if="passwordForm.errors.current_password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                    <input v-model="passwordForm.password" type="password" required autocomplete="new-password"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                    <p v-if="passwordForm.errors.password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                    <input v-model="passwordForm.password_confirmation" type="password" required autocomplete="new-password"
                        class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                </div>
                <button type="submit" :disabled="passwordForm.processing"
                    class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm">
                    {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                </button>
            </form>

            <!-- Passkeys -->
            <div v-if="supportsPasskeys" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5 mb-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Passkeys</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Sign in faster with biometric authentication. No password needed.</p>
                </div>

                <!-- Registered passkeys list -->
                <div v-if="passkeys.length" class="space-y-2">
                    <div v-for="pk in passkeys" :key="pk.id" class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ pk.name || 'Passkey' }}</div>
                                <div class="text-xs text-gray-400">{{ pk.last_used_at ? `Last used ${pk.last_used_at}` : 'Never used' }}</div>
                            </div>
                        </div>
                        <button @click="deletePasskey(pk.id)" class="text-sm text-red-500 hover:text-red-700 dark:hover:text-red-400 font-medium px-3 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            Remove
                        </button>
                    </div>
                </div>
                <p v-else class="text-center text-sm text-gray-400 dark:text-gray-500 py-4 bg-gray-50 dark:bg-gray-800 rounded-xl">No passkeys registered yet. Add one to enable biometric sign-in.</p>

                <div>
                    <button @click="addPasskey" :disabled="passkeyRegistering"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ passkeyRegistering ? 'Registering...' : 'Add Passkey' }}
                    </button>
                    <p v-if="passkeyError" class="text-red-500 text-xs mt-2 text-center">{{ passkeyError }}</p>
                </div>
            </div>

            <!-- Telegram Bot -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5 mb-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Telegram Bot</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Link your Telegram account to block/unblock IPs via chat commands.</p>
                </div>

                <!-- Linked state -->
                <div v-if="user.telegram_linked" class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-green-800 dark:text-green-300">Telegram linked</div>
                            <div class="text-xs text-green-600 dark:text-green-400">ID: {{ user.telegram_user_id }}</div>
                        </div>
                    </div>
                    <button @click="unlinkTelegram" class="text-sm text-red-500 hover:text-red-700 dark:hover:text-red-400 font-medium px-3 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        Unlink
                    </button>
                </div>

                <!-- Not linked state -->
                <div v-else class="space-y-4">
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            To link your account:
                        </p>
                        <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside">
                            <li>Click "Generate Token" below</li>
                            <li>Open a chat with the bot on Telegram</li>
                            <li>Send the link command shown</li>
                        </ol>
                    </div>

                    <!-- Generated token display -->
                    <div v-if="telegramToken" class="p-4 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl">
                        <p class="text-xs text-primary-600 dark:text-primary-400 mb-2 font-medium">Send this command to the bot (expires in 5 minutes):</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 text-sm bg-white dark:bg-gray-800 px-3 py-2 rounded-lg font-mono text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-700">/link {{ telegramToken }}</code>
                            <button @click="copyToken" class="px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors" title="Copy to clipboard">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            </button>
                        </div>
                    </div>

                    <button @click="generateTelegramToken"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors text-sm shadow-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                        {{ telegramToken ? 'Generate New Token' : 'Generate Token' }}
                    </button>
                </div>
            </div>

            <!-- Theme preference -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Theme Preference</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Choose your preferred color scheme. This will be remembered across sessions.</p>
                <div class="grid grid-cols-3 gap-3">
                    <label v-for="opt in [{value: 'light', label: 'Light', icon: 'sun'}, {value: 'dark', label: 'Dark', icon: 'moon'}, {value: 'auto', label: 'Auto', icon: 'monitor'}]"
                        :key="opt.value"
                        :class="[themeForm.theme_preference === opt.value ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-500' : 'border-gray-200 dark:border-gray-700']"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl border cursor-pointer hover:border-primary-400 transition-all">
                        <input v-model="themeForm.theme_preference" :value="opt.value" type="radio" name="theme" class="sr-only" />
                        <svg v-if="opt.icon === 'sun'" class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg v-if="opt.icon === 'moon'" class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        <svg v-if="opt.icon === 'monitor'" class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ opt.label }}</span>
                    </label>
                </div>
            </div>
        </div>

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
