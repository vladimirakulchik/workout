<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = defineProps<{
    value: string;
}>();

const formatted = ref('');

onMounted(() => {
    formatted.value = new Date(props.value).toLocaleString();
});
</script>

<template>
    <time :datetime="value">{{ formatted }}</time>
</template>
