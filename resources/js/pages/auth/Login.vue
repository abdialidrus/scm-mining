<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { Form, Head } from '@inertiajs/vue3';
import { Building2, Lock, Mail, ShieldCheck } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        class="flex min-h-screen bg-linear-to-br from-slate-50 to-slate-100 dark:from-slate-950 dark:to-slate-900"
    >
        <!-- Left Panel - Branding -->
        <div
            class="hidden w-1/2 bg-linear-to-br from-blue-600 to-blue-800 p-12 lg:flex lg:flex-col lg:justify-between"
        >
            <div>
                <div class="flex items-center gap-3 text-white">
                    <Building2 class="h-10 w-10" />
                    <div>
                        <h1 class="text-2xl font-bold">SCM Mining</h1>
                        <p class="text-sm text-blue-100">
                            Supply Chain Management System
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <h2 class="mb-4 text-3xl font-bold text-white">
                        Manage Your Supply Chain with Confidence
                    </h2>
                    <p class="text-lg text-blue-100">
                        Streamline procurement, inventory, and warehouse
                        operations in one powerful platform.
                    </p>
                </div>

                <div class="grid gap-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/30"
                        >
                            <ShieldCheck class="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-white">
                                Secure & Reliable
                            </h3>
                            <p class="text-sm text-blue-100">
                                Enterprise-grade security with role-based access
                                control
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/30"
                        >
                            <Building2 class="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-white">
                                Complete Solution
                            </h3>
                            <p class="text-sm text-blue-100">
                                From purchase requests to inventory tracking,
                                all in one place
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/30"
                        >
                            <Lock class="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-white">
                                Real-time Analytics
                            </h3>
                            <p class="text-sm text-blue-100">
                                Make data-driven decisions with comprehensive
                                reports
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-sm text-blue-200">
                © 2026 SCM Mining. All rights reserved.
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div
            class="flex w-full items-center justify-center p-8 lg:w-1/2 lg:p-12"
        >
            <div class="w-full max-w-md space-y-8">
                <!-- Mobile Logo -->
                <div class="flex items-center gap-3 lg:hidden">
                    <Building2 class="h-8 w-8 text-blue-600" />
                    <div>
                        <h1 class="text-xl font-bold text-slate-900">
                            SCM Mining
                        </h1>
                        <p class="text-xs text-slate-600">
                            Supply Chain Management
                        </p>
                    </div>
                </div>

                <!-- Login Header -->
                <div>
                    <h2
                        class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white"
                    >
                        Welcome back
                    </h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                        Enter your credentials to access your account
                    </p>
                </div>

                <!-- Status Message -->
                <div
                    v-if="status"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                >
                    {{ status }}
                </div>

                <!-- Login Form -->
                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label
                                for="email"
                                class="text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Email address
                            </Label>
                            <div class="relative">
                                <Mail
                                    class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    class="pl-10"
                                />
                            </div>
                            <InputError :message="errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="password"
                                class="text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Password
                            </Label>
                            <div class="relative">
                                <Lock
                                    class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    class="pl-10"
                                />
                            </div>
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <Label
                                for="remember"
                                class="flex items-center space-x-2"
                            >
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    :tabindex="3"
                                />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-400"
                                >
                                    Remember me
                                </span>
                            </Label>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="login-button"
                        size="lg"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        <span v-if="!processing">Sign in to your account</span>
                        <span v-else>Signing in...</span>
                    </Button>
                </Form>

                <!-- Additional Info -->
                <div
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50"
                >
                    <p
                        class="text-center text-xs text-slate-600 dark:text-slate-400"
                    >
                        Need help?
                        <a
                            href="#"
                            class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                        >
                            Contact IT Support
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
