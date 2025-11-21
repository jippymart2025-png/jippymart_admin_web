<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class restaurantUserController extends Controller
{
    public function getUserProfile(string $firebase_id): JsonResponse
    {
        try {
            $user = User::where('firebase_id', $firebase_id)->first(); // or 'uuid' column if separate

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }
            $locationString = $user->location;
            $location = json_decode($locationString, true);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->firebase_id,
                    'firstName' => $user->firstName,
                    "lastName" => $user->lastName,
                    'email' => $user->email,
                    'phone' => $user->phoneNumber ?? null,
                    'shippingAddress' => $user->shippingAddress,
                    'appIdentifier' => $user->appIdentifier,
                    'vendorID' => $user->vendorID,
                    'isDocumentVerify' => $user->isDocumentVerify,
                    'profile_pic' => $user->profilePictureURL ?? null,
                    "countryCode" => $user->countryCode ?? null,
                    "role" => $user->role ?? null,
                    "active" => $user->active ?? null,
                    "vType" => $user->vType ?? null,
                    "zoneId" => $user->zoneId ?? null,
                    "wallet_amount" => $user->wallet_amount ?? null,
                    "isActive" => $user->isActive ?? null,
                    "userBankDetails" => $user->userBankDetails ?? null,
                    "photos" => $user->photos ?? null,
                    'location' => $location,   // 👈 Added here
                    '_created_at' => $user->_created_at ?? null,
                    '_updated_at' => $user->_updated_at ?? null,
                    'orderCompleted' => $user->orderCompleted ?? null,
                    'orderRequestData' => $user->orderRequestData ?? null,
                    'subscriptionPlanId' => $user->subscriptionPlanId ?? null,
                    'subscription_plan' => $user->subscriptionPlan ?? null,
                    'subscriptionExpiryDate' => $user->subscriptionExpiryDate ?? null,
                    'fcmToken' => $user->fcmToken ?? null,
                    'inProgressOrderID' => $user->inProgressOrderID ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user profile: ' . $e->getMessage(),
            ], 500);
        }
    }
}
