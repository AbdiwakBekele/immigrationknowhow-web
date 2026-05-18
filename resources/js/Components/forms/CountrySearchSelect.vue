<script setup>
import { computed, ref } from 'vue';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/vue';
import { ChevronDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';

const model = defineModel({ type: String, default: 'US' });

const props = defineProps({
    options: {
        type: Array,
        required: true,
    },
    label: {
        type: String,
        default: 'Country',
    },
    required: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: 'Search countries…',
    },
});

const searchQuery = ref('');

const flagClass = (iso2) => `fi fi-${String(iso2 || '').toLowerCase()}`;

const selectedOption = computed(() => {
    const match = props.options.find((option) => option.value === model.value);
    if (match) {
        return match;
    }

    return props.options.find((option) => option.value === 'US') ?? props.options[0] ?? null;
});

const filteredOptions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) {
        return props.options;
    }

    return props.options.filter((option) => {
        const label = String(option.label ?? '').toLowerCase();
        const value = String(option.value ?? '').toLowerCase();

        return label.includes(query) || value.includes(query);
    });
});

if (!model.value) {
    model.value = 'US';
}
</script>

<template>
    <div class="space-y-1.5">
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
            {{ label }}
            <span v-if="required" class="text-rose-600">*</span>
        </label>
        <Listbox v-model="model" as="div" class="relative">
            <ListboxButton
                type="button"
                class="admin-select flex w-full items-center gap-2 text-left"
            >
                <span
                    v-if="selectedOption"
                    :class="[flagClass(selectedOption.value), 'h-4 w-5 shrink-0 rounded-sm']"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate">
                    {{ selectedOption?.label ?? 'Select country' }}
                </span>
                <ChevronDownIcon class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
            </ListboxButton>
            <ListboxOptions
                class="absolute z-30 mt-1 max-h-72 w-full overflow-hidden rounded-xl bg-white text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
            >
                <div class="sticky top-0 border-b border-slate-100 bg-white p-2">
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="searchQuery"
                            type="search"
                            class="w-full rounded-lg border border-slate-200 py-2 pl-8 pr-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            :placeholder="placeholder"
                            @keydown.stop
                            @click.stop
                        >
                    </div>
                </div>
                <div class="max-h-56 overflow-y-auto py-1">
                    <ListboxOption
                        v-for="option in filteredOptions"
                        :key="option.value"
                        v-slot="{ active, selected }"
                        :value="option.value"
                        as="template"
                    >
                        <li
                            :class="[
                                'cursor-pointer select-none px-3 py-2',
                                active ? 'bg-blue-50 text-blue-900' : 'text-slate-900',
                                selected ? 'font-semibold' : '',
                            ]"
                        >
                            <span class="flex items-center gap-2">
                                <span :class="[flagClass(option.value), 'h-4 w-5 shrink-0 rounded-sm']" aria-hidden="true" />
                                <span class="truncate">{{ option.label }}</span>
                            </span>
                        </li>
                    </ListboxOption>
                    <p v-if="filteredOptions.length === 0" class="px-3 py-4 text-center text-xs text-slate-500">
                        No countries match your search.
                    </p>
                </div>
            </ListboxOptions>
        </Listbox>
    </div>
</template>
