<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\FirestoreUtilsController;
use App\Mail\SetEmailData;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\documents_verify;
use App\Models\OnBoarding;
use App\Models\Order_Billing;
use App\Models\restaurant_orders;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DriverSqlBridgeController extends FirestoreUtilsController
{
    /**
     * Quick health-check to determine if a driver session should stay authenticated.
     */
    // public function isLogin(Request $request): JsonResponse
    // {
    //     $request->validate([
    //         'uid' => 'required|string',
    //     ]);

    //     $exists = User::query()->where('firebase_id', $request->uid)->exists();

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'isLogin' => $exists,
    //         ],
    //     ]);
    // }

    /**
     * Lightweight existence lookup used by the Flutter app before bootstrapping.
     */
    public function userExistOrNot(string $uid): JsonResponse
    {
        $exists = User::query()->where('firebase_id', $uid)->exists();

        return response()->json([
            'success' => true,
            'data' => $exists,
        ]);
    }

    /**
     * Fetch full driver profile using Firebase id.
     */
//    public function getDriverProfile(string $uid): JsonResponse
//    {
//        $user = User::query()->where('firebase_id', $uid)->first();
//
//        if (!$user) {
//            return response()->json([
//                'success' => false,
//                'message' => 'Driver not found',
//            ], 404);
//        }
//
//        return response()->json([
//            'success' => true,
//            'data' => $this->mapDriverPayload($user),
//        ]);
//    }

    /**
     * Update driver record with sanitized payload (mirrors Firestore set).
     */
    public function updateDriver(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'nullable|string',
            'firebase_id' => 'nullable|string',
        ]);

        // Prefer firebase_id → else use id
        $identifier = $request->firebase_id ?? $request->id;

        if(!$identifier){
            return response()->json([
                "success" => false,
                "message" => "id or firebase_id is required"
            ],422);
        }

        // Fetch using either one
        $user = User::where('firebase_id', $identifier)->first();

        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Driver not found"
            ],404);
        }

        $columns = Schema::getColumnListing('users');
        $exclude = ['id','firebase_id','created_at','updated_at'];
        $allowed = array_diff($columns,$exclude);
        $incoming = $request->all();

        if(isset($incoming['shippingAddress'])){
            $incoming['shippingAddress'] = json_encode($incoming['shippingAddress']);
        }

        $payload = array_intersect_key($incoming, array_flip($allowed));

        if(empty($payload)){
            return response()->json([
                "success" => false,
                "message" => "No valid fields to update"
            ],422);
        }

        $user->update($payload);

        return response()->json([
            "success" => true,
            "message" => "Driver updated successfully",
            "updated_fields" => $payload
        ]);
    }

