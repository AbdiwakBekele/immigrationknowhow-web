<script setup>
import LegalPageLayout from '@/Components/legal/LegalPageLayout.vue';
import { privacyPolicyDocument } from '@/content/legal/privacyPolicy';
</script>

<template>
    <LegalPageLayout :document="privacyPolicyDocument" />
</template>
