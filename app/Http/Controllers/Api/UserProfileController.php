<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Update allowed fields
            $updateData = [];

            if ($request->has('firstName')) $updateData['firstName'] = $request->input('firstName');
            if ($request->has('lastName')) $updateData['lastName'] = $request->input('lastName');
            if ($request->has('email')) $updateData['email'] = $request->input('email');
            if ($request->has('profilePictureURL')) $updateData['profilePictureURL'] = $request->input('profilePictureURL');
            if ($request->has('fcmToken')) $updateData['fcmToken'] = $request->input('fcmToken');
            if ($request->has('countryCode')) $updateData['countryCode'] = $request->input('countryCode');
            if ($request->has('shippingAddress')) $updateData['shippingAddress'] = json_encode($request->input('shippingAddress'));
            if ($request->has('location')) $updateData['location'] = json_encode($request->input('location'));
            if ($request->has('zoneId')) $updateData['zoneId'] = $request->input('zoneId');

            if (!empty($updateData)) {
                $user->update($updateData);
                $user->refresh();
            }

            // Get subscription plan if exists
            $subscriptionPlan = $this->getSubscriptionPlan($user);

            // Format response
            $data = $this->formatUserProfile($user, $subscriptionPlan);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Update User Profile Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user profile',
                'error' => config('app.debug') ? $e->getMessage() : null
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

        // If it's a string, decode it
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

        // If it's not an array at this point, return empty
        if (!is_array($shippingAddress)) {
            return [];
        }

        // Ensure it's a list of addresses
        $addresses = [];
        
        // If it's a single address (associative array), wrap it
        if (isset($shippingAddress['address']) || isset($shippingAddress['locality'])) {
            $addresses = [$shippingAddress];
        } else {
            $addresses = $shippingAddress;
        }

        // Format each address
        return array_map(function($addr) {
            if (!is_array($addr)) {
                return null;
            }

            return [
                'id' => $addr['id'] ?? null,
                'address' => $addr['address'] ?? '',
                'addressAs' => $addr['addressAs'] ?? '',
                'landmark' => $addr['landmark'] ?? '',
                'locality' => $addr['locality'] ?? '',
                'location' => isset($addr['location']) ? [
                    'latitude' => (float) ($addr['location']['latitude'] ?? 0),
                    'longitude' => (float) ($addr['location']['longitude'] ?? 0),
                ] : null,
                'isDefault' => (bool) ($addr['isDefault'] ?? false),
                'zoneId' => $addr['zoneId'] ?? null,
            ];
        }, array_filter($addresses));
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

