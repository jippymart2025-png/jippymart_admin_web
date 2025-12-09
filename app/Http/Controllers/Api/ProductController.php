<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\VendorProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;


class ProductController extends Controller
{
    /**
     * Fetch all published and available products for a vendor
     */
    public function getProductsByVendorId($vendorId)
    {
        try {
            $products = $this->fetchPublishedProducts(
                function ($query) use ($vendorId) {
                    $query->where('vendorID', $vendorId);
                }
            );


            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => $products->isEmpty()
                    ? 'No available products found for this vendor'
                    : 'Products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching vendor products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch all published and available products across vendors.
     */
    public function getAllPublishedProducts(Request $request)
    {
        try {
            $products = $this->fetchPublishedProducts(null, $request);

            if ($products instanceof LengthAwarePaginator) {
                $items = $products->items();

                return response()->json([
                    'success' => true,
                    'data' => $items,
                    'meta' => [
                        'total' => $products->total(),
                        'per_page' => $products->perPage(),
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                    ],
                    'links' => [
                        'first' => $products->url(1),
                        'last' => $products->url($products->lastPage()),
                        'prev' => $products->previousPageUrl(),
                        'next' => $products->nextPageUrl(),
                    ],
                    'message' => empty($items)
                        ? 'No available products found'
                        : 'Products retrieved successfully',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => $products->isEmpty()
                    ? 'No available products found'
                    : 'Products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comprehensive product feed for restaurant detail screen
     */
    /**
     * Comprehensive product feed for restaurant detail screen
     */
    // public function getRestaurantProductFeed(Request $request, string $vendorId, ?string $extra = null)
    // {
    //     try {
    //         if ($extra && $request->query->count() === 0) {
    //             $extraQuery = ltrim($extra, '?&');
    //             if ($extraQuery !== '') {
    //                 parse_str($extraQuery, $extraParams);
    //                 foreach ($extraParams as $key => $value) {
    //                     $request->query->set($key, $value);
    //                 }
    //             }
    //         }

    //         $filters = $this->parseFilters($request);

    //         $vendorId = trim((string) $vendorId);

    //         $query = VendorProduct::query()
    //             ->where('vendorID', $vendorId)
    //             ->where(function ($q) {
    //                 $q->whereNull('publish')
    //                     ->orWhereIn('publish', [1, '1', true, 'true', 'TRUE', 'yes', 'YES']);
    //             });

    //         if ($filters['search'] !== null) {
    //             $searchTerm = $filters['search'];
    //             $query->where(function ($q) use ($searchTerm) {
    //                 $q->where('name', 'LIKE', "%{$searchTerm}%")
    //                     ->orWhere('description', 'LIKE', "%{$searchTerm}%");
    //             });
    //         }

    //         $isVeg = $filters['is_veg'];
    //         $isNonVeg = $filters['is_nonveg'];

    //         if ($isVeg === true && $isNonVeg === true) {
    //             // Include both veg and non-veg -> no filter
    //         } elseif ($isVeg === true) {
    //             $query->where(function ($q) {
    //                 $q->where('veg', 1)
    //                     ->orWhere('veg', true)
    //                     ->orWhereNull('veg');
    //             })->where(function ($q) {
    //                 $q->whereNull('nonveg')
    //                     ->orWhere('nonveg', 0)
    //                     ->orWhere('nonveg', false);
    //             });
    //         } elseif ($isNonVeg === true) {
    //             $query->where(function ($q) {
    //                 $q->where('nonveg', 1)
    //                     ->orWhere('nonveg', true);
    //             });
    //         }

    //         if ($isVeg === false) {
    //             $query->where(function ($q) {
    //                 $q->whereNull('veg')
    //                     ->orWhere('veg', 0)
    //                     ->orWhere('veg', false);
    //             });
    //         }

    //         if ($isNonVeg === false) {
    //             $query->where(function ($q) {
    //                 $q->whereNull('nonveg')
    //                     ->orWhere('nonveg', 0)
    //                     ->orWhere('nonveg', false);
    //             });
    //         }

    //         $products = $query->orderBy('name')->get();

    //         $vendor = Vendor::find($vendorId);
    //         if (!$vendor) {
    //             Log::warning('Vendor not found for product feed', ['vendor_id' => $vendorId]);
    //         }

    //         $now = Carbon::now();
    //         $promotions = Promotion::query()
    //             ->when($vendor, function ($q) use ($vendor) {
    //                 $q->whereIn('restaurant_id', [$vendor->id, $vendor->title]);
    //             }, function ($q) use ($vendorId) {
    //                 $q->where('restaurant_id', $vendorId);
    //             })
    //             ->where('isAvailable', true)
    //             ->where(function ($q) use ($now) {
    //                 $q->whereNull('start_time')
    //                     ->orWhere('start_time', '<=', $now);
    //             })
    //             ->where(function ($q) use ($now) {
    //                 $q->whereNull('end_time')
    //                     ->orWhere('end_time', '>=', $now);
    //             })
    //             ->get()
    //             ->groupBy('product_id');

    //         $categoryIds = $products->pluck('categoryID')
    //             ->filter()
    //             ->unique()
    //             ->values();

    //         $restaurantKeys = array_values(array_filter([$vendor?->title, $vendorId, $vendor?->id]));

    //         $categoriesQuery = VendorCategory::query()
    //             ->whereIn('id', $categoryIds);

    //         if (!empty($restaurantKeys)) {
    //             $categoriesQuery->whereIn('restaurant_id', $restaurantKeys);
    //         }

    //         $categories = $categoriesQuery->get()->keyBy('id');

    //         if ($categories->isEmpty() && $categoryIds->isNotEmpty()) {
    //             $categories = VendorCategory::query()
    //                 ->whereIn('id', $categoryIds)
    //                 ->get()
    //                 ->keyBy('id');
    //         }

    //         $transformedProducts = $products
    //             ->map(function (VendorProduct $product) use ($promotions, $categories, $vendorId) {

    //                 $category = $categories->get($product->categoryID);

    //                 $data = $this->transformProduct($product, $promotions, $category);

    //                 // ⭐ Add vendorID here
    //                 $data['vendorID'] = $vendorId;

    //                 return $data;
    //             });

    //         if ($filters['offer_only'] === true) {
    //             $transformedProducts = $transformedProducts
    //                 ->filter(function (array $product) {
    //                     if ($product['has_active_promotion']) {
    //                         return true;
    //                     }

    //                     $discountPrice = $product['discount_price'];
    //                     $originalPrice = $product['original_price'];

    //                     return $discountPrice !== null
    //                         && $originalPrice !== null
    //                         && (float) $discountPrice > 0
    //                         && (float) $discountPrice < (float) $originalPrice;
    //                 })
    //                 ->values();
    //         }

    //         $categorySummaries = $this->buildCategorySummaries($transformedProducts, $categories);

    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'filters' => $filters,
    //                 'meta' => [
    //                     'total_products' => $transformedProducts->count(),
    //                     'offer_products' => $transformedProducts
    //                         ->where('has_active_promotion', true)
    //                         ->count(),
    //                     'categories' => $categorySummaries->count(),
    //                 ],
    //                 'categories' => $categorySummaries->values(),
    //                 'products' => $transformedProducts->values(),
    //             ],
    //         ]);
    //     } catch (\Throwable $e) {
    //         Log::error('Error building restaurant product feed: ' . $e->getMessage(), [
    //             'vendor_id' => $vendorId,
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unable to load restaurant products at the moment.',
    //         ], 500);
    //     }
    // }
    
        public function getRestaurantProductFeed(Request $request, string $vendorId, ?string $extra = null)
    {
        try {
            /** Extra Query Handling */
            if ($extra && $request->query->count() === 0) {
                $extraQuery = ltrim($extra, '?&');
                if ($extraQuery !== '') {
                    parse_str($extraQuery, $extraParams);
                    foreach ($extraParams as $key => $value) {
                        $request->query->set($key, $value);
                    }
                }
            }

            $filters = $this->parseFilters($request);
            $vendorId = trim($vendorId);

            /**
             * ---------------------------------------
             * PRODUCT QUERY (Optimized)
             * + New condition: isAvailable = 1
             * ---------------------------------------
             */
            $query = VendorProduct::query()
                ->where('vendorID', $vendorId)
                ->where('isAvailable', 1) // ⭐ ADDED CONDITION
                ->where(function ($q) {
                    $q->whereNull('publish')
                        ->orWhere('publish', 1)
                        ->orWhere('publish', true)
                        ->orWhere('publish', '1')
                        ->orWhere('publish', 'true')
                        ->orWhere('publish', 'TRUE')
                        ->orWhere('publish', 'yes')
                        ->orWhere('publish', 'YES');
                });

            /** Search */
            if ($filters['search'] !== null) {
                $search = '%' . $filters['search'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search)
                        ->orWhere('description', 'LIKE', $search);
                });
            }

            /** Veg / Non-veg filter */
            $isVeg = $filters['is_veg'];
            $isNonVeg = $filters['is_nonveg'];

            // both true → no filter

            if ($isVeg === true && $isNonVeg !== true) {
                $query->where('veg', 1);
            } elseif ($isNonVeg === true && $isVeg !== true) {
                $query->where('nonveg', 1);
            }

            if ($isVeg === false) {
                $query->where('veg', 0);
            }
            if ($isNonVeg === false) {
                $query->where('nonveg', 0);
            }

            /** Fetch products */
            $products = $query->orderBy('name')->get();

            /** Vendor */
            $vendor = Vendor::find($vendorId);

            /** Promotions */
            $now = Carbon::now();
            $promotions = Promotion::query()
                ->when($vendor, function ($q) use ($vendor) {
                    $q->whereIn('restaurant_id', [$vendor->id, $vendor->title]);
                }, function ($q) use ($vendorId) {
                    $q->where('restaurant_id', $vendorId);
                })
                ->where('isAvailable', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('start_time')->orWhere('start_time', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('end_time')->orWhere('end_time', '>=', $now);
                })
                ->get()
                ->groupBy('product_id');

            /** Categories */
            $categoryIds = $products->pluck('categoryID')->filter()->unique()->values();

            $restaurantKeys = array_values(array_filter([
                $vendor?->title,
                $vendorId,
                $vendor?->id
            ]));

            $categories = VendorCategory::query()
                ->whereIn('id', $categoryIds)
                ->whereIn('restaurant_id', $restaurantKeys)
                ->get()
                ->keyBy('id');

            if ($categories->isEmpty() && $categoryIds->isNotEmpty()) {
                $categories = VendorCategory::query()
                    ->whereIn('id', $categoryIds)
                    ->get()
                    ->keyBy('id');
            }

            /** Transform */
            $transformedProducts = $products->map(function (VendorProduct $product) use (
                $promotions,
                $categories,
                $vendorId
            ) {
                $category = $categories->get($product->categoryID);

                $data = $this->transformProduct($product, $promotions, $category);
                $data['vendorID'] = $vendorId; // keep existing response

                return $data;
            });

            /** Offer-only filter */
            if ($filters['offer_only'] === true) {
                $transformedProducts = $transformedProducts
                    ->filter(function ($p) {
                        if ($p['has_active_promotion']) return true;

                        return $p['discount_price'] &&
                            $p['original_price'] &&
                            floatval($p['discount_price']) > 0 &&
                            floatval($p['discount_price']) < floatval($p['original_price']);
                    })
                    ->values();
            }

            /** Category summaries */
            $categorySummaries = $this->buildCategorySummaries($transformedProducts, $categories);

            /** Response (no change) */
            return response()->json([
                'success' => true,
                'data' => [
                    'filters' => $filters,
                    'meta' => [
                        'total_products' => $transformedProducts->count(),
                        'offer_products' => $transformedProducts->where('has_active_promotion', true)->count(),
                        'categories' => $categorySummaries->count(),
                    ],
                    'categories' => $categorySummaries->values(),
                    'products' => $transformedProducts->values(),
                ],
            ]);

        } catch (\Throwable $e) {

            Log::error('Error building restaurant product feed: '.$e->getMessage(), [
                'vendor_id' => $vendorId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load restaurant products at the moment.',
            ], 500);
        }
    }


    /**
     * Fetch single product details by product ID.
     *
     * @param string $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductById(string $productId)
    {
        try {
            $product = VendorProduct::find($productId);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $vendor = Vendor::find($product->vendorID);

            $promotions = Promotion::query()
                ->where(function ($query) use ($productId, $product) {
                    $query->where('product_id', $productId)
                        ->orWhere('product_id', $product->id);
                })
                ->where('isAvailable', true)
                ->get()
                ->groupBy('product_id');

            $category = null;
            if (!empty($product->categoryID)) {
                $category = VendorCategory::find($product->categoryID);

                if (!$category && $vendor) {
                    $category = VendorCategory::query()
                        ->where('id', $product->categoryID)
                        ->whereIn('restaurant_id', array_filter([$vendor->id, $vendor->title]))
                        ->first();
                }
            }

            $data = $this->transformProduct($product, $promotions, $category);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching product by ID', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load product at the moment.',
            ], 500);
        }
    }

    protected function parseFilters(Request $request): array
    {
        return [
            'search' => $this->stringOrNull($request->query('search')),
            'is_veg' => $this->nullableBool($request->query('is_veg')),
            'is_nonveg' => $this->nullableBool($request->query('is_nonveg')),
            'offer_only' => $this->nullableBool($request->query('offer_only')),
        ];
    }

    protected function transformProduct(VendorProduct $product, Collection $promotions, ?VendorCategory $category = null): array
    {
        $safeDecode = fn ($value) => $this->safeDecode($value);
        $promotion = optional($promotions->get($product->id))->first();

        $originalPrice = $this->numericString($product->price);
        $discountPrice = $this->numericString($product->disPrice);
        $hasPromotion = $promotion !== null;

        $finalPrice = $hasPromotion
            ? $this->numericString($promotion->special_price)
            : ($this->isValidDiscount($originalPrice, $discountPrice)
                ? $discountPrice
                : $originalPrice);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'category_id' => $product->categoryID,
            'category_title' => $category->title ?? $product->categoryTitle,
            'is_available' => $this->coerceBoolean($product->isAvailable),
            'nonveg' => $this->coerceBoolean($product->nonveg),
            'veg' => $this->coerceBoolean($product->veg),
            'photo' => $product->photo,
            'photos' => $safeDecode($product->photos),
            'add_ons_title' => $safeDecode($product->addOnsTitle),
            'add_ons_price' => $safeDecode($product->addOnsPrice),
            'item_attribute' => $safeDecode($product->item_attribute),
            'product_specification' => $safeDecode($product->product_specification),
            'reviews_count' => (int) ($product->reviewsCount ?? 0),
            'reviews_sum' => (float) ($product->reviewsSum ?? 0),
            'quantity' => $product->quantity,
            'original_price' => $originalPrice,
            'discount_price' => $discountPrice,
            'final_price' => $finalPrice,
            'has_active_promotion' => $hasPromotion,
            'promotion' => $hasPromotion ? [
                'id' => $promotion->id,
                'special_price' => $this->numericString($promotion->special_price),
                'item_limit' => $promotion->item_limit,
                'start_time' => $this->formatDateTime($promotion->start_time),
                'end_time' => $this->formatDateTime($promotion->end_time),
            ] : null,
        ];
    }
    public function getAllProducts(Request $request)
    {
        try {
            $products = $this->fetchPublishedProducts(null, $request);

            if ($products instanceof LengthAwarePaginator) {
                $items = $products->items();

                return response()->json([
                    'success' => true,
                    'data' => $items,
                    'meta' => [
                        'total' => $products->total(),
                        'per_page' => $products->perPage(),
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                    ],
                    'links' => [
                        'first' => $products->url(1),
                        'last' => $products->url($products->lastPage()),
                        'prev' => $products->previousPageUrl(),
                        'next' => $products->nextPageUrl(),
                    ],
                    'message' => empty($items)
                        ? 'No available products found'
                        : 'Products retrieved successfully',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => $products->isEmpty()
                    ? 'No available products found'
                    : 'Products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function fetchPublishedProducts(?callable $scopedQuery = null, ?Request $request = null)
    {
        $query = VendorProduct::query()
            ->select([
                'id',
                'name',
                'description',
                'categoryID',
                'categoryTitle',
                'vendorID',
                'vendorTitle',
                'price',
                'disPrice',
                'quantity',
                'publish',
                'isAvailable',
                'veg',
                'nonveg',
                'takeawayOption',
                'photo',
                'photos',
                'createdAt',
            ])
            ->where('publish', 1)
            ->where('isAvailable', 1)
            ->orderBy('name');

        if ($scopedQuery) {
            $scopedQuery($query);
        }

        $transform = function (VendorProduct $item) {
            return $this->mapBasicProduct($item);
        };

        if ($request) {
            $perPage = (int) $request->query('per_page', 50);
            if ($perPage <= 0) {
                $perPage = 50;
            }
            $perPage = min($perPage, 200);

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->transform($transform);

            return $paginator;
        }

        return $query->get()->map($transform);
    }

    protected function mapBasicProduct(VendorProduct $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'vendor_id' => $item->vendorID,
            'vendor_title' => $item->vendorTitle,
            'category_id' => $item->categoryID,
            'category_title' => $item->categoryTitle,
            'is_available' => $this->coerceBoolean($item->isAvailable) ?? false,
            'publish' => $this->coerceBoolean($item->publish) ?? false,
            'veg' => $this->coerceBoolean($item->veg),
            'nonveg' => $this->coerceBoolean($item->nonveg),
            'quantity' => $item->quantity,
            'price' => $item->price,
            'discount_price' => $item->disPrice,
            'takeaway_option' => $this->coerceBoolean($item->takeawayOption),
            'photo' => $item->photo,
            'photos' => $this->safeDecode($item->photos),
            'created_at' => $this->safeDecode($item->createdAt),
        ];
    }

    protected function buildCategorySummaries(Collection $products, Collection $categories): Collection
    {
        $categoryCounts = $products
            ->groupBy('category_id')
            ->map(fn ($items) => $items->count());

        $categoryIds = $categoryCounts->keys()
            ->filter()
            ->values();

        if ($categoryIds->isEmpty() || $categories->isEmpty()) {
            return collect();
        }

        return $categoryCounts->map(function ($count, $categoryId) use ($categories, $products) {
            $category = $categories->get($categoryId);
            $productForCategory = $products->firstWhere('category_id', $categoryId);

            return [
                'id' => $categoryId,
                'title' => $category->title ?? ($productForCategory['category_title'] ?? null),
                'description' => $category->description ?? null,
                'photo' => $category->photo ?? null,
                'product_count' => $count,
            ];
        })->values();
    }

    protected function nullableBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $bool;
    }

    protected function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function safeDecode($value)
    {
        if (empty($value) || !is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    protected function numericString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $cleaned === '' ? null : $cleaned;
    }

    protected function isValidDiscount(?string $original, ?string $discount): bool
    {
        if ($original === null || $discount === null) {
            return false;
        }

        $originalValue = (float) $original;
        $discountValue = (float) $discount;

        return $discountValue > 0 && $discountValue < $originalValue;
    }

    protected function formatDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function coerceBoolean($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) (int) $value;
        }

        $lower = strtolower((string) $value);

        if (in_array($lower, ['true', '1', 'yes'], true)) {
            return true;
        }

        if (in_array($lower, ['false', '0', 'no'], true)) {
            return false;
        }

        return null;
    }
}
