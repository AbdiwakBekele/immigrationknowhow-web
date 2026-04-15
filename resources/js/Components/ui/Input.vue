<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    helper: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    id: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
const attrs = useAttrs();

const inputId = computed(() => {
    if (props.id) return props.id;
    if (attrs.id) return attrs.id;
    if (props.label) {
        return props.label.toLowerCase().replace(/\s+/g, '-');
    }
    return undefined;
});

const updateValue = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <div class="w-full">
        <label
            v-if="label"
            :for="inputId"
            class="mb-3 block text-base font-medium text-slate-700"
        >
            {{ label }}
            <span v-if="$attrs.required" class="text-red-500">*</span>
        </label>

        <input
            :id="inputId"
            v-bind="attrs"
            :type="type"
            :value="modelValue"
            @input="updateValue"
            :class="[
                'w-full rounded-2xl border bg-white/95 px-5 py-4 text-base text-slate-900 shadow-sm outline-none transition duration-200',
                'placeholder:text-slate-400',
                error
                    ? 'border-red-300 focus:border-red-400 focus:ring-4 focus:ring-red-100'
                    : 'border-slate-200 focus:border-blue-400 focus:ring-4 focus:ring-blue-100',
            ]"
        />

        <p v-if="helper && !error" class="mt-2 text-sm text-slate-500">
            {{ helper }}
        </p>

        <p v-if="error" class="mt-2 text-sm font-medium text-red-600">
            {{ error }}
        </p>
    </div>
</template>