<script setup>
import {Link} from '@inertiajs/vue3'

defineProps({
    group: Object
});
</script>

<template>
    <div class="mb-3 cursor-pointer hover:bg-gray-100">
        <Link :href="route('group.profile', group.slug)" class="flex items-start gap-1 py-2 px-2">
            <img :src="group.thumbnail_url" class="w-[40px] rounded-full">
            <div class="flex-1">
                <div class="flex justify-between">
                    <h3 class="font-sans text-xl">{{ group.name }}</h3>
                    {{ group.status === 'approved' ? (group.role === 'admin' ? group.role : '') : (group.status === 'rejected' ? 'Đã từ chối' : 'Chờ phê duyệt') }}
                </div>
                <div class="text-xs text-gray-500">{{ group.description }}</div>
            </div>
        </Link>
    </div>
</template>

<style scoped>

</style>