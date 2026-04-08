<script setup>
const props = defineProps({
    currentStep: {
        type: Number,
        required: true,
    },
    totalSteps: {
        type: Number,
        default: 4,
    },
});
</script>

<template>
    <div class="flex items-center justify-center gap-2" aria-label="Signup progress">
        <template v-for="step in totalSteps" :key="step">
            <div
                :class="[
                    'flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold transition-colors duration-200',
                    step <= currentStep ? 'bg-primary-600 text-white' : 'bg-neutral-300 text-neutral-700',
                ]"
            >
                {{ step }}
            </div>
            <div
                v-if="step < totalSteps"
                :class="['h-0.5 w-6 rounded-full', step < currentStep ? 'bg-primary-500' : 'bg-neutral-200']"
            />
        </template>
    </div>
</template>
