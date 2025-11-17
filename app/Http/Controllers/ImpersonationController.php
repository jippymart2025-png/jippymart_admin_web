<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FirebaseImpersonationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    private $impersonationService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->impersonationService = new FirebaseImpersonationService();
    }

    /**
     * Generate impersonation token for a restaurant (Session-based approach)
     */
    public function generateToken(Request $request)
    {
        // Validate CSRF token
        $this->validateCSRFToken($request);

        // Validate request
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|string',
            'expiration_minutes' => 'integer|min:1|max:30', // Max 30 minutes for security
            '_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid request parameters',
                'details' => $validator->errors()
            ], 400);
        }

        // Sanitize and validate input
        $restaurantId = $this->sanitizeRestaurantId($request->input('restaurant_id'));
        $expirationMinutes = $this->sanitizeExpirationMinutes($request->input('expiration_minutes', 5));
        $adminUserId = Auth::id();

        // Check if admin has permission to impersonate (you can add role-based checks here)
        if (!$this->canImpersonate($adminUserId)) {
            return response()->json([
                'success' => false,
                'error' => 'Insufficient permissions to impersonate restaurants'
            ], 403);
        }

        // Generate the impersonation token
        $result = $this->impersonationService->generateImpersonationToken(
            $restaurantId,
            $adminUserId,
            $expirationMinutes
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 400);
        }

        // Store impersonation data in cache (shared between domains)
        $cacheKey = 'impersonation_' . hash('sha256', $result['restaurant_uid'] . '_' . time() . '_' . $adminUserId);
        $impersonationData = [
            'token' => $result['custom_token'],
            'restaurant_uid' => $result['restaurant_uid'],
            'restaurant_id' => $restaurantId,
            'restaurant_name' => $result['restaurant_name'],
            'timestamp' => time(),
            'admin_id' => $adminUserId,
            'expires_at' => time() + ($expirationMinutes * 60)
        ];

        // Store in cache for 10 minutes (longer than token expiration)
        \Illuminate\Support\Facades\Cache::put($cacheKey, $impersonationData, 600);

        // Create URL with cache key for restaurant panel to retrieve data
        $restaurantUrl = config('app.restaurant_panel_url', 'http://127.0.0.1:8001');
        $impersonationUrl = $restaurantUrl . '/login?impersonation_key=' . $cacheKey;

        return response()->json([
            'success' => true,
            'restaurant_name' => $result['restaurant_name'],
            'restaurant_uid' => $result['restaurant_uid'],
            'expires_in' => $result['expires_in'],
            'impersonation_url' => $impersonationUrl,
            'message' => "Impersonation initiated successfully. Redirecting to {$result['restaurant_name']}..."
        ]);
    }

    /**
     * Check if admin can impersonate restaurants
     */
    private function canImpersonate($adminUserId)
    {
        $user = Auth::user();

        // Check if user exists and is authenticated
        if (!$user) {
            return false;
        }

        // Check if user has admin role (uncomment when roles are implemented)
        // if (!$user->hasRole('admin')) {
        //     return false;
        // }

        // Check if user has impersonation permission (uncomment when permissions are implemented)
        // if (!$user->hasPermission('restaurants.impersonate')) {
        //     return false;
        // }

        // Check if user account is active
        if (isset($user->status) && $user->status !== 'active') {
            return false;
        }

        // Check if user is not suspended
        if (isset($user->is_suspended) && $user->is_suspended) {
            return false;
        }

        return true; // For now, allow all authenticated users
    }

    /**
     * Sanitize restaurant ID input
     */
    private function sanitizeRestaurantId($restaurantId)
    {
        if (!is_string($restaurantId) || strlen($restaurantId) > 100) {
            throw new \InvalidArgumentException('Invalid restaurant ID format');
        }

        // Check for potentially malicious patterns
        if (preg_match('/[<>"\'\x00-\x1f\x7f-\x9f]/', $restaurantId)) {
            throw new \SecurityException('Potentially malicious restaurant ID');
        }

        // Additional security checks
        if (preg_match('/\.\.|\/|\\|script|javascript|vbscript/i', $restaurantId)) {
            throw new \SecurityException('Invalid characters in restaurant ID');
        }

        // Check for SQL injection patterns
        if (preg_match('/(union|select|insert|update|delete|drop|create|alter|exec|execute)/i', $restaurantId)) {
            throw new \SecurityException('Suspicious SQL patterns detected');
        }

        return trim($restaurantId);
    }

    /**
     * Sanitize expiration minutes input
     */
    private function sanitizeExpirationMinutes($minutes)
    {
        $minutes = (int) $minutes;

        if ($minutes < 1 || $minutes > 30) {
            throw new \InvalidArgumentException('Expiration minutes must be between 1 and 30');
        }

        return $minutes;
    }

    /**
     * Validate CSRF token for impersonation requests
     */
    private function validateCSRFToken(Request $request)
    {
        $token = $request->header('X-CSRF-TOKEN') ?? $request->input('_token');
        $sessionToken = session()->token();

        if (!hash_equals($sessionToken, $token)) {
            throw new \SecurityException('CSRF token mismatch');
        }

        // Additional validation for impersonation requests
        $referrer = $request->header('Referer');
        $allowedReferrers = [
            'admin.jippymart.in',
            'localhost:8000',
            '127.0.0.1:8000'
        ];

        $isValidReferrer = false;
        foreach ($allowedReferrers as $allowed) {
            if (strpos($referrer, $allowed) !== false) {
                $isValidReferrer = true;
                break;
            }
        }

        if (!$isValidReferrer) {
            throw new \SecurityException('Invalid referrer for impersonation request');
        }
    }

    /**
     * Create a signed URL for restaurant panel with impersonation token
     */
    private function createSignedImpersonationUrl($customToken, $restaurantUid, $cacheKey)
    {
        // Get restaurant panel URL from config with fallback
        $baseUrl = config('app.restaurant_panel_url', 'https://restaurant.jippymart.in');

        // Fallback URLs for different environments
        $fallbackUrls = [
            'production' => 'https://restaurant.jippymart.in',
            'staging' => 'https://staging-restaurant.jippymart.in',
            'local' => 'http://127.0.0.1:8001'
        ];

        $environment = app()->environment();
        $baseUrl = $fallbackUrls[$environment] ?? $baseUrl;

        $params = http_build_query([
            'impersonation_token' => $customToken,
            'restaurant_uid' => $restaurantUid,
            'cache_key' => $cacheKey,
            'timestamp' => time(),
            'auto_login' => 'true'
        ]);

        // Redirect to login page with token - restaurant panel will handle the auto-login
        return $baseUrl . '/login?' . $params;
    }

    /**
     * Get restaurant information for impersonation
     */
    public function getRestaurantInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Restaurant ID is required'
            ], 400);
        }

        $restaurantId = $request->input('restaurant_id');
        $result = $this->impersonationService->getRestaurantInfo($restaurantId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 404);
        }

        return response()->json([
            'success' => true,
            'restaurant' => $result['restaurant'],
            'owner' => $result['owner']
        ]);
    }

    /**
     * Validate an impersonation token (for restaurant panel)
     */
    public function validateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Token is required'
            ], 400);
        }

        $token = $request->input('token');
        $result = $this->impersonationService->validateImpersonationToken($token);

        return response()->json($result);
    }
}
