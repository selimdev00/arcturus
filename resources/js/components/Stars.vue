<script setup>
defineProps({ value: { type: Number, default: 0 } });
</script>

<template>
    <span class="inline-flex gap-0.5" :aria-label="`${value} из 5`">
        <svg v-for="i in 5" :key="i" viewBox="0 0 20 20" class="h-3.5 w-3.5" aria-hidden="true">
            <path
                d="M10 1.5l2.47 5.2 5.53.62-4.1 3.78 1.1 5.4L10 19.3 4.99 16.5l1.1-5.4-4.1-3.78 5.53-.62L10 1.5Z"
                :fill="i <= value ? 'var(--star)' : 'var(--border-strong)'" />
        </svg>
    </span>
</template>
