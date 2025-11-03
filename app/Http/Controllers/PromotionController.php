<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Promotion;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\MartItem;
use Carbon\Carbon;

class PromotionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index($id = '')
    {
        return view("promotions.index")->with('id', $id);
    }

    public function edit($id)
    {
        return view('promotions.edit')->with('id', $id);
    }

    public function create($id = '')
    {
        return view('promotions.create')->with('id', $id);
    }

    /**
     * Get all promotions data for DataTables
     */
    public function getData(Request $request)
    {
        try {
            $vtypeFilter = $request->input('vtype_filter', '');
            $zoneFilter = $request->input('zone_filter', '');

            // Check if vType and zoneId columns exist
            $columns = DB::getSchemaBuilder()->getColumnListing('promotions');
            $hasVType = in_array('vType', $columns);
            $hasZoneId = in_array('zoneId', $columns);

            $query = DB::table('promotions as p')
                ->leftJoin('vendors as v', 'v.id', '=', 'p.restaurant_id');

            // Only join zone table if zoneId column exists
            if ($hasZoneId) {
                $query->leftJoin('zone as z', 'z.id', '=', 'p.zoneId');
            }

            // Select columns
            $selectColumns = ['p.*', 'v.title as vendor_name'];
            if ($hasZoneId) {
                $selectColumns[] = 'z.name as zone_name';
            }
            $query->select($selectColumns);

            // Apply filters only if columns exist
            if (!empty($vtypeFilter) && $hasVType) {
                $query->where('p.vType', '=', $vtypeFilter);
            }

            if (!empty($zoneFilter) && $hasZoneId) {
                $query->where('p.zoneId', '=', $zoneFilter);
            }

            $promotions = $query->orderBy('p.start_time', 'desc')->get();

            // Process promotions to check expiry and format data
            $data = [];
            foreach ($promotions as $promo) {
                $endTime = $this->parseDateTime($promo->end_time);
                $isExpired = $endTime && $endTime < now();

                $rowData = [
                    'id' => $promo->id,
                    'vType' => $hasVType ? ($promo->vType ?? '-') : '-',
                    'zoneId' => $hasZoneId ? ($promo->zoneId ?? '') : '',
                    'zone_name' => $hasZoneId ? ($promo->zone_name ?? '-') : '-',
                    'restaurant_id' => $promo->restaurant_id,
                    'restaurant_title' => $promo->restaurant_title ?? ($promo->vendor_name ?? '-'),
                    'product_id' => $promo->product_id,
                    'product_title' => $promo->product_title ?? '-',
                    'special_price' => $promo->special_price ?? 0,
                    'item_limit' => $promo->item_limit ?? 2,
                    'extra_km_charge' => $promo->extra_km_charge ?? 0,
                    'free_delivery_km' => $promo->free_delivery_km ?? 0,
                    'start_time' => $this->formatDateTime($promo->start_time),
                    'end_time' => $this->formatDateTime($promo->end_time),
                    'payment_mode' => $promo->payment_mode ?? 'prepaid',
                    'isAvailable' => $promo->isAvailable ? true : false,
                    'isExpired' => $isExpired
                ];

                $data[] = $rowData;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data)
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching promotions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get zones for dropdown
     */
    public function getZones()
    {
        try {
            $zones = DB::table('zone')
                ->where('publish', 1)
                ->orderBy('name', 'asc')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => $zones
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vendors (restaurants/marts) for dropdown
     */
    public function getVendors(Request $request)
    {
        try {
            $vType = $request->input('vType', '');
            $zoneId = $request->input('zoneId', '');

            $query = DB::table('vendors')
                ->select('id', 'title', 'vType', 'zoneId')
                ->orderBy('title', 'asc');

            if (!empty($vType)) {
                $query->where('vType', '=', $vType);
            }

            if (!empty($zoneId)) {
                $query->where('zoneId', '=', $zoneId);
            }

            $vendors = $query->get();

            return response()->json([
                'success' => true,
                'data' => $vendors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get products for a vendor
     */
    public function getProducts(Request $request)
    {
        try {
            $vendorId = $request->input('vendor_id');
            $vType = $request->input('vType', '');

            if (empty($vendorId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Vendor ID is required'
                ], 400);
            }

            $products = [];

            if (strtolower($vType) === 'mart') {
                // Get mart items
                $products = DB::table('mart_items')
                    ->where('vendorID', '=', $vendorId)
                    ->select('id', 'item_name as name', 'item_price as price', 'dis_price as disPrice')
                    ->orderBy('item_name', 'asc')
                    ->get();
            } else {
                // Get restaurant products
                $products = DB::table('vendor_products')
                    ->where('vendorID', '=', $vendorId)
                    ->select('id', 'name', 'price', 'disPrice')
                    ->orderBy('name', 'asc')
                    ->get();
            }

            // Format products with display price
            $formattedProducts = $products->map(function ($product) {
                $displayPrice = $product->disPrice && $product->disPrice > 0 
                    ? $product->disPrice 
                    : $product->price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $displayPrice
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new promotion
     */
    public function store(Request $request)
    {
        try {
            // Check if vType and zoneId columns exist
            $columns = DB::getSchemaBuilder()->getColumnListing('promotions');
            $hasVType = in_array('vType', $columns);
            $hasZoneId = in_array('zoneId', $columns);

            // Build validation rules dynamically
            $rules = [
                'restaurant_id' => 'required|string',
                'restaurant_title' => 'required|string',
                'product_id' => 'required|string',
                'product_title' => 'required|string',
                'special_price' => 'required|numeric',
                'item_limit' => 'required|integer',
                'extra_km_charge' => 'required|numeric',
                'free_delivery_km' => 'required|numeric',
                'start_time' => 'required|string',
                'end_time' => 'required|string',
                'payment_mode' => 'required|string',
                'isAvailable' => 'required|in:0,1,true,false'
            ];

            if ($hasVType) {
                $rules['vType'] = 'required|string';
            }
            if ($hasZoneId) {
                $rules['zoneId'] = 'nullable|string';
            }

            $data = $request->validate($rules);
            
            // Convert isAvailable to boolean before processing
            $data['isAvailable'] = filter_var($data['isAvailable'], FILTER_VALIDATE_BOOLEAN);

            // Don't set ID - let MySQL auto-increment handle it
            // Remove any 'id' field that might have been sent
            unset($data['id']);

            // Remove vType and zoneId if columns don't exist
            if (!$hasVType && isset($data['vType'])) {
                unset($data['vType']);
            }
            if (!$hasZoneId && isset($data['zoneId'])) {
                unset($data['zoneId']);
            }

            // Convert boolean to integer for MySQL tinyint column
            if (isset($data['isAvailable'])) {
                $data['isAvailable'] = $data['isAvailable'] ? 1 : 0;
            }

            // Log the data being inserted for debugging
            \Log::info('Attempting to insert promotion', [
                'data' => $data
            ]);

            // Insert promotion and get the auto-generated ID
            $insertedId = DB::table('promotions')->insertGetId($data);
            
            \Log::info('Promotion inserted successfully', [
                'id' => $insertedId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Promotion created successfully',
                'id' => $insertedId
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating promotion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing promotion
     */
    public function update(Request $request, $id)
    {
        try {
            // Check if vType and zoneId columns exist
            $columns = DB::getSchemaBuilder()->getColumnListing('promotions');
            $hasVType = in_array('vType', $columns);
            $hasZoneId = in_array('zoneId', $columns);

            // Build validation rules dynamically
            $rules = [
                'restaurant_id' => 'required|string',
                'restaurant_title' => 'required|string',
                'product_id' => 'required|string',
                'product_title' => 'required|string',
                'special_price' => 'required|numeric',
                'item_limit' => 'required|integer',
                'extra_km_charge' => 'required|numeric',
                'free_delivery_km' => 'required|numeric',
                'start_time' => 'required|string',
                'end_time' => 'required|string',
                'payment_mode' => 'required|string',
                'isAvailable' => 'required|in:0,1,true,false'
            ];

            if ($hasVType) {
                $rules['vType'] = 'required|string';
            }
            if ($hasZoneId) {
                $rules['zoneId'] = 'nullable|string';
            }

            $data = $request->validate($rules);
            
            // Convert isAvailable to boolean before processing
            $data['isAvailable'] = filter_var($data['isAvailable'], FILTER_VALIDATE_BOOLEAN);

            // Check if promotion exists
            $exists = DB::table('promotions')->where('id', $id)->exists();
            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Promotion not found'
                ], 404);
            }

            // Remove vType and zoneId if columns don't exist
            if (!$hasVType && isset($data['vType'])) {
                unset($data['vType']);
            }
            if (!$hasZoneId && isset($data['zoneId'])) {
                unset($data['zoneId']);
            }

            // Convert boolean to integer for MySQL tinyint column
            if (isset($data['isAvailable'])) {
                $data['isAvailable'] = $data['isAvailable'] ? 1 : 0;
            }

            // Update promotion
            DB::table('promotions')->where('id', $id)->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Promotion updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating promotion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a promotion
     */
    public function destroy($id)
    {
        try {
            $deleted = DB::table('promotions')->where('id', $id)->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'error' => 'Promotion not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Promotion deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error deleting promotion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete promotions
     */
    public function bulkDelete(Request $request)
    {
        try {
            $ids = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No promotion IDs provided'
                ], 400);
            }

            DB::table('promotions')->whereIn('id', $ids)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Promotions deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error bulk deleting promotions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle promotion availability
     */
    public function toggleAvailability(Request $request, $id)
    {
        try {
            $isAvailable = $request->input('isAvailable');
            
            // Convert ID to string to match varchar column
            $id = (string) $id;
            
            // Get current value first
            $currentPromotion = DB::table('promotions')->where('id', $id)->first();
            
            if (!$currentPromotion) {
                \Log::error('Promotion not found', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'error' => 'Promotion not found with ID: ' . $id
                ], 404);
            }
            
            // Log incoming data
            \Log::info('Toggle availability request', [
                'id' => $id,
                'id_type' => gettype($id),
                'current_isAvailable' => $currentPromotion->isAvailable,
                'requested_isAvailable' => $isAvailable,
                'isAvailable_type' => gettype($isAvailable)
            ]);
            
            // Convert to integer (0 or 1) for MySQL tinyint column
            $isAvailableInt = (int) ($isAvailable ? 1 : 0);
            
            \Log::info('Converted value', [
                'isAvailableInt' => $isAvailableInt,
                'current_value' => $currentPromotion->isAvailable
            ]);

            // Perform update
            $affected = DB::table('promotions')
                ->where('id', $id)
                ->update(['isAvailable' => $isAvailableInt]);
            
            \Log::info('Update result', [
                'affected_rows' => $affected,
                'id' => $id,
                'sql' => DB::getQueryLog()
            ]);
            
            // Verify the update
            $updatedPromotion = DB::table('promotions')->where('id', $id)->first();
            \Log::info('After update verification', [
                'new_isAvailable' => $updatedPromotion->isAvailable
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Promotion availability updated',
                'affected_rows' => $affected,
                'current_value' => $currentPromotion->isAvailable,
                'new_value' => $updatedPromotion->isAvailable,
                'debug' => [
                    'id' => $id,
                    'requested' => $isAvailableInt
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error toggling promotion availability: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single promotion data
     */
    public function show($id)
    {
        try {
            $promotion = DB::table('promotions')->where('id', $id)->first();

            if (!$promotion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Promotion not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $promotion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function to parse datetime
     */
    private function parseDateTime($dateTime)
    {
        if (!$dateTime) return null;

        try {
            // Remove quotes if present
            $dateTime = trim($dateTime, '"');
            return Carbon::parse($dateTime);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper function to format datetime for display
     */
    private function formatDateTime($dateTime)
    {
        if (!$dateTime) return '';

        try {
            // Remove quotes if present
            $dateTime = trim($dateTime, '"');
            $date = Carbon::parse($dateTime);
            return $date->format('Y-m-d\TH:i');
        } catch (\Exception $e) {
            return $dateTime;
        }
    }
}


