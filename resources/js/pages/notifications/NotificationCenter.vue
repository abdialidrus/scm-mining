<script setup lang="ts">
import axios from '@/bootstrap';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { format, formatDistanceToNow } from 'date-fns';
import {
    AlertCircle,
    Bell,
    CheckCheck,
    Clock,
    ExternalLink,
    FileCheck,
    FileX,
    Package,
    Settings,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Notification {
    id: string;
    type: string;
    data: {
        title?: string;
        message?: string;
        details?: string;
        url?: string;
        [key: string]: any;
    };
    read_at: string | null;
    created_at: string;
}

const { toast } = useToast();
const loading = ref(true);
const notifications = ref<Notification[]>([]);
const unreadCount = ref(0);
const currentTab = ref('all');

// Fetch notifications
const fetchNotifications = async (unreadOnly = false) => {
    loading.value = true;
    try {
        const params: any = { per_page: 50 };
        if (unreadOnly) {
            params.unread_only = true;
        }

        const response = await axios.get('/api/notifications', { params });
        notifications.value = response.data.data || [];
    } catch (error: any) {
        toast({
            title: 'Error',
            description:
                error.response?.data?.message || 'Failed to load notifications',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

// Fetch unread count
const fetchUnreadCount = async () => {
    try {
        const response = await axios.get('/api/notifications/unread-count');
        unreadCount.value = response.data.count || 0;
    } catch (error) {
        console.error('Failed to fetch unread count:', error);
    }
};

// Mark as read
const markAsRead = async (id: string) => {
    try {
        await axios.post(`/api/notifications/${id}/read`);

        const notification = notifications.value.find((n) => n.id === id);
        if (notification) {
            notification.read_at = new Date().toISOString();
        }

        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (error: any) {
        toast({
            title: 'Error',
            description:
                error.response?.data?.message || 'Failed to mark as read',
            variant: 'destructive',
        });
    }
};

// Mark all as read
const markAllAsRead = async () => {
    try {
        await axios.post('/api/notifications/read-all');

        notifications.value.forEach((n) => {
            if (!n.read_at) {
                n.read_at = new Date().toISOString();
            }
        });

        unreadCount.value = 0;

        toast({
            title: 'Success',
            description: 'All notifications marked as read',
        });
    } catch (error: any) {
        toast({
            title: 'Error',
            description:
                error.response?.data?.message || 'Failed to mark all as read',
            variant: 'destructive',
        });
    }
};

// Delete notification
const deleteNotification = async (id: string) => {
    try {
        await axios.delete(`/api/notifications/${id}`);

        const index = notifications.value.findIndex((n) => n.id === id);
        if (index !== -1) {
            const notification = notifications.value[index];
            if (!notification.read_at) {
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
            notifications.value.splice(index, 1);
        }

        toast({
            title: 'Success',
            description: 'Notification deleted',
        });
    } catch (error: any) {
        toast({
            title: 'Error',
            description:
                error.response?.data?.message ||
                'Failed to delete notification',
            variant: 'destructive',
        });
    }
};

// Get notification icon
const getNotificationIcon = (type: string) => {
    // Extract the notification class name from the full namespace
    const className = type.split('\\').pop() || '';

    if (className.includes('ApprovalRequired')) {
        return AlertCircle;
    } else if (className.includes('Approved')) {
        return FileCheck;
    } else if (className.includes('Rejected')) {
        return FileX;
    } else if (className.includes('Reminder')) {
        return Clock;
    } else if (className.includes('Stock')) {
        return Package;
    }
    return Bell;
};

// Get notification color
const getNotificationColor = (type: string) => {
    const className = type.split('\\').pop() || '';

    if (className.includes('ApprovalRequired')) {
        return 'text-blue-500';
    } else if (className.includes('Approved')) {
        return 'text-green-500';
    } else if (className.includes('Rejected')) {
        return 'text-red-500';
    } else if (className.includes('Reminder')) {
        return 'text-orange-500';
    } else if (className.includes('Stock')) {
        return 'text-yellow-500';
    }
    return 'text-gray-500';
};

// Format notification title
const getNotificationTitle = (notification: Notification) => {
    const className = notification.type.split('\\').pop() || '';

    if (className.includes('ApprovalRequired')) {
        return 'Approval Required';
    } else if (className.includes('Approved')) {
        return 'Document Approved';
    } else if (className.includes('Rejected')) {
        return 'Document Rejected';
    } else if (className.includes('Reminder')) {
        return 'Pending Approvals Reminder';
    } else if (className.includes('Stock')) {
        return 'Low Stock Alert';
    }
    return notification.data?.title || 'Notification';
};

// Handle notification click
const handleNotificationClick = (notification: Notification) => {
    if (!notification.read_at) {
        markAsRead(notification.id);
    }

    // Navigate to related page if URL exists
    if (notification.data?.url) {
        router.visit(notification.data.url);
    }
};

// Filtered notifications
const filteredNotifications = computed(() => {
    if (currentTab.value === 'unread') {
        return notifications.value.filter((n) => !n.read_at);
    }
    return notifications.value;
});

// Tab change handler
const handleTabChange = (value: string) => {
    currentTab.value = value;
    if (value === 'unread') {
        fetchNotifications(true);
    } else {
        fetchNotifications(false);
    }
};

onMounted(() => {
    fetchNotifications();
    fetchUnreadCount();
});
</script>

<template>
    <AppLayout>
        <Head title="Notifications" />

        <div class="container mx-auto max-w-5xl px-4 py-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1
                        class="flex items-center gap-2 text-3xl font-bold tracking-tight"
                    >
                        <Bell class="h-8 w-8" />
                        Notification Center
                        <Badge
                            v-if="unreadCount > 0"
                            variant="destructive"
                            class="ml-2"
                        >
                            {{ unreadCount }}
                        </Badge>
                    </h1>
                    <p class="mt-2 text-muted-foreground">
                        Stay updated with your important notifications
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="markAllAsRead"
                        :disabled="unreadCount === 0"
                    >
                        <CheckCheck class="mr-2 h-4 w-4" />
                        Mark All Read
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="router.visit('/notifications/preferences')"
                    >
                        <Settings class="mr-2 h-4 w-4" />
                        Settings
                    </Button>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="mb-4 flex gap-2">
                <Button
                    :variant="currentTab === 'all' ? 'default' : 'outline'"
                    @click="handleTabChange('all')"
                >
                    All Notifications
                </Button>
                <Button
                    :variant="currentTab === 'unread' ? 'default' : 'outline'"
                    @click="handleTabChange('unread')"
                >
                    Unread
                    <Badge
                        v-if="unreadCount > 0"
                        variant="secondary"
                        class="ml-2"
                    >
                        {{ unreadCount }}
                    </Badge>
                </Button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div
                    class="h-12 w-12 animate-spin rounded-full border-b-2 border-primary"
                ></div>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="filteredNotifications.length === 0"
                class="py-12 text-center"
            >
                <component
                    :is="currentTab === 'unread' ? CheckCheck : Bell"
                    class="mx-auto mb-4 h-16 w-16 text-muted-foreground"
                />
                <h3 class="mb-2 text-lg font-medium">
                    {{
                        currentTab === 'unread'
                            ? 'No unread notifications'
                            : 'No notifications'
                    }}
                </h3>
                <p class="text-muted-foreground">You're all caught up!</p>
            </div>

            <!-- Notifications List -->
            <div v-else class="space-y-4">
                <Card
                    v-for="notification in filteredNotifications"
                    :key="notification.id"
                    :class="[
                        'cursor-pointer transition-all hover:shadow-md',
                        !notification.read_at &&
                            'border-l-4 border-l-primary bg-accent/50',
                    ]"
                    @click="handleNotificationClick(notification)"
                >
                    <CardContent class="p-4">
                        <div class="flex items-start gap-4">
                            <!-- Icon -->
                            <div
                                :class="[
                                    'mt-1',
                                    getNotificationColor(notification.type),
                                ]"
                            >
                                <component
                                    :is="getNotificationIcon(notification.type)"
                                    class="h-6 w-6"
                                />
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <div
                                    class="mb-2 flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <h3 class="text-base font-semibold">
                                            {{
                                                getNotificationTitle(
                                                    notification,
                                                )
                                            }}
                                        </h3>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                notification.data?.message ||
                                                notification.data?.title
                                            }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Badge
                                            v-if="!notification.read_at"
                                            variant="default"
                                            class="shrink-0"
                                        >
                                            New
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 shrink-0"
                                            @click.stop="
                                                deleteNotification(
                                                    notification.id,
                                                )
                                            "
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <!-- Additional Details -->
                                <div
                                    v-if="notification.data?.details"
                                    class="mb-2 text-sm text-muted-foreground"
                                >
                                    {{ notification.data.details }}
                                </div>

                                <!-- Action Button -->
                                <div v-if="notification.data?.url" class="mt-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click.stop="
                                            router.visit(notification.data.url)
                                        "
                                    >
                                        View Details
                                        <ExternalLink class="ml-2 h-3 w-3" />
                                    </Button>
                                </div>

                                <!-- Timestamp -->
                                <div
                                    class="mt-3 flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <Clock class="h-3 w-3" />
                                    <span
                                        :title="
                                            format(
                                                new Date(
                                                    notification.created_at,
                                                ),
                                                'PPpp',
                                            )
                                        "
                                    >
                                        {{
                                            formatDistanceToNow(
                                                new Date(
                                                    notification.created_at,
                                                ),
                                                { addSuffix: true },
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
