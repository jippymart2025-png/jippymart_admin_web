<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class restaurantControllerLogin extends Controller
{
    public function restaurantLogin(Request $request)
    {
        $request->validate([
            "email" => "required|email",
            "password" => "required"
        ]);

        // Check if user exists
        $user = User::where("email", $request->email)->first();

        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "No user found for that email."
            ], 404);
        }

        // Check driver role only
        if ($user->role !== "vendor") {
            return response()->json([
                "success" => false,
                "message" => "This user is not created in vendor application."
            ], 403);
        }

        // Check active
        if ((int)$user->active !== 1) {
            return response()->json([
                "success" => false,
                "message" => "This user is disabled, please contact administrator."
            ], 403);
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                "success" => false,
                "message" => "Wrong password provided for that user."
            ], 401);
        }

        // Update FCM token
        if ($request->has('fcmToken')) {
            $user->fcm_token = $request->fcm_token;
            $user->save();
        }

        return response()->json([
            "success" => true,
            "message" => "Login successful",
            "data" => $user
        ], 200);
    }


    public function restaurantSignup(Request $request): \Illuminate\Http\JsonResponse
    {
        // Common validation
        $request->validate([
            "type" => "required",  // email / mobileNumber / google / apple
            "first_name" => "required",
            "last_name" => "required",
            "zone_id" => "required",
            "app_identifier" => "required"  // android or ios
        ]);

        // Auto-approve settings
        $autoApprove = false;
        $isDocumentVerify = false;

        // EMAIL SIGNUP
        if ($request->type == "email") {

            $request->validate([
                "email" => "required|email|unique:users,email",
                "password" => "required|min:6"
            ]);

            $firebaseId = $this->generateFirebaseId();

            $user = User::create([
                "firebase_id" => $firebaseId,
                "firstName" => $request->first_name,
                "lastName" => $request->last_name,
                "email" => strtolower($request->email),
                "phoneNumber" => $request->phone_number,
                "countryCode" => $request->country_code,
                "password" => Hash::make($request->password),
                "role" => "vendor",
                "vType" => "restaurant",
                "fcmToken" => $request->fcm_token,
                "isActive" => $autoApprove ? 0 : 1,
                "isDocumentVerify" => $isDocumentVerify ? 0 : 1,
                "zoneId" => $request->zone_id,
                "provider" => "email",
                "appIdentifier" => $request->app_identifier,
            ]);

            return response()->json([
                "success" => true,
                "auto_approve" => $autoApprove,
                "message" => $autoApprove ? "Account created successfully" : "Your signup is under approval.",
                "data" => $user
            ]);
        }

        return response()->json([
            "success" => false,
            "message" => "Invalid signup type"
        ], 400);
    }

    // --- FIREBASE ID GENERATOR ---
    private function generateFirebaseId($length = 20)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $max = strlen($characters) - 1;

        do {
            $id = '';

            for ($i = 0; $i < $length; $i++) {
                $id .= $characters[random_int(0, $max)];
            }

            // Check if this ID exists in users table
            $exists = \DB::table('users')->where('firebase_id', $id)->exists();

        } while ($exists);

        return $id;
    }




}
