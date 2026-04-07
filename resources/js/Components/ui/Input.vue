<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    label: String,
    placeholder: String,
    error: String,
    helper: String,
    required: Boolean,
    disabled: Boolean,
    id: String,
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || `input-${Math.random().toString(36).substr(2, 9)}`);

const inputClasses = computed(() => {
    const base = 'w-full px-4 py-3 text-sm bg-white border rounded-xl transition-all duration-200 placeholder:text-neutral-400 focus:outline-none';
    
    if (props.error) {
        return `${base} border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-500/20`;
    }
    
    return `${base} border-neutral-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20`;
});

const handleInput = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="inputId" class="block text-sm font-medium text-neutral-700">
            {{ label }}
            <span v-if="required" class="text-red-500 ml-0.5">*</span>
        </label>
        
        <div class="relative">
            <slot name="prefix" />
            
            <input
                :id="inputId"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :class="inputClasses"
                @input="handleInput"
            />
            
            <slot name="suffix" />
        </div>
        
        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        <p v-else-if="helper" class="text-xs text-neutral-500">{{ helper }}</p>
    </div>
</template>
