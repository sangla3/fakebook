<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    user: Object,
    forApprove: {
        type: Boolean,
        default: false
    },
    showRoleDropdown: {
        type: Boolean,
        default: false
    },
    disableRoleDropdown: {
        type: Boolean,
        default: false
    }
})

defineEmits(['approve', 'reject', 'roleChange', 'delete']);
</script>

<template>
    <div v-if="user.status !== 'rejected'" class="cursor-pointer bg-white border border-gray-300 hover:bg-gray-100 mb-2">
        <div class="flex items-center gap-2 py-2 px-2">
            <Link :href="route('profile', user.username)">
                <img :src="user.avatar_url" class="w-[40px] h-[40px] rounded-full object-cover">
            </Link>
            <div class="flex justify-between flex-1 ">
                <Link :href="route('profile', user.username)">
                    <h3 class="font-black hover:underline">{{ user.name }}</h3>
                </Link>
                <div v-if="user.status == 'pending'">
                    <p>Chờ xác nhận</p>
                </div>
                <div v-if="forApprove" class="flex gap-1">
                    <button @click.prevent.stop="$emit('approve', user)" class="py-1 px-2 text-xs text-white rounded bg-emerald-500 hover:bg-emerald-600">
                        Chấp nhận
                    </button>
                    <button @click.prevent.stop="$emit('reject', user)" class="py-1 px-2 text-xs text-white rounded bg-red-500 hover:bg-red-600">
                        Từ chối
                    </button>
                </div>
                <div v-if="showRoleDropdown && user.status == 'approved'">
                    <select @change="$emit('roleChange', user, $event.target.value)"
                            class="rounded-md border-0 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 max-w-xs text-sm leading-6"
                            :disabled="disableRoleDropdown">
                        <option :selected="user.role === 'admin'">admin</option>
                        <option :selected="user.role === 'user'">user</option>
                    </select>
                    <button v-if="!disableRoleDropdown" @click="$emit('delete', user)"
                            class="text-xs py-1.5 px-2 rounded bg-gray-700 hover:bg-gray-800 text-white ml-3 disabled:bg-gray-500"
                            :disabled="disableRoleDropdown">Xóa
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>