<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { startAuthentication } from '@simplewebauthn/browser'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const passkeyLoading = ref(false)
const passkeyError = ref('')

const loginWithPassword = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}

const loginWithPasskey = async () => {
    passkeyLoading.value = true
    passkeyError.value = ''
    try {
        const optionsRes = await fetch(route('login') + '/../webauthn/login/options', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'), 'Accept': 'application/json' },
        })
        const options = await optionsRes.json()
        const assertion = await startAuthentication({ optionsJSON: options })
        const verifyRes = await fetch(route('login') + '/../webauthn/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'), 'Accept': 'application/json' },
            body: JSON.stringify(assertion),
        })
        const result = await verifyRes.json()
        if (result.redirect) {
            window.location.href = result.redirect
        } else if (result.error) {
            passkeyError.value = result.error
        }
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            passkeyError.value = 'Passkey authentication was cancelled.'
        } else {
            passkeyError.value = e.message || 'Passkey authentication failed.'
        }
    } finally {
        passkeyLoading.value = false
    }
}

function getCookie(name) {
    const value = `; ${document.cookie}`
    const parts = value.split(`; ${name}=`)
    if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift())
}
</script>

<template>
    <Head title="Login" />
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-primary-900 px-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary-600/30">
                    <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h1 class="text-2xl font-bold text-white">IP Block Manager</h1>
                <p class="text-gray-400 mt-1 text-sm">Sign in to manage your servers</p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl p-6 space-y-6 border border-gray-200 dark:border-gray-800">
                <!-- Passkey login -->
                <button @click="loginWithPasskey" :disabled="passkeyLoading"
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                    {{ passkeyLoading ? 'Authenticating...' : 'Sign in with Passkey' }}
                </button>
                <p v-if="passkeyError" class="text-red-500 text-sm text-center">{{ passkeyError }}</p>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-700"></div></div>
                    <div class="relative flex justify-center text-xs"><span class="bg-white dark:bg-gray-900 px-3 text-gray-400">or sign in with password</span></div>
                </div>

                <!-- Password login -->
                <form @submit.prevent="loginWithPassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input v-model="form.email" type="email" required autofocus autocomplete="username"
                            class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none transition" />
                        <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                        <input v-model="form.password" type="password" required autocomplete="current-password"
                            class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none transition" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <input v-model="form.remember" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500" />
                        Remember me
                    </label>
                    <button type="submit" :disabled="form.processing"
                        class="w-full px-4 py-2.5 bg-gray-900 dark:bg-gray-100 hover:bg-gray-800 dark:hover:bg-white text-white dark:text-gray-900 font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm">
                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
