<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShippingAddressController extends Controller
{
    /**
     * Fetch user's shipping addresses.
     *
     * Routes examples:
     * GET /api/users/{userId}/shipping-address
     * GET /api/users/shipping-address?phone=9999999999
     */
    public function show(Request $request, $userId = null)
    {
        try {
            if ($userId) {
                $user = User::where('id', $userId)
                            ->orWhere('firebase_id', $userId)
                            ->first();
            } elseif ($request->query('phone')) {
                $user = User::where('phone', $request->query('phone'))->first();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing user identifier. Provide route userId, firebase_id, or ?phone=.',
                ], 400);
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $addresses = [];

            $rawExisting = $user->shippingAddress;
            if (!empty($rawExisting)) {
                if (is_string($rawExisting)) {
                    $decoded = json_decode($rawExisting, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $addresses = $decoded;
                    }
                } elseif (is_array($rawExisting)) {
                    $addresses = $rawExisting;
                }
            }

            if (!is_array($addresses)) {
                $addresses = [];
            }

            return response()->json([
                'success' => true,
                'data' => array_values($addresses),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed fetching shipping address', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'userId' => $userId ?? $request->query('phone'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping address',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update user's shipping addresses (replace or merge).
     *
     * Routes examples:
     * PUT /api/users/{userId}/shipping-address
     * POST /api/users/{userId}/shipping-address?merge=true
     *
     * Optionally you can call by phone: POST /api/users/shipping-address?phone=9999999999
     */
    public function update(Request $request, $userId = null)
    {
        try {
            // Identify user by route param `userId` OR query param `phone`
            if ($userId) {
                // Try finding by numeric id OR firebase_id
                $user = User::where('id', $userId)
                            ->orWhere('firebase_id', $userId)
                            ->first();
            } elseif ($request->query('phone')) {
                $user = User::where('phone', $request->query('phone'))->first();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing user identifier. Provide route userId, firebase_id, or ?phone=.',
                ], 400);
            }
            

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Get raw JSON body (accept array or single object)
            $payload = $request->json()->all();
            if ($payload === null) {
                $payload = [];
            }

            if (!is_array($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payload. Expected JSON array or object.',
                ], 400);
            }

            if (Arr::isAssoc($payload)) {
                $payload = [$payload];
            }

            // Merge flag ?merge=true or POST field merge=true
            $mergeMode = $request->query('merge', $request->input('merge', false));
            $mergeMode = filter_var($mergeMode, FILTER_VALIDATE_BOOLEAN);

            // Load existing shippingAddress (if any) and make sure it's an array
            $existing = [];
            $rawExisting = $user->shippingAddress;
            if (!empty($rawExisting)) {
                if (is_string($rawExisting)) {
                    $decoded = json_decode($rawExisting, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $existing = $decoded;
                    }
                } elseif (is_array($rawExisting)) {
                    $existing = $rawExisting;
                }
            }

            // Prepare result array
            if ($mergeMode) {
                // Build associative map by id for existing addresses
                $map = [];

                foreach ($existing as $addr) {
                    if (isset($addr['id']) && $addr['id'] !== '') {
                        $map[$addr['id']] = $addr;
                    } else {
                        // keep addresses without id using generated key to avoid overwrite
                        $map[Str::uuid()->toString() . '_old'] = $addr;
                    }
                }

                // Merge incoming: incoming entries overwrite existing with same id
                foreach ($payload as $addr) {
                    if (isset($addr['id']) && $addr['id'] !== '') {
                        $map[$addr['id']] = $addr;
                    } else {
                        // assign uuid for incoming without id
                        $addr['id'] = Str::uuid()->toString();
                        $map[$addr['id']] = $addr;
                    }
                }

                // Final addresses are values of map (reset numeric keys)
                $finalAddresses = array_values($map);
            } else {
                // Replace mode: take payload as-is but ensure all items have id
                $finalAddresses = [];
                foreach ($payload as $addr) {
                    if (!isset($addr['id']) || empty($addr['id'])) {
                        $addr['id'] = Str::uuid()->toString();
                    }
                    $finalAddresses[] = $addr;
                }
            }

            // Optional: ensure only one isDefault = true (if present)
            $defaultCount = 0;
            foreach ($finalAddresses as $a) {
                if (isset($a['isDefault'])) {
                    // accept both integer and boolean
                    if ($a['isDefault'] === true || $a['isDefault'] === 1 || $a['isDefault'] === '1') {
                        $defaultCount++;
                    }
                }
            }
            if ($defaultCount > 1) {
                // If multiple defaults exist, keep the last one as default, unset others
                $seenDefault = false;
                for ($i = count($finalAddresses) - 1; $i >= 0; $i--) {
                    if (isset($finalAddresses[$i]['isDefault']) &&
                        ($finalAddresses[$i]['isDefault'] === true || $finalAddresses[$i]['isDefault'] === 1 || $finalAddresses[$i]['isDefault'] === '1')) {
                        if ($seenDefault === false) {
                            // keep this as default
                            $seenDefault = true;
                            $finalAddresses[$i]['isDefault'] = 1;
                        } else {
                            $finalAddresses[$i]['isDefault'] = 0;
                        }
                    }
                }
            }

            // Save within DB transaction
            DB::transaction(function () use ($user, $finalAddresses) {
                $user->shippingAddress = json_encode($finalAddresses, JSON_UNESCAPED_UNICODE);
                $user->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Shipping address updated.',
                'data' => $finalAddresses,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed updating shipping address', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'userId' => $userId ?? $request->query('phone'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update shipping address',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
