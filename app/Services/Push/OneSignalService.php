<?php

namespace App\Services\Push;

use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OneSignalService
{
    protected string $appId;
    protected string $apiKey;
    protected string $baseUrl = 'https://onesignal.com/api/v1';

    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id');
        $this->apiKey = config('services.onesignal.api_key');

        if (empty($this->appId) || empty($this->apiKey)) {
            throw new Exception('OneSignal credentials are not configured');
        }
    }

    /**
     * Register a device for push notifications.
     *
     * @param int $userId User ID
     * @param string $deviceToken Device token
     * @param string $deviceType Device type (web, ios, android)
     * @param array $metadata Additional device metadata
     * @return array Response with player_id
     */
    public function registerDevice(
        int $userId,
        string $deviceToken,
        string $deviceType = 'web',
        array $metadata = []
    ): array {
        try {
            // Create player in OneSignal
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/players', [
                'app_id' => $this->appId,
                'device_type' => $this->getDeviceTypeCode($deviceType),
                'identifier' => $deviceToken,
                'tags' => [
                    'user_id' => (string) $userId,
                ],
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $playerId = $responseData['id'] ?? null;

                // Save to database
                UserDevice::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'device_token' => $deviceToken,
                    ],
                    [
                        'device_type' => $deviceType,
                        'onesignal_player_id' => $playerId,
                        'browser' => $metadata['browser'] ?? null,
                        'os' => $metadata['os'] ?? null,
                        'is_active' => true,
                        'last_used_at' => now(),
                    ]
                );

                return [
                    'success' => true,
                    'player_id' => $playerId,
                ];
            }

            throw new Exception('OneSignal API error: ' . $response->body());
        } catch (Exception $e) {
            Log::error('OneSignal device registration failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send push notification to specific users.
     *
     * @param array $userIds Array of user IDs
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data payload
     * @param array $options Additional options (url, icon, image, buttons)
     * @return array Response with notification ID and recipient count
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $message,
        array $data = [],
        array $options = []
    ): array {
        try {
            $payload = [
                'app_id' => $this->appId,
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'filters' => $this->buildUserFilters($userIds),
                'data' => $data,
            ];

            // Add optional fields
            if (!empty($options['url'])) {
                $payload['url'] = $options['url'];
            }

            if (!empty($options['icon'])) {
                $payload['small_icon'] = $options['icon'];
                $payload['large_icon'] = $options['icon'];
            }

            if (!empty($options['image'])) {
                $payload['big_picture'] = $options['image'];
            }

            if (!empty($options['buttons'])) {
                $payload['buttons'] = $options['buttons'];
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/notifications', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'notification_id' => $responseData['id'] ?? null,
                    'recipients' => $responseData['recipients'] ?? 0,
                ];
            }

            throw new Exception('OneSignal API error: ' . $response->body());
        } catch (Exception $e) {
            Log::error('OneSignal push notification failed', [
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification to a segment.
     *
     * @param string $segment Segment name
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data payload
     * @param array $options Additional options
     * @return array Response
     */
    public function sendToSegment(
        string $segment,
        string $title,
        string $message,
        array $data = [],
        array $options = []
    ): array {
        try {
            $payload = [
                'app_id' => $this->appId,
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'included_segments' => [$segment],
                'data' => $data,
            ];

            if (!empty($options['url'])) {
                $payload['url'] = $options['url'];
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/notifications', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'notification_id' => $responseData['id'] ?? null,
                ];
            }

            throw new Exception('OneSignal API error: ' . $response->body());
        } catch (Exception $e) {
            Log::error('OneSignal segment push failed', [
                'segment' => $segment,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get notification delivery status.
     *
     * @param string $notificationId OneSignal notification ID
     * @return array Status information
     */
    public function getNotificationStatus(string $notificationId): array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->apiKey,
            ])->get($this->baseUrl . '/notifications/' . $notificationId, [
                'app_id' => $this->appId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('OneSignal API error: ' . $response->body());
        } catch (Exception $e) {
            Log::error('OneSignal status check failed', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Deactivate a device.
     *
     * @param string $playerId OneSignal player ID
     * @return bool Success status
     */
    public function deactivateDevice(string $playerId): bool
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->apiKey,
            ])->delete($this->baseUrl . '/players/' . $playerId, [
                'app_id' => $this->appId,
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('OneSignal device deactivation failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build user filters for OneSignal.
     *
     * @param array $userIds Array of user IDs
     * @return array Filters array
     */
    protected function buildUserFilters(array $userIds): array
    {
        $filters = [];

        foreach ($userIds as $index => $userId) {
            $filters[] = [
                'field' => 'tag',
                'key' => 'user_id',
                'relation' => '=',
                'value' => (string) $userId,
            ];

            // Add OR operator between users (except for last one)
            if ($index < count($userIds) - 1) {
                $filters[] = ['operator' => 'OR'];
            }
        }

        return $filters;
    }

    /**
     * Get device type code for OneSignal.
     *
     * @param string $deviceType Device type string
     * @return int OneSignal device type code
     */
    protected function getDeviceTypeCode(string $deviceType): int
    {
        return match ($deviceType) {
            'ios' => 0,
            'android' => 1,
            'web' => 5,
            default => 5, // Default to web
        };
    }
}
