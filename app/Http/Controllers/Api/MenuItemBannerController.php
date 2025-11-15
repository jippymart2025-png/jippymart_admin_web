<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MenuItemBannerController extends Controller
{
    /**
     * Get Top Menu Item Banners
     * GET /api/menu-items/banners/top
     *
     * Purpose: Get top position menu item banners for home page
     *
     * Query Parameters:
     * - zone_id (optional): Filter banners by zone
     *
     * Business Logic:
     * 1. Filter where is_publish = true AND position = "top"
     * 2. Zone Filtering:
     *    - If banner has no zoneId or empty → show to all zones
     *    - If user zone matches banner zoneId → show
     *    - If user zone is null → show all (fallback)
     * 3. Order by set_order ASC
     */
    public function top(Request $request)
    {
        return $this->getMenuItemsByPosition($request, 'top');
    }

    public function middle(Request $request)
    {
        return $this->getMenuItemsByPosition($request, 'middle');
    }

    public function bottom(Request $request)
    {
        return $this->getMenuItemsByPosition($request, 'bottom');
    }

    /**
     * Common function to fetch menu items by position
     */
    private function getMenuItemsByPosition(Request $request, string $position)
    {
        // Validate optional zone_id parameter
        $validator = Validator::make($request->all(), [
            'zone_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $zoneId = $request->input('zone_id');

        try {
            // Base query: published menu items with given position
            $query = MenuItem::where('is_publish', true)
                ->where('position', $position);

            // Apply zone filtering logic
            if ($zoneId) {
                $query->where(function ($q) use ($zoneId) {
                    $q->where('zoneId', $zoneId)
                        ->orWhereNull('zoneId')
                        ->orWhere('zoneId', '');
                });
            }

            // Order by set_order
            $query->orderBy('set_order', 'asc');

            // Get menu items
            $menuItems = $query->get();

            // Format response
            $data = $menuItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title ?? '',
                    'photo' => $item->photo ?? '',
                    'position' => $item->position ?? '',
                    'is_publish' => (bool) $item->is_publish,
                    'set_order' => (int) ($item->set_order ?? 0),
                    'zoneId' => $item->zoneId ?? null,
                    'zoneTitle' => $item->zoneTitle ?? null,
                    'redirect_type' => $item->redirect_type ?? null,
                    'redirect_id' => $item->redirect_id ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error("Get {$position} Menu Item Error: " . $e->getMessage(), [
                'zone_id' => $zoneId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Failed to fetch {$position} menu item banners",
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get All Menu Item Banners
     * GET /api/menu-items/banners
     */
    public function index(Request $request)
    {
        try {
            $zoneId = $request->input('zone_id');
            $position = $request->input('position'); // optional filter by position

            $query = MenuItem::where('is_publish', true);

            // Filter by position if provided
            if ($position) {
                $query->where('position', $position);
            }

            // Apply zone filtering
            if ($zoneId) {
                $query->where(function($q) use ($zoneId) {
                    $q->where('zoneId', $zoneId)
                      ->orWhereNull('zoneId')
                      ->orWhere('zoneId', '');
                });
            }

            $query->orderBy('set_order', 'asc');
            $banners = $query->get();

            $data = $banners->map(function ($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title ?? '',
                    'photo' => $banner->photo ?? '',
                    'position' => $banner->position ?? '',
                    'is_publish' => (bool) $banner->is_publish,
                    'set_order' => (int) ($banner->set_order ?? 0),
                    'zoneId' => $banner->zoneId ?? null,
                    'zoneTitle' => $banner->zoneTitle ?? null,
                    'redirect_type' => $banner->redirect_type ?? null,
                    'redirect_id' => $banner->redirect_id ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get Menu Item Banners Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch menu item banners',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get Single Menu Item Banner
     * GET /api/menu-items/banners/{id}
     */
    public function show($id)
    {
        try {
            $banner = MenuItem::find($id);

            if (!$banner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu item banner not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $banner->id,
                    'title' => $banner->title ?? '',
                    'photo' => $banner->photo ?? '',
                    'position' => $banner->position ?? '',
                    'is_publish' => (bool) $banner->is_publish,
                    'set_order' => (int) ($banner->set_order ?? 0),
                    'zoneId' => $banner->zoneId ?? null,
                    'zoneTitle' => $banner->zoneTitle ?? null,
                    'redirect_type' => $banner->redirect_type ?? null,
                    'redirect_id' => $banner->redirect_id ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Menu Item Banner Error: ' . $e->getMessage(), ['id' => $id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch menu item banner',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

