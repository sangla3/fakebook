<script setup>
import UserListItem from "@/Components/app/UserListItem.vue";
import TextInput from "@/Components/TextInput.vue";
import { ref } from "vue";

const searchKeyword = ref('');

defineProps({
    users: Array,
    allUser: Array
});
</script>

<template>
    <TextInput :model-value="searchKeyword" placeholder="Tìm kiếm" class="w-full mt-4"/>
    <div class="mt-3 h-[200px] lg:flex-1 overflow-auto">
        <div v-if="!users" class="text-gray-400 flex flex-center justify-center p-3">
            Bạn chưa có người bạn nào
        </div>
        <div v-else>
            <UserListItem v-for="user of users"
                                :user="user"
                                :key="user.id"
                                class="shadow rounded-lg"/>
        </div>
    </div>
    <div class="flex items-center justify-start mt-2">
        <h2 class="text-xl font-bold">Mọi người</h2>
    </div>
    <div class="mt-3 h-[200px] lg:flex-1 overflow-auto">
        <div>
            <UserListItem v-for="user of allUser"
                            :user="user"
                            :key="user.id"
                            class="shadow rounded-lg"/>
        </div>
    </div>
</template>

<style scoped>

</style>