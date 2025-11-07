<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CouponApiController extends Controller
{
    /**
     * Get coupons by type (restaurant / mart)
     * GET /api/coupons/restaurant
     * GET /api/coupons/mart
     */
    public function byType(Request $request, string $type)
    {
        try {
            // Allow only 'restaurant' or 'mart'
            if (!in_array($type, ['restaurant', 'mart'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid coupon type. Allowed: restaurant, mart',
                ], 400);
            }

            $now = Carbon::now('UTC')->toDateTimeString();

            // Query active, public coupons of given type
            $coupons = Coupon::query()
                ->where('isEnabled', true)
                ->where('isPublic', true)
                ->where('cType', $type)
                ->orderBy('expiresAt', 'asc')
                ->get();

            $data = $coupons->map(function (Coupon $coupon) {
                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code ?? '',
                    'discount' => (string) ($coupon->discount ?? '0'),
                    'discountType' => $coupon->discountType ?? '',
                    'cType' => $coupon->cType ?? '',
                    'resturantId' => $coupon->resturant_id ?? '',
                    'expiresAt' => $this->formatTimestamp($coupon->expiresAt),
                    'isEnabled' => (bool) $coupon->isEnabled,
                    'isPublic' => (bool) $coupon->isPublic,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Coupon fetch failed: ' . $e->getMessage(), [
                'type' => $type,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch coupons',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function formatTimestamp($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
