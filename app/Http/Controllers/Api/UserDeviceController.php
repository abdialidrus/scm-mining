<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Services\Push\OneSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserDeviceController extends Controller
{
    /**
     * Register a device for push notifications.
     */
    public function register(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|in:web,ios,android',
            'browser' => 'nullable|string',
            'os' => 'nullable|string',
        ]);

        try {
            $oneSignal = new OneSignalService();

            $result = $oneSignal->registerDevice(
                Auth::id(),
                $request->device_token,
                $request->device_type,
                [
                    'browser' => $request->browser,
                    'os' => $request->os,
                ]
            );

            return response()->json([
                'message' => 'Device registered successfully',
                'player_id' => $result['player_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Device registration failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to register device',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all devices for the authenticated user.
     */
    public function index()
    {
        $devices = UserDevice::where('user_id', Auth::id())
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json($devices);
    }

    /**
     * Deactivate a device.
     */
    public function deactivate(Request $request, $id)
    {
        $device = UserDevice::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        if ($device->onesignal_player_id) {
            try {
                $oneSignal = new OneSignalService();
                $oneSignal->deactivateDevice($device->onesignal_player_id);
            } catch (\Exception $e) {
                Log::warning('OneSignal deactivation failed', [
                    'device_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $device->deactivate();

        return response()->json([
            'message' => 'Device deactivated successfully',
        ]);
    }
}
