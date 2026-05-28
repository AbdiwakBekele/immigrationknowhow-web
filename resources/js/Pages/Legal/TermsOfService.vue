<script setup>
import LegalPageLayout from '@/Components/legal/LegalPageLayout.vue';
import { termsOfServiceDocument } from '@/content/legal/termsOfService';
</script>

<template>
    <LegalPageLayout :document="termsOfServiceDocument" />
</template>
