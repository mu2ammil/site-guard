<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <svg
        :class="className"
        v-bind="$attrs"
        viewBox="0 0 32 36"
        fill="none"
        aria-hidden="true"
    >
        <path
            d="M16 2 29 7v10c0 8-5.2 13.5-13 17C8.2 30.5 3 25 3 17V7L16 2Z"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linejoin="round"
        />
    </svg>
</template>
