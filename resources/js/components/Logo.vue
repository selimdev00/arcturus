<script setup>
defineProps({ glow: { type: Boolean, default: true } });
</script>

<template>
    <span class="inline-flex items-center gap-2 select-none">
        <svg viewBox="0 0 24 24" class="h-5 w-5" :class="glow && 'star-glow'" aria-hidden="true">
            <!-- four-point star — Arcturus -->
            <path
                d="M12 1.5c.5 4.6 2.9 7 7.5 7.5-4.6.5-7 2.9-7.5 7.5-.5-4.6-2.9-7-7.5-7.5 4.6-.5 7-2.9 7.5-7.5Z"
                transform="translate(0 1.5)"
                fill="oklch(0.78 0.15 75)" />
        </svg>
        <span class="text-[0.95rem] font-semibold tracking-tight text-[var(--ink)]">Arcturus</span>
    </span>
</template>
