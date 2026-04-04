<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({ users: Array })

const confirmModal = ref({ show: false, title: '', message: '', variant: 'danger', confirmText: 'Delete', action: null })

const confirmDelete = (user) => {
    confirmModal.value = {
        show: true,
        title: 'Delete user',
        message: `Are you sure you want to delete "${user.name}"? This cannot be undone.`,
        variant: 'danger',
        confirmText: 'Delete',
        action: () => router.delete(route('users.destroy', user.id)),
    }
}
const onConfirm = () => {
    confirmModal.value.action?.()
    confirmModal.value.show = false
}
const onCancel = () => {
    confirmModal.value.show = false
}
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Users</h1>
            <Link :href="route('users.create')" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                Add User
            </Link>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">Email</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Created</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ user.name }}</td>
                        <td class="px-5 py-3 text-gray-500 dark:text-gray-400 hidden sm:table-cell">{{ user.email }}</td>
                        <td class="px-5 py-3 text-gray-400 dark:text-gray-500 text-xs hidden md:table-cell">{{ user.created_at }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <Link :href="route('users.edit', user.id)" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">Edit</Link>
                                <button @click="confirmDelete(user)" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
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
