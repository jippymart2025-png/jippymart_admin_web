<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use \Illuminate\Http\JsonResponse;

class SettingsApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except([
            'mobileSettings',
            'getDeliveryChargeSettings',
            'getVendorAttributes',
        ]);
    }

    /**
     * Get all settings needed for the layout
     */
    public function getAllSettings()
    {
        try {
            $settings = [
                'globalSettings' => $this->getGlobalSettings(),
                'distanceSettings' => $this->getDistanceSettings(),
                'languages' => $this->getLanguages(),
                'version' => $this->getVersion(),
                'mapSettings' => $this->getMapSettings(),
                'notificationSettings' => $this->getNotificationSettings(),
                'currency' => $this->getCurrencySettings(),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching settings'
            ], 500);
        }
    }

    /**
     * Get global settings
     */
    public function getGlobalSettings()
    {
        try {
            // Settings table structure: id (auto-increment), document_name (unique), fields (JSON)
            $setting = DB::table('settings')
                ->where('document_name', 'globalSettings')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                return $data ?? [];
            }

            // Return defaults if not found
            return [
                'appLogo' => '',
                'meta_title' => 'Jippy Mart',
                'applicationName' => 'Jippy Mart',
                'web_panel_color' => '#FF683A',
                'order_ringtone_url' => ''
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching global settings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get distance settings
     */
    public function getDistanceSettings()
    {
        try {
            $setting = DB::table('settings')
                ->where('document_name', 'RestaurantNearBy')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                return $data ?? [];
            }

            return [
                'distanceType' => 'km',
                'radios' => '15'
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching distance settings: ' . $e->getMessage());
            return ['distanceType' => 'km'];
        }
    }

    /**
     * Get languages
     */
    public function getLanguages()
    {
        try {
            $setting = DB::table('settings')
                ->where('document_name', 'languages')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                if (isset($data['list'])) {
                    return $data['list'];
                }
            }

            // Return default English if not found
            return [[
                'title' => 'English',
                'slug' => 'en',
                'isActive' => true,
                'is_rtl' => false
            ]];

        } catch (\Exception $e) {
            Log::error('Error fetching languages: ' . $e->getMessage());
            return [[
                'title' => 'English',
                'slug' => 'en',
                'isActive' => true
            ]];
        }
    }

    /**
     * Get version information
     */
    public function getVersion()
    {
        try {
            $setting = DB::table('settings')
                ->where('document_name', 'Version')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                return $data ?? [];
            }

            return [
                'web_version' => '2.5.0',
                'app_version' => '2.5.0'
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching version: ' . $e->getMessage());
            return ['web_version' => '2.5.0'];
        }
    }

    /**
     * Get map settings
     */
    public function getMapSettings()
    {
        try {
            $driverNearBy = DB::table('settings')
                ->where('document_name', 'DriverNearBy')
                ->first();

            $googleMapKey = DB::table('settings')
                ->where('document_name', 'googleMapKey')
                ->first();

            $data = [];

            if ($driverNearBy && !empty($driverNearBy->fields)) {
                $driverData = json_decode($driverNearBy->fields, true);
                $data['selectedMapType'] = $driverData['selectedMapType'] ?? 'google';
            }

            if ($googleMapKey && !empty($googleMapKey->fields)) {
                $keyData = json_decode($googleMapKey->fields, true);
                $data['googleMapKey'] = $keyData['key'] ?? '';
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Error fetching map settings: ' . $e->getMessage());
            return ['selectedMapType' => 'google'];
        }
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings()
    {
        try {
            $setting = DB::table('settings')
                ->where('document_name', 'notification_setting')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                return $data ?? [];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Error fetching notification settings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get currency settings
     */
    public function getCurrencySettings()
    {
        try {
            $currency = DB::table('currencies')
                ->where('isActive', 1)
                ->first();

            if ($currency) {
                return response()->json([
                    'symbol' => $currency->symbol ?? '₹',
                    'code' => $currency->code ?? 'INR',
                    'name' => $currency->name ?? 'Indian Rupee',
                    'symbolAtRight' => $currency->symbolAtRight ?? false,
                    'decimal_degits' => $currency->decimal_degits ?? 2,
                ]);
            }

            return response()->json([
                'symbol' => '₹',
                'code' => 'INR',
                'symbolAtRight' => false,
                'decimal_degits' => 2
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching currency settings: ' . $e->getMessage());
            return response()->json(['symbol' => '₹']);
        }
    }

    /**
     * Get restaurant nearby settings
     */
    public function getRestaurantSettings()
    {
        try {
            $setting = DB::table('settings')
                ->where('document_name', 'RestaurantNearBy')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                return response()->json($data ?? []);
            }

            return response()->json([
                'distanceType' => 'km',
                'radios' => '15',
                'driverRadios' => '50000'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching restaurant settings: ' . $e->getMessage());
            return response()->json(['distanceType' => 'km']);
        }
    }

    /**
     * Get admin commission settings
     */
    public function getAdminCommission()
    {
        try {
            $setting = DB::table('settings')
                ->where('document_name', 'AdminCommission')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                return response()->json($data ?? []);
            }

            return response()->json([
                'isEnabled' => false,
                'commissionType' => 'Percent',
                'fix_commission' => 0
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching admin commission: ' . $e->getMessage());
            return response()->json(['isEnabled' => false]);
        }
    }

    /**
     * Get driver nearby settings
     */
    public function getDriverSettings()
    {
        try {
            $setting = DB::table('settings')
                ->where('document_name', 'DriverNearBy')
                ->first();

            if ($setting && !empty($setting->fields)) {
                $data = json_decode($setting->fields, true);
                return response()->json($data ?? []);
            }

            return response()->json([
                'driverRadios' => '5',
                'mapType' => 'inappmap',
                'selectedMapType' => 'google'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching driver settings: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Unified settings payload for mobile clients (replaces Firestore listeners)
     */



    /**
     * Decode a JSON settings payload safely.
     */
    protected function decodeSetting(?string $payload): array
    {
        if (empty($payload)) {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Resolve active currency information.
     */
    protected function resolveCurrency(): array
    {
        $currency = DB::table('currencies')
            ->where('isActive', 1)
            ->first();

        if ($currency) {
            return [
                'symbol' => $currency->symbol ?? '₹',
                'code' => $currency->code ?? 'INR',
                'name' => $currency->name ?? 'Indian Rupee',
                'symbolAtRight' => (bool)($currency->symbolAtRight ?? false),
                'decimal_digits' => (int)($currency->decimal_degits ?? 2),
            ];
        }

        return [
            'symbol' => '₹',
            'code' => 'INR',
            'name' => 'Indian Rupee',
            'symbolAtRight' => false,
            'decimal_digits' => 2,
        ];
    }

    /**
     * Fetch delivery charge settings only.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDeliveryChargeSettings()
    {
        try {
            // Default structure (ensures consistent response)
//            $defaultData = [
//                'base_delivery_charge' => 234,
//                'minimum_delivery_charges' => null,
//                'minimum_delivery_charges_within_km' => null,
//                'delivery_charges_per_km' => null,
//                'amount' => null,
//                'item_total_threshold' => 1994,
//                'vendor_can_modify' => false,
//                'per_km_charge_above_free_distance' => 74,
//                'free_delivery_distance_km' => 54,
//            ];

            // Fetch record from DB
            $setting = DB::table('settings')
                ->where('document_name', 'DeliveryCharge')
                ->first();

//            $data = $defaultData;

            if ($setting && !empty($setting->fields)) {
                $decoded = json_decode($setting->fields, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Merge DB values with defaults (DB overrides defaults)
                    $data = array_merge( $decoded);
                } else {
                    Log::warning('Invalid JSON format in DeliveryCharge settings, using defaults.');
                }
            } else {
                Log::info('No DeliveryCharge settings found, using default values.');
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching delivery charge settings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch delivery charge settings.',
            ], 500);
        }
    }



    public function getVendorAttributes()
    {
        try {
            $attributes = DB::table('vendor_attributes')->get();

            return response()->json([
                'success' => true,
                'data' => $attributes,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching vendor attributes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch vendor attributes.',
            ], 500);
        }
    }


}

