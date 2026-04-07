<script setup>
import { computed } from 'vue';
import {
    Listbox,
    ListboxButton,
    ListboxLabel,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: {
        type: [String, Number, Object, Array],
        default: null,
    },
    options: {
        type: Array,
        required: true,
    },
    label: String,
    placeholder: {
        type: String,
        default: 'Select an option',
    },
    error: String,
    required: Boolean,
    disabled: Boolean,
    multiple: Boolean,
    valueKey: {
        type: String,
        default: 'value',
    },
    labelKey: {
        type: String,
        default: 'label',
    },
});

const emit = defineEmits(['update:modelValue']);

const selectedLabel = computed(() => {
    if (!props.modelValue) return null;
    
    if (props.multiple && Array.isArray(props.modelValue)) {
        if (props.modelValue.length === 0) return null;
        const selected = props.options.filter(opt => 
            props.modelValue.includes(typeof opt === 'object' ? opt[props.valueKey] : opt)
        );
        return selected.map(opt => typeof opt === 'object' ? opt[props.labelKey] : opt).join(', ');
    }
    
    const selected = props.options.find(opt => {
        const value = typeof opt === 'object' ? opt[props.valueKey] : opt;
        return value === props.modelValue;
    });
    
    return selected ? (typeof selected === 'object' ? selected[props.labelKey] : selected) : null;
});

const handleChange = (value) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <Listbox :model-value="modelValue" @update:model-value="handleChange" :multiple="multiple" :disabled="disabled">
        <div class="relative">
            <ListboxLabel v-if="label" class="block text-sm font-medium text-neutral-700 mb-1.5">
                {{ label }}
                <span v-if="required" class="text-red-500 ml-0.5">*</span>
            </ListboxLabel>
            
            <ListboxButton
                :class="[
                    'relative w-full cursor-pointer rounded-xl bg-white py-3 pl-4 pr-10 text-left border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 text-sm',
                    error ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20' : 'border-neutral-300 focus:border-primary-500 focus:ring-primary-500/20',
                    disabled ? 'bg-neutral-100 cursor-not-allowed' : ''
                ]"
            >
                <span :class="['block truncate', selectedLabel ? 'text-neutral-900' : 'text-neutral-400']">
                    {{ selectedLabel || placeholder }}
                </span>
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                    <ChevronUpDownIcon class="h-5 w-5 text-neutral-400" aria-hidden="true" />
                </span>
            </ListboxButton>

            <transition
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <ListboxOptions
                    class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none text-sm"
                >
                    <ListboxOption
                        v-for="option in options"
                        v-slot="{ active, selected }"
                        :key="typeof option === 'object' ? option[valueKey] : option"
                        :value="typeof option === 'object' ? option[valueKey] : option"
                        as="template"
                    >
                        <li
                            :class="[
                                'relative cursor-pointer select-none py-2.5 pl-10 pr-4',
                                active ? 'bg-primary-50 text-primary-900' : 'text-neutral-900',
                            ]"
                        >
                            <span :class="['block truncate', selected ? 'font-semibold' : 'font-normal']">
                                {{ typeof option === 'object' ? option[labelKey] : option }}
                            </span>
                            <span
                                v-if="selected"
                                class="absolute inset-y-0 left-0 flex items-center pl-3 text-primary-600"
                            >
                                <CheckIcon class="h-5 w-5" aria-hidden="true" />
                            </span>
                        </li>
                    </ListboxOption>
                </ListboxOptions>
            </transition>
            
            <p v-if="error" class="text-xs text-red-600 mt-1">{{ error }}</p>
        </div>
    </Listbox>
</template>
