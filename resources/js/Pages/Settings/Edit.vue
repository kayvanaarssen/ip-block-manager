<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({ settings: Object })
const page = usePage()
const appSettings = computed(() => page.props.appSettings)

const form = useForm({
    app_name: props.settings.app_name || 'IPBlock',
    logo_light: null,
    logo_dark: null,
})

const lightPreview = ref(appSettings.value?.logo_light || null)
const darkPreview = ref(appSettings.value?.logo_dark || null)

const handleLogoUpload = (e, mode) => {
    const file = e.target.files[0]
    if (!file) return

    if (mode === 'light') {
        form.logo_light = file
        lightPreview.value = URL.createObjectURL(file)
    } else {
        form.logo_dark = file
        darkPreview.value = URL.createObjectURL(file)
    }
}

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        forceFormData: true,
    })
}

const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', confirmText: 'Remove', action: null })
const onConfirm = () => { confirmModal.value.action?.(); confirmModal.value.show = false }
const onCancel = () => { confirmModal.value.show = false }

const removeLogo = (type) => {
    confirmModal.value = {
        show: true,
        title: `Remove ${type} mode logo`,
        message: `Are you sure you want to remove the ${type} mode logo? The default icon will be used instead.`,
        variant: 'warning',
        confirmText: 'Remove',
        action: () => {
            router.delete(route('settings.remove-logo'), {
                data: { type },
                preserveScroll: true,
                onSuccess: () => {
                    if (type === 'light') lightPreview.value = null
                    else darkPreview.value = null
                },
            })
        },
    }
}
</script>

<template>
    <Head title="Application Settings" />
    <AppLayout>
        <div class="max-w-2xl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Application Settings</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- App name -->
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Application Name</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">This name appears in the sidebar, login page, and browser tab.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <input v-model="form.app_name" type="text" required maxlength="100"
                            class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none" />
                        <p v-if="form.errors.app_name" class="text-red-500 text-xs mt-1">{{ form.errors.app_name }}</p>
                    </div>
                </div>

                <!-- Logo light mode -->
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Logo - Light Mode</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Shown in the sidebar when light mode is active. Recommended: SVG or PNG with transparent background, max 2MB.</p>
                    </div>

                    <!-- Current / Preview -->
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center bg-white overflow-hidden">
                            <img v-if="lightPreview" :src="lightPreview" alt="Light logo" class="w-full h-full object-contain p-1" />
                            <div v-else class="text-center">
                                <svg class="w-8 h-8 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <label class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl cursor-pointer transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                Upload logo
                                <input type="file" class="hidden" accept="image/svg+xml,image/png,image/jpeg,image/webp" @change="e => handleLogoUpload(e, 'light')" />
                            </label>
                            <button v-if="lightPreview" type="button" @click="removeLogo('light')"
                                class="text-xs text-red-500 hover:text-red-700 font-medium">Remove logo</button>
                        </div>
                    </div>
                    <p v-if="form.errors.logo_light" class="text-red-500 text-xs">{{ form.errors.logo_light }}</p>
                </div>

                <!-- Logo dark mode -->
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 space-y-5">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Logo - Dark Mode</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Shown in the sidebar when dark mode is active. Use a light-colored or white version of your logo.</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-600 flex items-center justify-center bg-gray-900 overflow-hidden">
                            <img v-if="darkPreview" :src="darkPreview" alt="Dark logo" class="w-full h-full object-contain p-1" />
                            <div v-else class="text-center">
                                <svg class="w-8 h-8 text-gray-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <label class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl cursor-pointer transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                Upload logo
                                <input type="file" class="hidden" accept="image/svg+xml,image/png,image/jpeg,image/webp" @change="e => handleLogoUpload(e, 'dark')" />
                            </label>
                            <button v-if="darkPreview" type="button" @click="removeLogo('dark')"
                                class="text-xs text-red-500 hover:text-red-700 font-medium">Remove logo</button>
                        </div>
                    </div>
                    <p v-if="form.errors.logo_dark" class="text-red-500 text-xs">{{ form.errors.logo_dark }}</p>
                </div>

                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50 text-sm shadow-sm">
                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                </button>
            </form>
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
