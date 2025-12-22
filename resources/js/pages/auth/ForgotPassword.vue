<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout title="Forgot password" description="Disabled">
        <Head title="Forgot password" />

        <div class="space-y-4 text-sm text-muted-foreground">
            <p>Password reset is disabled for this system.</p>
            <p>
                <Link :href="login()" class="underline underline-offset-4">
                    Go to login
                </Link>
            </p>
        </div>
    </AuthLayout>
</template>