//    public function updateDriver(Request $request): JsonResponse
//    {
//        $request->validate([
//            'firebase_id' => 'required|string',
//        ]);
//
//        // Check if user exists
//        $user = User::where('firebase_id', $request->firebase_id)->first();
//
//        if (!$user) {
//            return response()->json([
//                'success' => false,
//                'message' => 'Driver not found'
//            ], 404);
//        }
//
//        // Get table columns
//        $columns = Schema::getColumnListing('users');
//
//        // fields that cannot be updated
//        $exclude = ['id','firebase_id','created_at','updated_at'];
//
//        $allowed = array_diff($columns,$exclude);
//        $incoming = $request->all();
//
//        // Flatten/encode nested address if exists
//        if(isset($incoming['shippingAddress'])){
//            $incoming['shippingAddress'] = json_encode($incoming['shippingAddress']);
//        }
//
//        // Extract valid fields only
//        $payload = array_intersect_key($incoming, array_flip($allowed));
//
//        if(empty($payload)){
//            return response()->json([
//                "success" => false,
//                "message" => "No valid fields to update"
//            ],422);
//        }
//
//        $user->update($payload);
//
//        return response()->json([
//            "success" => true,
//            "message" => "Driver updated successfully",
//            "updated_fields" => $payload
//        ]);
//    }

    /**
     * Increment / decrement driver wallet balance atomically.
     */
    public function updateUserWallet(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {
            $user = User::query()
                ->where('firebase_id', $request->user_id)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Driver not found',
                ], 404);
            }

            $user->wallet_amount = (float) $user->wallet_amount + (float) $request->amount;
            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet updated',
                'data' => [
                    'wallet_amount' => $user->wallet_amount,
                ],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('updateUserWallet failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update wallet',
            ], 500);
        }
    }

    /**
     * Increment driver delivery-amount field.
     */
    public function updateUserDeliveryAmount(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $updated = User::query()
            ->where('firebase_id', $request->user_id)
            ->increment('deliveryAmount', (float) $request->amount);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Delivery amount updated',
        ]);
    }

    /**
     * Return onboarding screens filtered for driver app by default.
     */
    public function getDriverOnBoardingList(Request $request): JsonResponse
    {
        $type = $request->input('type', 'driverApp');

        $records = OnBoarding::query()
            ->where('type', $type)
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    /**
     * Fetch vendors inside currently selected zone (used for driver preferences).
     */
    public function getDriverZoneVendors(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => 'required|string',
        ]);

        $vendors = Vendor::query()
            ->where('zoneId', $request->zone_id)
            ->orderBy('title')
            ->get()
            ->map(function ($vendor) {
                return [
                    'id' => $vendor->_id ?? $vendor->id,
                    'title' => $vendor->title,
                    'description' => $vendor->description,
                    'phonenumber' => $vendor->phonenumber,
                    'latitude' => (float) $vendor->latitude,
                    'longitude' => (float) $vendor->longitude,
                    'location' => $vendor->location,
                    'zoneId' => $vendor->zoneId,
                    'restaurant_slug' => $vendor->restaurant_slug,
                    'zone_slug' => $vendor->zone_slug,
                    'photos' => json_decode($vendor->photos ?? '[]', true),
                    'categoryID' => json_decode($vendor->categoryID ?? '[]', true),
                    'categoryTitle' => json_decode($vendor->categoryTitle ?? '[]', true),
                    'filters' => json_decode($vendor->filters ?? '{}', true),
                    'workingHours' => json_decode($vendor->workingHours ?? '[]', true),
                    'specialDiscount' => json_decode($vendor->specialDiscount ?? '[]', true),
                    'vType' => $vendor->vType,
                    'walletAmount' => $vendor->walletAmount,
                    'subscriptionPlanId' => $vendor->subscriptionPlanId,
                    'subscription_plan' => $vendor->subscription_plan,
                    'subscriptionExpiryDate' => $vendor->subscriptionExpiryDate,
                    'publish' => (bool) $vendor->publish,
                    'reststatus' => (bool) $vendor->reststatus,
                    'isSelfDelivery' => (bool) $vendor->isSelfDelivery,
                    'enabledDelivery' => $vendor->enabledDelivery,
                    'author' => $vendor->author,
                    'authorName' => $vendor->authorName,
                    'authorProfilePic' => $vendor->authorProfilePic,
                    'restaurantCost' => $vendor->restaurantCost,
                    'cuisineID' => $vendor->cuisineID,
                    'cuisineTitle' => $vendor->cuisineTitle,
                    'DeliveryCharge' => (bool) $vendor->DeliveryCharge,
                    'closeDineTime' => $vendor->closeDineTime,
                    'openDineTime' => $vendor->openDineTime,
                    'lastAutoScheduleUpdate' => $vendor->lastAutoScheduleUpdate,
                    'enabledDiveInFuture' => (bool) $vendor->enabledDiveInFuture,
                    'hidephotos' => (bool) $vendor->hidephotos,
                    'reviewsCount' => (int) $vendor->reviewsCount,
                    'reviewsSum' => (int) $vendor->reviewsSum,
                    'adminCommission' => json_decode($vendor->adminCommission ?? '{}', true),
                    'g' => json_decode($vendor->g ?? '{}', true),
                    'coordinates' => json_decode($vendor->coordinates ?? '{}', true),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $vendors,
        ]);
    }


    /**
     * Store delivery wallet ledger rows (one per transaction).
     */
    public function setDriverWalletRecord(Request $request): JsonResponse
    {
        $request->validate([
            'firebase_id' => 'required|string',
            'driverId' => 'required|string',
            'totalEarnings' => 'required|numeric',
        ]);

        $table = 'delivery_wallet_record';

        // Only include valid table columns
        $payload = $this->filterColumns($request->all(), $table);

        // Default date if not provided
        $payload['date'] = $payload['date'] ?? now();

        // If ID exists, update; otherwise, insert new
        if (!empty($request->id)) {
            DB::table($table)->where('id', $request->id)->update($payload);
            $recordId = $request->id;
        } else {
            $recordId = DB::table($table)->insertGetId($payload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver wallet record saved',
            'id' => $recordId, // return ID for frontend reference
        ]);
    }

    /**
     * Fetch driver total charge config (pickup + delivery share).
     */
    public function getDriverCharges(): JsonResponse
    {
        $setting = Setting::where('document_name', 'driver_total_charges')->first();

        // Fix → decode only when field is a JSON string
        $charges = $setting && is_string($setting->fields)
            ? json_decode($setting->fields, true)
            : (is_array($setting->fields) ? $setting->fields : []);

        return response()->json([
            'success' => true,
            'data' => [
                'pickup_charges'        => Arr::get($charges, 'pickup_charges', '0'),
                'user_delivery_charge'  => Arr::get($charges, 'user_delivery_charge', '0'),
            ],
        ]);
    }



    /**
     * Aggregate all realtime settings the driver application expects.
     */
    public function getDriverSettings(): JsonResponse
    {
        $documents = [
            'globalSettings',
            'googleMapKey',
            'notification_setting',
            'RestaurantNearBy',
            'privacyPolicy',
            'termsAndConditions',
            'Version',
            'referral_amount',
            'emailSetting',
            'placeHolderImage',
            'document_verification_settings',
            'DriverNearBy',
        ];

        $settings = Setting::query()
            ->whereIn('document_name', $documents)
            ->get()
            ->mapWithKeys(function ($row) {
                $fields = $row->fields ?? [];
                if (is_string($fields)) {
                    $decoded = json_decode($fields, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $fields = $decoded;
                    }
                }

                return [$row->document_name => $fields];
            });

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Driver wallet transactions ordered by descending date.
     */
    public function getDriverWalletTransactions(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|string',
        ]);

        $transactions = DB::table('wallet')
            ->where('user_id', $request->driver_id)
            ->get()
            ->map(function ($item) {

                // clean invalid date format "\"2025-10-14T07:26:42.436000Z\""
                $date = trim($item->date, '"');  // removes starting & ending quotes

                $item->date = $date; // update cleaned date

                // convert to readable format (optional)
                if ($date) {
                    try {
                        $item->date = \Carbon\Carbon::parse($date)->format("Y-m-d H:i:s");
                    } catch (\Exception $e) {
                        $item->date = null;
                    }
                }

                return $item;
            });

        // finally sort by cleaned date
        $transactions = $transactions->sortByDesc('date')->values();

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Driver delivery wallet ledger (new SQL table mirrors Firestore collection).
     */
    public function getDriverAmountWalletTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|string',
        ]);

        $records = DB::table('delivery_wallet_record')
            ->where('driverId', $request->driver_id)
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    /**
     * Pull tax slabs by country (driver app already resolves country via geocoding).
     */
    public function getDriverTaxList(Request $request): JsonResponse
    {
        $request->validate([
            'country' => 'required|string',
        ]);

        $taxRows = DB::table('tax')
            ->where('country', $request->country)
            ->where('enable', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $taxRows,
        ]);
    }

    /**
     * Wallet bookkeeping invoked after driver confirms delivery.
     */
    public function updateWalletAmount(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string',
            'driver_id' => 'required|string',
        ]);

        $order = DB::table('restaurant_orders')
            ->where('id', $request->order_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $driverAmount = $this->calculateDriverWalletDelta($order);

        DB::beginTransaction();

        try {
            $user = User::query()
                ->where('firebase_id', $request->driver_id)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Driver not found',
                ], 404);
            }

            $user->wallet_amount = (float) $user->wallet_amount + $driverAmount;
            $user->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('updateWalletAmount failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update wallet',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'delta' => $driverAmount,
            ],
        ]);
    }

    /**
     * Get cuisines/tags derived from vendor products.
     */
    public function getVendorCuisines(string $vendorId): JsonResponse
    {
        $categoryIds = DB::table('vendor_products')
            ->where('vendorID', $vendorId)
            ->pluck('categoryID')
            ->filter()
            ->unique()
            ->toArray();

        if (empty($categoryIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $tags = DB::table('vendor_categories')
            ->whereIn('id', $categoryIds)
            ->pluck('title')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * Hard delete driver, their documents and wallet history.
     */
    public function deleteDriver(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|string',
        ]);

        try {
            DB::transaction(function () use ($request) {
                DB::table('wallet')->where('user_id', $request->driver_id)->delete();
                DB::table('delivery_wallet_record')->where('driverId', $request->driver_id)->delete();

                // If documents_verify has column driverID instead of id → fix here
                documents_verify::query()->where('id', $request->driver_id)->delete();

                DriverPayout::query()->where('driverID', $request->driver_id)->delete();
                User::query()->where('firebase_id', $request->driver_id)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Driver and related wallet records removed successfully.',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send wallet top-up email using stored templates.
     */
    public function sendTopUpMail(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|string',
            'amount' => 'required|numeric',
            'transaction_id' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        $user = User::query()->where('firebase_id', $request->driver_id)->first();
        if (!$user || empty($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Driver email not found',
            ], 404);
        }

        $template = DB::table('email_templates')
            ->where('type', 'wallet_topup')
            ->first();

        $body = $template ? $template->message : 'Wallet top-up confirmation for {username}, amount {amount}.';
        $subject = $template ? $template->subject : 'Wallet top-up confirmation';

        $replacements = [
            '{username}' => trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
            '{amount}' => number_format((float) $request->amount, 2),
            '{paymentmethod}' => $request->payment_method,
            '{transactionid}' => $request->transaction_id,
            '{date}' => now()->toDateString(),
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $body);

        Mail::to($user->email)->send(new SetEmailData($subject, $body));

        return response()->json([
            'success' => true,
            'message' => 'Top-up email queued',
        ]);
    }

    /**
     * Driver payout email helper.
     */
    public function sendPayoutMail(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|string',
            'amount' => 'required|numeric',
            'payout_request_id' => 'required|string',
        ]);

        $user = User::query()->where('firebase_id', $request->driver_id)->first();
        if (!$user || empty($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Driver email not found',
            ], 404);
        }

        $template = DB::table('email_templates')
            ->where('type', 'payout_request')
            ->first();

        $body = $template ? $template->message : 'Payout request {payoutrequestid} for amount {amount}.';
        $subject = $template ? $template->subject : 'Driver payout request';

        $replacements = [
            '{username}' => trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
            '{userid}' => $user->firebase_id,
            '{amount}' => number_format((float) $request->amount, 2),
            '{payoutrequestid}' => $request->payout_request_id,
            '{usercontactinfo}' => $user->email . PHP_EOL . ($user->phoneNumber ?? ''),
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $body);

        Mail::to($user->email)->send(new SetEmailData($subject, $body));

        return response()->json([
            'success' => true,
            'message' => 'Payout email queued',
        ]);
    }

    /**
     * Determine if author has any order history aside from supplied order.
     */
    public function getFirstOrderOrNot(string $authorID): JsonResponse
    {
        $order = DB::table('restaurant_orders')->where('authorID', $authorID)->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $count = DB::table('restaurant_orders')
            ->where('authorID', $authorID)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'isFirstOrder' => $count <= 1,
            ],
        ]);
    }

    /**
     * Propagate referral reward to parent user when criteria met.
     */
