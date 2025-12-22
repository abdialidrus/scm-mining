<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout title="Verify email" description="Disabled">
        <Head title="Email verification" />

        <div class="text-sm text-muted-foreground">
            Email verification is disabled for this system.
        </div>
    </AuthLayout>
</template>
