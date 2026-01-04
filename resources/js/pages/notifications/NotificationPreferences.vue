<script setup lang="ts">
import axios from '@/bootstrap';
import Button from '@/components/ui/button/Button.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Bell,
    Database,
    Mail,
    RotateCcw,
    Save,
    Smartphone,
} from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

interface NotificationType {
    key: string;
    name: string;
    description: string;
    category: string;
}

interface NotificationPreferences {
    email_enabled: boolean;
    push_enabled: boolean;
    database_enabled: boolean;
    preferences: Record<
        string,
        {
            email: boolean;
            push: boolean;
            database: boolean;
        }
    >;
}

const { toast } = useToast();
const loading = ref(true);
const saving = ref(false);
const resetting = ref(false);

const globalPreferences = ref({
    email_enabled: true,
    push_enabled: true,
    database_enabled: true,
});

const notificationTypes = ref<NotificationType[]>([]);
const preferences = ref<Record<string, any>>({});

// Fetch preferences
const fetchPreferences = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/notification-preferences');

        // Set global preferences from API response
        globalPreferences.value = {
            email_enabled: response.data.global_preferences?.email ?? true,
            push_enabled: response.data.global_preferences?.push ?? true,
            database_enabled:
                response.data.global_preferences?.database ?? true,
        };

        // Set notification type preferences
        preferences.value = response.data.preferences || {};

        // Set notification types list
        notificationTypes.value = response.data.notification_types || [];

        // Initialize preferences for any missing notification types
        notificationTypes.value.forEach((type) => {
            if (!preferences.value[type.key]) {
                preferences.value[type.key] = {
                    email: true,
                    push: true,
                    database: true,
                };
            }
        });
    } catch (error: any) {
        toast({
            title: 'Error',
            description:
                error.response?.data?.message || 'Failed to load preferences',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

// Save preferences
const savePreferences = async () => {
    saving.value = true;
    try {
        await axios.put('/api/notification-preferences', {
            ...globalPreferences.value,
            preferences: preferences.value,
        });

        toast({
            title: 'Success',
            description: 'Notification preferences saved successfully',
        });
    } catch (error: any) {
        toast({
            title: 'Error',
            description:
                error.response?.data?.message || 'Failed to save preferences',
            variant: 'destructive',
        });
    } finally {
        saving.value = false;
    }
};

// Reset to defaults
const resetToDefaults = async () => {
    if (
        !confirm(
            'Are you sure you want to reset all notification preferences to defaults?',
        )
    ) {
        return;
    }

    resetting.value = true;
    try {
        await axios.post('/api/notification-preferences/reset');

        // Re-fetch preferences after reset
        await fetchPreferences();

        toast({
            title: 'Success',
            description: 'Preferences reset to defaults',
        });
    } catch (error: any) {
        toast({
            title: 'Error',
            description:
                error.response?.data?.message || 'Failed to reset preferences',
            variant: 'destructive',
        });
    } finally {
        resetting.value = false;
    }
};

// Group notification types by category
const groupedTypes = ref<Record<string, NotificationType[]>>({});
const updateGroupedTypes = () => {
    groupedTypes.value = notificationTypes.value.reduce(
        (acc, type) => {
            if (!acc[type.category]) {
                acc[type.category] = [];
            }
            acc[type.category].push(type);
            return acc;
        },
        {} as Record<string, NotificationType[]>,
    );
};

onMounted(() => {
    fetchPreferences();
});

// Watch for notification types changes
watch(
    notificationTypes,
    () => {
        updateGroupedTypes();
    },
    { immediate: true },
);
</script>

<template>
    <AppLayout>
        <Head title="Notification Preferences" />

        <div class="container mx-auto max-w-5xl px-4 py-8">
            <div class="mb-8">
                <h1
                    class="flex items-center gap-2 text-3xl font-bold tracking-tight"
                >
                    <Bell class="h-8 w-8" />
                    Notification Preferences
                </h1>
                <p class="mt-2 text-muted-foreground">
                    Manage how you receive notifications for different events
                </p>
            </div>

            <div v-if="loading" class="flex items-center justify-center py-12">
                <div
                    class="h-12 w-12 animate-spin rounded-full border-b-2 border-primary"
                ></div>
            </div>

            <div v-else class="space-y-6">
                <!-- Global Channel Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle>Global Channel Settings</CardTitle>
                        <CardDescription>
                            Enable or disable entire notification channels
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <Mail class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <Label class="text-base font-medium"
                                        >Email Notifications</Label
                                    >
                                    <p class="text-sm text-muted-foreground">
                                        Receive notifications via email
                                    </p>
                                </div>
                            </div>
                            <Switch
                                v-model:checked="
                                    globalPreferences.email_enabled
                                "
                            />
                        </div>

                        <Separator />

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <Smartphone
                                    class="h-5 w-5 text-muted-foreground"
                                />
                                <div>
                                    <Label class="text-base font-medium"
                                        >Push Notifications</Label
                                    >
                                    <p class="text-sm text-muted-foreground">
                                        Receive push notifications on your
                                        devices
                                    </p>
                                </div>
                            </div>
                            <Switch
                                v-model:checked="globalPreferences.push_enabled"
                            />
                        </div>

                        <Separator />

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <Database
                                    class="h-5 w-5 text-muted-foreground"
                                />
                                <div>
                                    <Label class="text-base font-medium"
                                        >In-App Notifications</Label
                                    >
                                    <p class="text-sm text-muted-foreground">
                                        Store notifications in the notification
                                        center
                                    </p>
                                </div>
                            </div>
                            <Switch
                                v-model:checked="
                                    globalPreferences.database_enabled
                                "
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Notification Type Preferences -->
                <Card v-for="(types, category) in groupedTypes" :key="category">
                    <CardHeader>
                        <CardTitle>{{ category }}</CardTitle>
                        <CardDescription>
                            Configure notification channels for
                            {{ category.toLowerCase() }} events
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <div
                                v-for="type in types"
                                :key="type.key"
                                class="space-y-3"
                            >
                                <div>
                                    <h4 class="font-medium">{{ type.name }}</h4>
                                    <p class="text-sm text-muted-foreground">
                                        {{ type.description }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-3 gap-4 pl-4">
                                    <div
                                        v-if="preferences[type.key]"
                                        class="flex items-center space-x-2"
                                    >
                                        <Switch
                                            :id="`${type.key}-email`"
                                            v-model:checked="
                                                preferences[type.key].email
                                            "
                                            :disabled="
                                                !globalPreferences.email_enabled
                                            "
                                        />
                                        <Label
                                            :for="`${type.key}-email`"
                                            class="text-sm"
                                            :class="{
                                                'text-muted-foreground':
                                                    !globalPreferences.email_enabled,
                                            }"
                                        >
                                            Email
                                        </Label>
                                    </div>

                                    <div
                                        v-if="preferences[type.key]"
                                        class="flex items-center space-x-2"
                                    >
                                        <Switch
                                            :id="`${type.key}-push`"
                                            v-model:checked="
                                                preferences[type.key].push
                                            "
                                            :disabled="
                                                !globalPreferences.push_enabled
                                            "
                                        />
                                        <Label
                                            :for="`${type.key}-push`"
                                            class="text-sm"
                                            :class="{
                                                'text-muted-foreground':
                                                    !globalPreferences.push_enabled,
                                            }"
                                        >
                                            Push
                                        </Label>
                                    </div>

                                    <div
                                        v-if="preferences[type.key]"
                                        class="flex items-center space-x-2"
                                    >
                                        <Switch
                                            :id="`${type.key}-database`"
                                            v-model:checked="
                                                preferences[type.key].database
                                            "
                                            :disabled="
                                                !globalPreferences.database_enabled
                                            "
                                        />
                                        <Label
                                            :for="`${type.key}-database`"
                                            class="text-sm"
                                            :class="{
                                                'text-muted-foreground':
                                                    !globalPreferences.database_enabled,
                                            }"
                                        >
                                            In-App
                                        </Label>
                                    </div>
                                </div>

                                <Separator
                                    v-if="
                                        types.indexOf(type) < types.length - 1
                                    "
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between">
                    <Button
                        variant="outline"
                        @click="resetToDefaults"
                        :disabled="resetting || saving"
                    >
                        <RotateCcw class="mr-2 h-4 w-4" />
                        Reset to Defaults
                    </Button>

                    <Button
                        @click="savePreferences"
                        :disabled="saving || loading"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{ saving ? 'Saving...' : 'Save Preferences' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