//    public function updateReferralAmount(Request $request): JsonResponse
//    {
//        $request->validate([
//            'author_id' => 'required|string',
//            'order_id' => 'required|string',
//        ]);
//
//        $referral = DB::table('referral')
//            ->where('id', $request->author_id)
//            ->first();
//
//        if (!$referral || empty($referral->referralBy)) {
//            return response()->json([
//                'success' => true,
//                'message' => 'Referral not found or no parent user',
//            ]);
//        }
//
//        $settings = Setting::getByDocument('referral_amount');
//        $amount = (float) Arr::get($settings, 'referralAmount', 0);
//
//        if ($amount <= 0) {
//            return response()->json([
//                'success' => true,
//                'message' => 'Referral amount disabled',
//            ]);
//        }
//
//        DB::transaction(function () use ($referral, $amount, $request) {
//            DB::table('wallet')->insert([
//                'id' => (string) Str::uuid(),
//                'date' => now()->toIso8601String(),
//                'note' => "Referral bonus for order #{$request->order_id}",
//                'transactionUser' => 'driver',
//                'amount' => $amount,
//                'user_id' => $referral->referralBy,
//                'payment_status' => 'success',
//                'isTopUp' => 1,
//                'order_id' => $request->order_id,
//                'payment_method' => 'referral',
//            ]);
//
//            User::query()
//                ->where('firebase_id', $referral->referralBy)
//                ->increment('wallet_amount', $amount);
//        });
//
//        return response()->json([
//            'success' => true,
//            'message' => 'Referral amount credited',
//        ]);
//    }



    public function getReferralById($id)
    {
        $referral = Referral::where('id', $id)->first();

        if (!$referral) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $referral
        ], 200);
    }

    /**
     * Atomically assign a driver to order if still pending.
     */
    public function assignOrderToDriverFCFS(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string',
            'driver_id' => 'required|string',
        ]);

        try {
            $success = DB::transaction(function () use ($request) {

                // Get the order with row lock to prevent race condition
                $order = DB::table('restaurant_orders')
                    ->where('id', $request->order_id)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    return false; // Order not found
                }

                // Order assignable?
                $isAvailable = empty($order->driverID)
                    || in_array($order->status, ['driverPending','Order Placed','Order Accepted']);

                if (!$isAvailable) {
                    return false; // Order already assigned
                }

                // Fetch driver details to store inside order record
                $driver = DB::table('users')->where('firebase_id', $request->driver_id)->first();

                if (!$driver) return false;

                $driverData = [
                    'id'               => $driver->id,
                    'firebase_id'      => $driver->firebase_id,
                    'firstName'        => $driver->firstName,
                    'lastName'         => $driver->lastName,
                    'email'            => $driver->email,
                    'profilePictureURL'=> $driver->profilePictureURL,
                    'fcmToken'         => $driver->fcmToken,
                    'countryCode'      => $driver->countryCode,
                    'phoneNumber'      => $driver->phoneNumber,
                    'createdAt' => $driver->createdAt,
                    'isActive'         => $driver->active,
                    'role' => $driver->role,
                    'isDocumentVerify' => $driver->isDocumentVerify,
                    'location' => $driver->location,
                    'userBankDetails' => $driver->userBankDetails,
                    'shippingAddress' => $driver->shippingAddress,
                    'appIdentifier' => $driver->appIdentifier,
                    'provider' => $driver->provider,
                    'vendorID' => $driver->vendorID,
                    'inProgressOrderID' => $driver->inProgressOrderID,
                    'rotation' => $driver->rotation,
                    'orderRequestData' => $driver->orderRequestData,
                    'wallet_amount'    => $driver->wallet_amount,
                    'deliveryAmount'   => $driver->deliveryAmount,
                    'carName'          => $driver->carName,
                    'carNumber'        => $driver->carNumber,
                    'carPictureURL'    => $driver->carPictureURL,
                    'zoneId'           => $driver->zoneId,
                ];

                // Update order table
                DB::table('restaurant_orders')
                    ->where('id', $request->order_id)
                    ->update([
                        'driverID' => $request->driver_id,
                        'driver'   => json_encode($driverData), // store full driver JSON like firestore
                        'status'   => 'Driver Accepted',
                    ]);

                return true;
            });

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already assigned!',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order successfully assigned to driver',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getDriverPayoutsByDriver(Request $request)
    {
        $request->validate([
            'driverID' => 'required|string'
        ]);

        $payouts = DB::table('driver_payouts')
            ->where('driverID', $request->driverID)
            ->select('id','note','amount','withdrawMethod','paidDate','driverID','vendorID','adminNote','paymentStatus')
            ->orderBy('paidDate','DESC')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Driver payout history fetched successfully',
            'data' => $payouts
        ]);
    }


    public function addDriverPayout(Request $request)
    {
        $request->validate([
            'id' => 'required|string|unique:driver_payouts,id',
            'note' => 'nullable|string',
            'amount' => 'nullable|string',
            'withdrawMethod' => 'nullable|string',
            'paidDate' => 'nullable|string',
            'driverID' => 'nullable|string',
            'vendorID' => 'nullable|string',
            'adminNote' => 'nullable|string',
            'paymentStatus' => 'nullable|string',
        ]);

        $payout = DriverPayout::create([
            'id' => $request->id,
            'note' => $request->note,
            'amount' => $request->amount,
            'withdrawMethod' => $request->withdrawMethod,
            'paidDate' => $request->paidDate,
            'driverID' => $request->driverID,
            'vendorID' => $request->vendorID,
            'adminNote' => $request->adminNote,
            'paymentStatus' => $request->paymentStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout stored successfully',
            'data' => $payout
        ]);
    }


    /**
     * Remove order from every driver's request queue except assigned one.
     */
    public function removeOrderFromOtherDrivers(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string',
            'assigned_driver_id' => 'required|string',
        ]);

        $drivers = User::query()
            ->where('role', 'driver')
            ->whereNotNull('orderRequestData')
            ->get();

        foreach ($drivers as $driver) {
            if ($driver->firebase_id === $request->assigned_driver_id) {
                continue;
            }

            $payload = json_decode($driver->orderRequestData ?? '[]', true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($payload)) {
                continue;
            }

            $filtered = array_values(array_filter($payload, function ($entry) use ($request) {
                return $entry !== $request->order_id;
            }));

            if ($filtered !== $payload) {
                User::query()
                    ->where('id', $driver->id)
                    ->update([
                        'orderRequestData' => json_encode($filtered),
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order removed from other drivers',
        ]);
    }

    /**
     * Helper: format driver payload.
     */
    protected function mapDriverPayload(User $user): array
    {
        return [
            'id' => $user->firebase_id,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'email' => $user->email,
            'phoneNumber' => $user->phoneNumber,
            'countryCode' => $user->countryCode,
            'role' => $user->role,
            'active' => (bool) $user->active,
            'walletAmount' => (float) ($user->wallet_amount ?? 0),
            'deliveryAmount' => (float) ($user->deliveryAmount ?? 0),
            'zoneId' => $user->zoneId,
            'vehicleType' => $user->vType,
            'location' => $this->decodeJsonField($user->location),
            'photos' => $this->decodeJsonField($user->photos, []),
            'settings' => $this->decodeJsonField($user->settings),
            'orderRequestData' => $this->decodeJsonField($user->orderRequestData, []),
        ];
    }

    /**
     * Helper: decode json-ish column safely.
     */
    protected function decodeJsonField($value, $default = null)
    {
        if (empty($value) || !is_string($value)) {
            return $default;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /**
     * Helper: limit associative array to valid table columns.
     */
    protected function filterColumns(array $payload, string $table): array
    {
        static $cache = [];

        if (!isset($cache[$table])) {
            $cache[$table] = Schema::getColumnListing($table);
        }

        return array_intersect_key($payload, array_flip($cache[$table]));
    }

    /**
     * Compute wallet delta for a driver based on stored order payload.
     */
    protected function calculateDriverWalletDelta(object $order): float
    {
        $products = $this->decodeJsonField($order->products, []);
        $subTotal = 0.0;

        foreach ($products as $product) {
            $quantity = (float) Arr::get($product, 'quantity', 0);
            $price = (float) Arr::get($product, 'discountPrice', Arr::get($product, 'price', 0));
            $extrasPrice = (float) Arr::get($product, 'extrasPrice', 0);
            $subTotal += ($price * $quantity) + ($extrasPrice * $quantity);
        }

        $specialDiscount = 0.0;
        $special = $this->decodeJsonField($order->specialDiscount, []);
        if (is_array($special) && isset($special['special_discount'])) {
            $specialDiscount = (float) $special['special_discount'];
        }

        $discount = (float) ($order->discount ?? 0);
        $adminCommission = (float) ($order->adminCommission ?? 0);
        $basePrice = $subTotal;

        if ($adminCommission > 0) {
            $basePrice = $subTotal / (1 + ($adminCommission / 100));
        }

        $basePrice -= ($discount + $specialDiscount);
        $basePrice = max($basePrice, 0);

        $taxAmount = 0.0;
        $taxSetting = $this->decodeJsonField($order->taxSetting, []);
        if (is_array($taxSetting)) {
            foreach ($taxSetting as $tax) {
                $percent = (float) Arr::get($tax, 'tax', 0);
                if ($percent > 0) {
                    $taxAmount += ($subTotal - $discount - $specialDiscount) * ($percent / 100);
                }
            }
        }

        $payment = strtolower((string) ($order->payment_method ?? ''));
        $driverAmount = 0.0;

        if ($payment !== 'cod') {
            $driverAmount += (float) ($order->deliveryCharge ?? 0);
            $driverAmount += (float) ($order->tip_amount ?? 0);
        } else {
            $toPay = (float) ($order->ToPay ?? $order->toPayAmount ?? 0);
            $driverAmount -= $toPay;
        }

        return round($driverAmount, 2);
    }

    public function getCurrentOrder(Request $request)
    {
        $driverId = $request->driver_id;

        $driver = user::where('firebase_id', $driverId)->first();
        if (!$driver) {
            return response()->json(["success" => false, "message" => "Driver not found"], 404);
        }

        $inProgress   = json_decode($driver->inProgressOrderID ?? "[]");
        $orderRequest = json_decode($driver->orderRequestData ?? "[]");

        /** ───────────────────────────────
         * CASE 1: Driver already has an order
         * ───────────────────────────────*/
        if (!empty($inProgress)) {

            $orderId = $inProgress[0];

            $order = restaurant_orders::where('id', $orderId)
                ->whereNotIn('status', ['orderCancelled', 'driverRejected', 'orderCompleted'])
                ->first();

            if ($order) {
                return response()->json([
                    "success" => true,
                    "type" => "inProgress",
                    "order" => $order
                ]);
            }

            /** Order finished → Remove from driver */
            $inProgress = array_filter($inProgress, fn($id) => $id != $orderId);
            $driver->inProgressOrderID = json_encode(array_values($inProgress));
            $driver->save();

            return response()->json([
                "success" => false,
                "message" => "Order completed, removed from driver"
            ]);
        }

        /** ───────────────────────────────
         * CASE 2: Requested Orders Pending
         * ───────────────────────────────*/
        if (!empty($orderRequest)) {

            $orderId = $orderRequest[0];

            $order = restaurant_orders::where('id', $orderId)
                ->whereNotIn('status', ['orderCancelled','driverRejected'])
                ->first();

            if ($order) {
                return response()->json([
                    "success" => true,
                    "type" => "request",
                    "order" => $order
                ]);
            }

            /** Remove old request if not found */
            $orderRequest = array_filter($orderRequest, fn($id) => $id != $orderId);
            $driver->orderRequestData = json_encode(array_values($orderRequest));
            $driver->save();

            return response()->json([
                "success" => false,
                "message" => "Order not found, removed request"
            ]);
        }

        /** ───────────────────────────────
         * CASE 3: No Current Orders
         * ───────────────────────────────*/
        return response()->json([
            "success" => false,
            "message" => "No orders available"
        ]);
    }



    public function getDriver($id)
    {
        $driver = User::where('firebase_id', $id)->first();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => "Driver not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $driver
        ]);
    }


    public function todayCompletedOrders($driverId)
    {
            $todayStart = now()->startOfDay()->toIso8601String(); // e.g. 2025-12-01T00:00:00Z
        $todayEnd   = now()->endOfDay()->toIso8601String();   // e.g. 2025-12-01T23:59:59Z

        $count = Restaurant_Orders::where('driverID', $driverId)
            ->whereIn('status', ['completed', 'shipped'])
            ->where('createdAt', '>=', $todayStart)
            ->where('createdAt', '<=', $todayEnd)
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }



//    public function completeOrder(Request $request, $orderId)
//    {
//        DB::beginTransaction();
//        try {
//            // Fetch order
//            $order = Restaurant_Orders::where('id', $orderId)->first();
//            if (!$order) {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'Order not found'
//                ], 404);
//            }
//
//            // Ensure required fields
//            if (!$order->driverID) {
//                $order->driverID = $request->driver_id ?? null;
//            }
//
//            if (!$order->driverID || !$order->paymentMethod || !$order->deliveryCharge || !$order->tipAmount) {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'Order data incomplete. Cannot complete order.'
//                ], 400);
//            }
//
//            // Fetch ToPay from billing
//            $billing = Order_Billing::where('order_id', $order->id)->first();
//            if (!$billing || !$billing->ToPay) {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'Order billing info missing. Cannot complete order.'
//                ], 400);
//            }
//
//            $order->toPay = $billing->ToPay;
//            $order->status = 'completed'; // or 'shipped' depending on your logic
//            $order->save();
//
//            // Update driver wallet
//            DriverWallet::updateOrCreate(
//                ['driver_id' => $order->driverID],
//                ['amount' => DB::raw("amount + {$order->toPay}")]
//            );
//
//            // Remove order from other drivers (if you have a table storing assigned drivers)
//            DB::table('driver_orders')
//                ->where('order_id', $order->id)
//                ->where('driver_id', '!=', $order->driverID)
//                ->delete();
//
//            // Optional: Update user's inProgressOrderID/orderRequestData if needed
//            $user = User::find($order->driverID);
//            if ($user) {
//                // Remove order from user's lists if stored
//            }
//
//            // Optional: Update referral amount for first order
//            // Your logic here...
//
//            // Send notification (pseudo-code)
//            if ($order->author_fcm_token) {
//                SendNotification::sendFcmMessage(
//                    'Order Completed',
//                    $order->author_fcm_token,
//                    []
//                );
//            }
//
//            DB::commit();
//
//            return response()->json([
//                'success' => true,
//                'message' => 'Order completed successfully'
//            ]);
//
//        } catch (\Exception $e) {
//            DB::rollBack();
//            return response()->json([
//                'success' => false,
//                'message' => 'Failed to complete order: ' . $e->getMessage()
//            ], 500);
//        }
//    }

}

