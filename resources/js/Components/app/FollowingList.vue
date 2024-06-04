<script setup>
import FollowingListItems from "@/Components/app/FollowingListItems.vue";
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'

defineProps({
    users: Array
});
</script>

<template>
    <div class="px-3 bg-white rounded border h-full py-3 overflow-hidden">
        <!-- Mobile -->
        <div class="block lg:hidden">
            <Disclosure v-slot="{ open }">
                <DisclosureButton class="w-full">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold">Đang theo dõi</h2>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" :class="open ? 'rotate-90 transform' : ''" class="w-6 h-6 transition-all">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </div>
                </DisclosureButton>
                <DisclosurePanel>
                    <FollowingListItems :users="users"/>
                </DisclosurePanel>
            </Disclosure>
        </div>
        <!-- Pc -->
        <div class="h-full overflow-hidden flex flex-col lg:flex">
            <h2 class="text-xl font-bold">Đang theo dõi</h2>
            <FollowingListItems :users="users"/>
        </div>
    </div>
</template>

<style scoped>

</style>