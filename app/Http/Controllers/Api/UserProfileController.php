<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserProfileController extends Controller
{
    /**
     * Get User Profile
     * GET /api/users/profile/{firebase_id}
     *
     * Purpose: Get complete customer profile with all details
     *
     * Path Parameters:
     * - firebase_id (required): User's firebase_id
     *
     * Note: Only returns customers (role = 'customer')
     */
    public function show($firebase_id)
    {
        try {
            // Validate firebase_id is not empty
            if (empty($firebase_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase ID is required'
                ], 400);
            }

            Log::info('getUserProfile: Fetching customer with firebase_id: ' . $firebase_id);

            // Find customer by firebase_id only
            $user = User::where('firebase_id', $firebase_id)
                ->where('role', 'customer')
                ->first();

            if (!$user) {
                Log::info('getUserProfile: Customer not found for firebase_id: ' . $firebase_id);
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            Log::info('getUserProfile: Customer found, ID: ' . $user->id);

            // Get subscription plan if exists
            $subscriptionPlan = $this->getSubscriptionPlan($user);

            // Format response
            $data = $this->formatUserProfile($user, $subscriptionPlan);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Get User Profile Error: ' . $e->getMessage(), [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get Authenticated User Profile
     * GET /api/user/profile
     *
     * Purpose: Get profile of currently authenticated customer
     * Requires: auth:sanctum middleware
     *
     * Note: Only returns customers (role = 'customer')
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Ensure user is a customer
            if ($user->role !== 'customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'This endpoint is only for customers'
                ], 403);
            }

            // Get subscription plan if exists
            $subscriptionPlan = $this->getSubscriptionPlan($user);

            // Format response
            $data = $this->formatUserProfile($user, $subscriptionPlan);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Get Current User Profile Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update User Profile
     * PUT /api/user/profile
     */
    public function update(Request $request)
    {
        try {
            // ✅ Get user by firebase_id (if sent) OR by token
            $user = null;

            if ($request->has('firebase_id')) {
                $user = User::where('firebase_id', $request->input('firebase_id'))->first();
            } else {
                $user = $request->user();
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or not authenticated',
                ], 404);
            }

            $data = $request->all();
            $data['firstName'] = $data['firstName'] ?? $data['first_name'] ?? null;
            $data['lastName'] = $data['lastName'] ?? $data['last_name'] ?? null;
            $data['email'] = $data['email'] ?? $data['Email'] ?? null;
            $data['countryCode'] = $data['countryCode'] ?? $data['country_code'] ?? null;
            $data['phoneNumber'] = $data['phoneNumber'] ?? $data['phone_number'] ?? null;
            $data['profilePictureURL'] = $data['profilePictureURL'] ?? $data['profile_picture_url'] ?? null;
            $data['shippingAddress'] = $data['shippingAddress'] ?? $data['shipping_address'] ?? null;
            $data['location'] = $data['location'] ?? $data['location_data'] ?? null;

            // ✅ Validate request
            $validationData = array_merge($data, $request->allFiles());

            $validator = Validator::make($validationData, [
                'firstName' => 'nullable|string|max:100',
                'lastName' => 'nullable|string|max:100',
                'email' => 'nullable|email|max:255',
                'countryCode' => 'nullable|string|max:10',
                'profile_picture' => 'nullable|image|max:4096',
                'fcmToken' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];

            // ✅ Handle profile picture file upload
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('public/users', $fileName);
                $updateData['profilePictureURL'] = url('storage/users/' . $fileName);
            } elseif ($data['profilePictureURL'] !== null) {
                $updateData['profilePictureURL'] = $data['profilePictureURL'];
            }

            // ✅ Handle normal fields
            foreach ([
                'firstName' => 'firstName',
                'lastName' => 'lastName',
                'email' => 'email',
                'fcmToken' => 'fcmToken',
                'countryCode' => 'countryCode',
                'zoneId' => 'zoneId',
                'phoneNumber' => 'phoneNumber',
            ] as $payloadKey => $column) {
                if (array_key_exists($payloadKey, $data) && $data[$payloadKey] !== null) {
                    $updateData[$column] = $data[$payloadKey];
                }
            }

            // ✅ Handle JSON fields (shippingAddress, location)
            foreach (['shippingAddress', 'location'] as $jsonField) {
                if (array_key_exists($jsonField, $data) && $data[$jsonField] !== null) {
                    $value = $data[$jsonField];
                    $updateData[$jsonField] = is_array($value)
                        ? json_encode($value)
                        : $value;
                }
            }

            // ✅ Save and refresh user
            if (!empty($updateData)) {
                foreach ($updateData as $attribute => $value) {
                    $user->{$attribute} = $value;
                }

                $user->save();
                $user->refresh();
            }

            // ✅ Get subscription plan if needed
            $subscriptionPlan = $this->getSubscriptionPlan($user);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $this->formatUserProfile($user, $subscriptionPlan),
            ]);

        } catch (\Exception $e) {
            Log::error('Update User Profile Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user profile',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Format user profile for API response
     */
    private function formatUserProfile($user, $subscriptionPlan = null)
    {
        // Parse shipping addresses
        $shippingAddress = $this->parseShippingAddress($user->shippingAddress ?? null);

        // Parse location
        $location = $this->parseLocation($user->location ?? null);

        // Parse user bank details
        $userBankDetails = $this->parseBankDetails($user->userBankDetails ?? null);

        // Parse in-progress orders
        $inProgressOrderID = $this->parseJsonArray($user->inProgressOrderID ?? null);
        $orderRequestData = $this->parseJsonArray($user->orderRequestData ?? null);

        // Ensure wallet_amount is a number
        $walletAmount = 0;
        if (isset($user->wallet_amount)) {
            if (is_numeric($user->wallet_amount)) {
                $walletAmount = (int) $user->wallet_amount;
            } elseif (is_string($user->wallet_amount)) {
                $walletAmount = (int) ($user->wallet_amount ?? 0);
            }
        }

        return [
            'id' => $user->id ?? $user->firebase_id ?? $user->_id,
            'firstName' => $user->firstName ?? '',
            'lastName' => $user->lastName ?? '',
            'email' => $user->email ?? '',
            'profilePictureURL' => $user->profilePictureURL ?? $user->profile_picture_url ?? '',
            'fcmToken' => $user->fcmToken ?? $user->fcm_token ?? '',
            'countryCode' => $user->countryCode ?? $user->country_code ?? '',
            'phoneNumber' => $user->phoneNumber ?? $user->phone_number ?? '',
            'wallet_amount' => $walletAmount,
            'active' => (bool) ($user->active ?? false),
            'isActive' => (bool) ($user->isActive ?? false),
            'isDocumentVerify' => (bool) ($user->isDocumentVerify ?? $user->is_document_verify ?? false),
            'createdAt' => $user->createdAt ?? $user->created_at ?? $user->_created_at ?? null,
            'role' => $user->role ?? 'customer',
            'location' => $location,
            'userBankDetails' => $userBankDetails,
            'shippingAddress' => $shippingAddress,
            'carName' => $user->carName ?? $user->car_name ?? null,
            'carNumber' => $user->carNumber ?? $user->car_number ?? null,
            'carPictureURL' => $user->carPictureURL ?? $user->car_picture_url ?? null,
            'inProgressOrderID' => $inProgressOrderID,
            'orderRequestData' => $orderRequestData,
            'vendorID' => $user->vendorID ?? $user->vendor_id ?? null,
            'zoneId' => $user->zoneId ?? $user->zone_id ?? null,
            'rotation' => $user->rotation ?? 0,
            'appIdentifier' => $user->appIdentifier ?? $user->app_identifier ?? 'android',
            'provider' => $user->provider ?? 'email',
            'subscriptionPlanId' => $user->subscriptionPlanId ?? $user->subscription_plan_id ?? null,
            'subscriptionExpiryDate' => $user->subscriptionExpiryDate ?? $user->subscription_expiry_date ?? null,
            'subscriptionPlan' => $subscriptionPlan,
        ];
    }

    /**
     * Parse shipping address from JSON or array
     */
    private function parseShippingAddress($shippingAddress)
    {
        if (empty($shippingAddress)) {
            return [];
        }

        // Decode if it's a JSON string
        if (is_string($shippingAddress)) {
            try {
                $decoded = json_decode($shippingAddress, true);
                if (is_array($decoded)) {
                    $shippingAddress = $decoded;
                } else {
                    return [];
                }
            } catch (\Exception $e) {
                Log::error('Error parsing shipping address: ' . $e->getMessage());
                return [];
            }
        }

        // Validate array type
        if (!is_array($shippingAddress)) {
            return [];
        }

        // If it's a single address object, wrap it in an array
        if (isset($shippingAddress['address']) || isset($shippingAddress['locality'])) {
            $shippingAddress = [$shippingAddress];
        }

        // Normalize each address
        return array_map(function ($addr) {
            if (!is_array($addr)) {
                return null;
            }

            return [
                'id' => $addr['id'] ?? null,
                'label' => $addr['label'] ?? '',              // ✅ Added
                'address' => $addr['address'] ?? '',
                'addressAs' => $addr['addressAs'] ?? '',
                'landmark' => $addr['landmark'] ?? '',
                'city' => $addr['city'] ?? '',                // ✅ Added
                'pincode' => $addr['pincode'] ?? '',          // ✅ Added
                'locality' => $addr['locality'] ?? '',
                'location' => [
                    'latitude' => (float) ($addr['latitude'] ?? ($addr['location']['latitude'] ?? 0)),
                    'longitude' => (float) ($addr['longitude'] ?? ($addr['location']['longitude'] ?? 0)),
                ],
                'isDefault' => (bool) ($addr['isDefault'] ?? false),
                'zoneId' => $addr['zoneId'] ?? null,
            ];
        }, array_filter($shippingAddress));
    }

    /**
     * Parse location from JSON
     */
    private function parseLocation($location)
    {
        if (empty($location)) {
            return null;
        }

        // If it's a string, decode it
        if (is_string($location)) {
            try {
                $location = json_decode($location, true);
            } catch (\Exception $e) {
                return null;
            }
        }

        if (!is_array($location)) {
            return null;
        }

        return [
            'latitude' => (float) ($location['latitude'] ?? 0),
            'longitude' => (float) ($location['longitude'] ?? 0),
        ];
    }

    /**
     * Parse bank details from JSON
     */
    private function parseBankDetails($bankDetails)
    {
        if (empty($bankDetails)) {
            return null;
        }

        // If it's a string, decode it
        if (is_string($bankDetails)) {
            try {
                $bankDetails = json_decode($bankDetails, true);
            } catch (\Exception $e) {
                return null;
            }
        }

        if (!is_array($bankDetails)) {
            return null;
        }

        return [
            'bankName' => $bankDetails['bankName'] ?? '',
            'branchName' => $bankDetails['branchName'] ?? '',
            'holderName' => $bankDetails['holderName'] ?? '',
            'accountNumber' => $bankDetails['accountNumber'] ?? '',
            'otherDetails' => $bankDetails['otherDetails'] ?? '',
        ];
    }

    /**
     * Parse JSON array field
     */
    private function parseJsonArray($field)
    {
        if (empty($field)) {
            return [];
        }

        if (is_string($field)) {
            try {
                $decoded = json_decode($field, true);
                return is_array($decoded) ? $decoded : [];
            } catch (\Exception $e) {
                return [];
            }
        }

        return is_array($field) ? $field : [];
    }

    /**
     * Get subscription plan for user
     */
    private function getSubscriptionPlan($user)
    {
        if (empty($user->subscriptionPlanId) && empty($user->subscription_plan_id)) {
            return null;
        }

        try {
            $planId = $user->subscriptionPlanId ?? $user->subscription_plan_id;

            // Check if subscription_plans table exists
            if (!DB::getSchemaBuilder()->hasTable('subscription_plans')) {
                return null;
            }

            $plan = DB::table('subscription_plans')
                ->where('id', $planId)
                ->first();

            if (!$plan) {
                return null;
            }

            return [
                'id' => $plan->id ?? null,
                'name' => $plan->name ?? '',
                'price' => $plan->price ?? '0',
                'expiryDay' => $plan->expiryDay ?? $plan->expiry_day ?? null,
                'type' => $plan->type ?? 'free',
                'description' => $plan->description ?? '',
                'itemLimit' => $plan->itemLimit ?? $plan->item_limit ?? '-1',
                'orderLimit' => $plan->orderLimit ?? $plan->order_limit ?? '-1',
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching subscription plan: ' . $e->getMessage());
            return null;
        }
    }

}

