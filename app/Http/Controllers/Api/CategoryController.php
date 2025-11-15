<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorCategory;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * Get Home Page Categories
     * GET /api/categories/home
     *
     * Purpose: Get categories to display on home page
     *
     * Business Logic:
     * - Filter where show_in_homepage = true AND publish = true
     * - Order by display order (if available)
     */
    public function home()
    {
        try {
            // Get categories for homepage
            $categories = VendorCategory::where('show_in_homepage', true)
                ->where('publish', true)
                ->orderBy('title', 'asc') // Default order by title
                ->get();

            // Format response
            $data = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title ?? '',
                    'photo' => $category->photo ?? '',
                    'show_in_homepage' => (bool) $category->show_in_homepage,
                    'publish' => (bool) $category->publish,
                    'description' => $category->description ?? '',
                    'vType' => $category->vType ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Get Home Categories Error: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch home categories',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get All Categories
     * GET /api/categories
     */
    public function index()
    {
        try {
            $categories = VendorCategory::where('publish', true)
                ->orderBy('title', 'asc')
                ->get();

            $data = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title ?? '',
                    'photo' => $category->photo ?? '',
                    'show_in_homepage' => (bool) $category->show_in_homepage,
                    'publish' => (bool) $category->publish,
                    'description' => $category->description ?? '',
                    'vType' => $category->vType ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get Categories Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get Single Category
     * GET /api/categories/{id}
     */
//    public function show($id)
//    {
//        try {
//            $category = VendorCategory::find($id);
//
//            if (!$category) {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'Category not found'
//                ], 404);
//            }
//
//            return response()->json([
//                'success' => true,
//                'data' => [
//                    'id' => $category->id,
//                    'title' => $category->title ?? '',
//                    'photo' => $category->photo ?? '',
//                    'show_in_homepage' => (bool) $category->show_in_homepage,
//                    'publish' => (bool) $category->publish,
//                    'description' => $category->description ?? '',
//                    'vType' => $category->vType ?? null,
//                ]
//            ]);
//
//        } catch (\Exception $e) {
//            Log::error('Get Category Error: ' . $e->getMessage(), ['id' => $id]);
//
//            return response()->json([
//                'success' => false,
//                'message' => 'Failed to fetch category',
//                'error' => config('app.debug') ? $e->getMessage() : null
//            ], 500);
//        }
//    }
}


