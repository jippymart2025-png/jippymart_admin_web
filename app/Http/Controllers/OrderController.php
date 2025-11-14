<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Google\Client as Google_Client;

class OrderController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

//    public function index(Request $request, $id = '')
//    {
//        $status = $request->query('status');
//
//        $query = \App\Models\restaurant_orders::query();
//
//        if ($status) {
//            $query->where('status', $status);
//        }
//
//        $orders = $query->orderBy('id', 'desc')->paginate(10);
//
//        // Load zones from MySQL
//        $zones = DB::table('zone')
//            ->where('publish', 1)
//            ->orderBy('name', 'asc')
//            ->get(['id', 'name']);
//
//        // ✅ Pass both $status and $id to the view
//        return view('orders.index', compact('orders', 'status', 'id', 'zones'));
//    }

    public function edit($id)
    {
        // Fetch order from MySQL with joins
        $order = DB::table('restaurant_orders as ro')
            ->leftJoin('vendors as v', 'v.id', '=', 'ro.vendorID')
            ->leftJoin('users as u', 'u.id', '=', 'ro.authorID')
            ->leftJoin('users as d', 'd.id', '=', 'ro.driverID')
            ->leftJoin('zone as z', 'z.id', '=', 'v.zoneId')
            ->where('ro.id', $id)
            ->select(
                'ro.*',
                'v.id as vendor_db_id',
                'v.title as vendor_title',
                'v.vType as vendor_type',
                'v.photo as vendor_photo',
                'v.phonenumber as vendor_phone',
                'v.location as vendor_location',
                'v.zoneId as vendor_zone_id',
                'u.id as user_db_id',
                'u.firstName as user_first_name',
                'u.lastName as user_last_name',
                'u.email as user_email',
                'u.phoneNumber as user_phone',
                'd.id as driver_db_id',
                'd.firstName as driver_first_name',
                'd.lastName as driver_last_name',
                'd.email as driver_email',
                'd.phoneNumber as driver_phone',
                'z.name as zone_name'
            )
            ->first();

        if (!$order) {
            abort(404, 'Order not found');
        }

        // Parse JSON fields
        $order->products = !empty($order->products) ? json_decode($order->products, true) : [];
        $order->author = !empty($order->author) ? json_decode($order->author, true) : [];
        $order->driver = !empty($order->driver) ? json_decode($order->driver, true) : [];
        $order->address = !empty($order->address) ? json_decode($order->address, true) : [];
        $order->vendor = !empty($order->vendor) ? json_decode($order->vendor, true) : [];
        $order->specialDiscount = !empty($order->specialDiscount) ? json_decode($order->specialDiscount, true) : null;

        // Load currency settings
        $currency = DB::table('currencies')
            ->where('isActive', true)
            ->first();

        // Load zones for driver assignment
        $zones = DB::table('zone')
            ->where('publish', 1)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        // Load available drivers if needed
        $availableDrivers = [];
        if (!empty($order->driverID)) {
            // Already has driver
        } else {
            // Load available drivers for assignment
            $availableDrivers = DB::table('users')
                ->where('role', 'driver')
                ->where('isActive', true)
                ->select('id', 'firstName', 'lastName', 'phoneNumber', 'email')
                ->get();
        }

        return view('orders.edit', compact('order', 'currency', 'zones', 'availableDrivers', 'id'));
    }

    public function sendNotification(Request $request)
    {

        $orderStatus=$request->orderStatus;

        // Email notifications removed to prevent resource issues on shared hosting

        if(Storage::disk('local')->has('firebase/credentials.json') && ($orderStatus=="restaurantorders Accepted" || $orderStatus=="restaurantorders Rejected"|| $orderStatus=="restaurantorders Completed" || $orderStatus=="Driver Accepted")){

            $client= new Google_Client();
            $client->setAuthConfig(storage_path('app/firebase/credentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $client_token = $client->getAccessToken();
            $access_token = $client_token['access_token'];

            $fcm_token = $request->fcm;

            if(!empty($access_token) && !empty($fcm_token)){

                $projectId = env('FIREBASE_PROJECT_ID');
                $url = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

                $data = [
                    'message' => [
                        'notification' => [
                            'title' => $request->subject,
                            'body' => $request->message,
                        ],
                        'token' => $fcm_token,
                    ],
                ];

                $headers = array(
                    'Content-Type: application/json',
                    'Authorization: Bearer '.$access_token
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                $result = curl_exec($ch);
                if ($result === FALSE) {
                    die('FCM Send Error: ' . curl_error($ch));
                }
                curl_close($ch);
                $result=json_decode($result);

                $response = array();
                $response['success'] = true;
                $response['message'] = 'Notification successfully sent.';
                $response['result'] = $result;

            }else{
                $response = array();
                $response['success'] = false;
                $response['message'] = 'Missing sender id or token to send notification.';
            }

        }else{
            $response = array();
            $response['success'] = false;
            $response['message'] = 'Firebase credentials file not found.';
        }

        return response()->json($response);
    }

    /**
     * Public method to send email notifications (for direct API calls) - DISABLED
     */
    public function sendOrderEmailNotificationPublic(Request $request)
    {
        // Email notifications disabled to prevent resource issues on shared hosting
        return response()->json([
            'success' => false,
            'message' => 'Email notifications are disabled'
        ]);
    }

    // Email notification methods removed to prevent resource issues on shared hosting

    // Helper methods removed along with email functionality

    public function orderprint($id){
        // Fetch order from MySQL with joins (same as edit)
        $order = DB::table('restaurant_orders as ro')
            ->leftJoin('vendors as v', 'v.id', '=', 'ro.vendorID')
            ->leftJoin('users as u', 'u.id', '=', 'ro.authorID')
            ->leftJoin('users as d', 'd.id', '=', 'ro.driverID')
            ->where('ro.id', $id)
            ->select(
                'ro.*',
                'v.id as vendor_db_id',
                'v.title as vendor_title',
                'v.vType as vendor_type',
                'v.photo as vendor_photo',
                'v.phonenumber as vendor_phone',
                'v.location as vendor_location',
                'u.id as user_db_id',
                'u.firstName as user_first_name',
                'u.lastName as user_last_name',
                'u.email as user_email',
                'u.phoneNumber as user_phone',
                'd.id as driver_db_id',
                'd.firstName as driver_first_name',
                'd.lastName as driver_last_name',
                'd.email as driver_email',
                'd.phoneNumber as driver_phone'
            )
            ->first();

        if (!$order) {
            abort(404, 'Order not found');
        }

        // Parse JSON fields
        $order->products = !empty($order->products) ? json_decode($order->products, true) : [];
        $order->author = !empty($order->author) ? json_decode($order->author, true) : [];
        $order->driver = !empty($order->driver) ? json_decode($order->driver, true) : [];
        $order->address = !empty($order->address) ? json_decode($order->address, true) : [];
        $order->vendor = !empty($order->vendor) ? json_decode($order->vendor, true) : [];
        $order->specialDiscount = !empty($order->specialDiscount) ? json_decode($order->specialDiscount, true) : null;

        // Load currency settings
        $currency = DB::table('currencies')
            ->where('isActive', true)
            ->first();

        return view('orders.print', compact('order', 'currency', 'id'));
    }

    /**
     * Orders index page - handles both page load and DataTables requests
     */
    public function index(Request $request, $id='')
    {
        // Check if this is a DataTables AJAX request
        if ($request->has('draw')) {
            // This is a DataTables request - return JSON (delegated to Model)
            $draw = (int) ($request->input('draw', 1));
            $orderColumnIdx = (int) data_get($request->input('order'), '0.column', 0);
            $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            $orderable = [
                0 => 'ro.id',
                1 => 'v.title',
                2 => 'ro.createdAt',
                3 => 'ro.toPayAmount',
                4 => 'ro.status',
            ];
            // Default to ro.id desc for reliable ordering
            $orderBy = $orderable[$orderColumnIdx] ?? 'ro.createdAt';

            $filters = [
                'vendor_id' => !empty($id) ? (string) $id : (string) $request->input('vendor_id', ''),
                'user_id' => (string) $request->input('user_id', ''),
                'driver_id' => (string) $request->input('driver_id', ''),
                'status' => (string) $request->input('status', ''),
                'zone_id' => (string) $request->input('zone_id', ''),
                'order_type' => (string) $request->input('order_type', ''),
                'date_from' => (string) $request->input('date_from', ''),
                'date_to' => (string) $request->input('date_to', ''),
                'search' => strtolower((string) data_get($request->input('search'), 'value', '')),
                'order_by' => $orderBy,
                'order_dir' => $orderDir,
                'start' => (int) ($request->input('start', 0)),
                'length' => (int) ($request->input('length', 10)),
            ];

            $result = \App\Models\restaurant_orders::fetchForDatatable($filters);
            $rows = $result['rows'];
            $recordsFiltered = $result['recordsFiltered'];

            // Log search queries for debugging
            if (!empty($filters['search'])) {
                \Log::info('📡 Orders search query:', [
                    'search_term' => $filters['search'],
                    'results_count' => $recordsFiltered
                ]);
            }

            // Extract filter values for use in row processing
            $vendorId = $filters['vendor_id'];
            $userId = $filters['user_id'];
            $driverId = $filters['driver_id'];

            // Build records for DataTables
            $data = [];
            // Permission: delete
            $canDelete = false;
            try {
                $perm = @json_decode((string) session('user_permissions'), true);
                if (is_array($perm) && in_array('orders.delete', array_values($perm))) {
                    $canDelete = true;
                }
            } catch (\Throwable $e) {}

            $isVendorContext = $vendorId !== '';
            $isUserContext = $userId !== '';
            $isDriverContext = $driverId !== '';

            foreach ($rows as $row) {
                $id = (string) $row->id;
                $vendorTitle = (string) ($row->vendor_title ?? '');
                $vendorType = (string) ($row->vendor_type ?? 'restaurant');

                // If vendor join failed, try to extract from vendor JSON if available
                if (empty($vendorTitle)) {
                    // Check if there's vendor data in the order JSON (might be in a 'vendor' field)
                    // For now, just show empty or try to get from vendorID if needed
                }

                // Author/client
                $clientName = '';
                if (!empty($row->user_first_name) || !empty($row->user_last_name)) {
                    $clientName = trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''));
                }
                // Always try JSON as fallback (in case join didn't match)
                if (empty($clientName)) {
                    $clientName = $this->extractNameFromJson($row->author);
                }
                if (empty($clientName)) {
                    $clientName = 'N/A';
                }

                // Driver name from JSON if available
                $driverName = $this->extractNameFromJson($row->driver);

                // // Order type
                // $takeAway = strtolower((string) ($row->takeAway ?? ''));
                // $orderTypeText = ($takeAway === '1' || $takeAway === 'true') ? trans('lang.order_takeaway') : trans('lang.order_delivery');

                // Amount
                $amountValue = $this->resolveAmount($row);
                $amountText = $this->formatCurrency($amountValue);

                // Date render - Format like "Oct 1, 2025 11:27 PM"
                $dateText = '';
                if (!empty($row->createdAt)) {
                    try {
                        // Parse ISO 8601 string (e.g., "2025-10-14T14:53:43.860219Z")
                        $date = \Carbon\Carbon::parse($row->createdAt);
                        // Format: "Oct 1, 2025 11:27 PM"
                        $dateText = $date->format('M j, Y g:i A');

                        // Log first row for debugging
                        if ($id === ($rows->first()->id ?? '')) {
                            \Log::info('📅 Date formatting sample:', [
                                'raw_date' => $row->createdAt,
                                'formatted_date' => $dateText,
                                'format_used' => 'M j, Y g:i A'
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // Fallback to raw string if parsing fails
                        \Log::warning('⚠️ Date parsing failed:', ['date' => $row->createdAt, 'error' => $e->getMessage()]);
                        $dateText = (string) $row->createdAt;
                    }
                }

                // Status
                $statusText = (string) ($row->status ?? '');

                // Build action URLs (keep same routes)
                $editUrl = route('orders.edit', $id);
                if ($vendorId !== '') {
                    $editUrl .= '?eid=' . $vendorId;
                }
                $printUrl = route('vendors.orderprint', $id);
                if ($vendorId !== '') {
                    $printUrl .= '?eid=' . $vendorId;
                }

                // Build cells matching exact table structure from blade file
                $rowCells = [];

                // Column 0: Checkbox (if delete permission)
                if ($canDelete) {
                    $rowCells[] = '<input type="checkbox" id="is_open_' . e($id) . '" class="is_open" dataId="' . e($id) . '"><label class="col-3 control-label" for="is_open_' . e($id) . '"></label>';
                }

                // Column 1: Order ID
                $rowCells[] = '<a href="' . e($editUrl) . '" class="redirecttopage">' . e($id) . '</a>';

                // Column 2: Restaurant (only if $id == '', i.e., not vendor context)
                if (!$isVendorContext) {
                    // Check if vendor_id exists before generating route
                    if (!empty($row->vendor_id)) {
                        $vendorViewUrl = ($vendorType === 'mart' ? route('marts.view', $row->vendor_id) : route('restaurants.view', $row->vendor_id));
                        $rowCells[] = '<a href="' . e($vendorViewUrl) . '">' . e($vendorTitle ?: 'N/A') . '</a>';
                    } else {
                        $rowCells[] = e($vendorTitle ?: 'N/A');
                    }
                }

                // Column 3: Driver or User depending on context
                // If userId is set: show Driver column
                // If driverId is set: show User column
                // Default: show Driver then User
                if ($isUserContext) {
                    // User context: show Driver column
                    $driverLink = '';
                    if (!empty($driverName)) {
                        // Extract driver ID from JSON if possible
                        $driverIdFromJson = $this->extractIdFromJson($row->driver);
                        if ($driverIdFromJson) {
                            $driverViewUrl = route('drivers.view', $driverIdFromJson);
                            $driverLink = '<a href="' . e($driverViewUrl) . '">' . e($driverName) . '</a>';
                        } else {
                            $driverLink = e($driverName);
                        }
                    }
                    $rowCells[] = $driverLink;
                } elseif ($isDriverContext) {
                    // Driver context: show User/Client column
                    $authorId = $row->authorID ?? '';
                    if (!empty($authorId)) {
                        $userViewUrl = route('users.view', $authorId);
                        $rowCells[] = '<a href="' . e($userViewUrl) . '">' . e($clientName ?: 'N/A') . '</a>';
                    } else {
                        $rowCells[] = e($clientName ?: 'N/A');
                    }
                } else {
                    // Default: show Driver then User
                    $driverLink = '';
                    if (!empty($driverName)) {
                        $driverIdFromJson = $this->extractIdFromJson($row->driver);
                        if ($driverIdFromJson) {
                            $driverViewUrl = route('drivers.view', $driverIdFromJson);
                            $driverLink = '<a href="' . e($driverViewUrl) . '">' . e($driverName) . '</a>';
                        } else {
                            $driverLink = e($driverName);
                        }
                    }
                    $rowCells[] = $driverLink;

                    // User column
                    $authorId = $row->authorID ?? '';
                    if (!empty($authorId)) {
                        $userViewUrl = route('users.view', $authorId);
                        $rowCells[] = '<a href="' . e($userViewUrl) . '" class="redirecttopage">' . e($clientName ?: 'N/A') . '</a>';
                    } else {
                        $rowCells[] = '<span class="redirecttopage">' . e($clientName ?: 'N/A') . '</span>';
                    }
                }

                // Date column
                $rowCells[] = '<span class="dt-time">' . e($dateText) . '</span>';

                // Amount column
                $rowCells[] = '<span class="text-green">' . e($amountText) . '</span>';

                // Order Type column
                // $rowCells[] = e($orderTypeText);

                // Status column with proper styling
                $statusClass = $this->getStatusClass($statusText);
                $rowCells[] = '<span class="' . e($statusClass) . '"><span>' . e($statusText) . '</span></span>';

                // Actions column
                $actionHtml = '<span class="action-btn">';
                $actionHtml .= '<a href="' . e($printUrl) . '"><i class="fa fa-print" style="font-size:20px;"></i></a>';
                $actionHtml .= '<a href="' . e($editUrl) . '"><i class="mdi mdi-lead-pencil" title="Edit"></i></a>';

                // Add delete button if permission
                if ($canDelete) {
                    $actionHtml .= '<a id="' . e($id) . '" class="delete-btn" name="order-delete" href="javascript:void(0)"><i class="mdi mdi-delete"></i></a>';
                }

                $actionHtml .= '</span>';
                $rowCells[] = $actionHtml;

                $data[] = $rowCells;
            }

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsFiltered,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        }

        // Regular page load - return view with zones
        $status = $request->query('status');

        // Load zones from MySQL
        $zones = DB::table('zone')
            ->where('publish', 1)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return view('orders.index', compact('status', 'id', 'zones'));
    }

    private function extractNameFromJson($json)
    {
        if (empty($json)) return '';
        try {
            $data = is_string($json) ? json_decode($json, true) : $json;
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $first = (string) ($data['firstName'] ?? '');
                $last = (string) ($data['lastName'] ?? '');
                return trim(($first . ' ' . $last));
            }
        } catch (\Throwable $e) {}
        return '';
    }

    private function extractIdFromJson($json)
    {
        if (empty($json)) return '';
        try {
            $data = is_string($json) ? json_decode($json, true) : $json;
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return (string) ($data['id'] ?? '');
            }
        } catch (\Throwable $e) {}
        return '';
    }

    private function getStatusClass($status)
    {
        $status = strtolower(trim((string) $status));
        $classMap = [
            'restaurantorders placed' => 'order_placed',
            'orders placed' => 'order_placed',
            'order placed' => 'order_placed',
            'restaurantorders accepted' => 'order_accepted',
            'order accepted' => 'order_accepted',
            'restaurantorders rejected' => 'order_rejected',
            'order rejected' => 'order_rejected',
            'driver pending' => 'driver_pending',
            'driver rejected' => 'driver_rejected',
            'restaurantorders shipped' => 'order_shipped',
            'order shipped' => 'order_shipped',
            'in transit' => 'in_transit',
            'restaurantorders completed' => 'order_completed',
            'order completed' => 'order_completed',
        ];
        return $classMap[$status] ?? 'order_completed';
    }

    private function resolveAmount($row): float
    {
        // Prefer toPayAmount (check if it's not null and not empty string and not "0")
        if (!is_null($row->toPayAmount) && $row->toPayAmount !== '' && $row->toPayAmount !== '0') {
            $amount = (float) $row->toPayAmount;
            if ($amount > 0) {
                return $amount;
            }
        }
        // Fallback: compute from products JSON and charges
        $total = 0.0;
        try {
            $products = is_string($row->products) ? json_decode($row->products, true) : $row->products;
            if (json_last_error() === JSON_ERROR_NONE && is_array($products)) {
                foreach ($products as $product) {
                    $price = (float) ($product['discountPrice'] ?? 0);
                    if ($price <= 0) {
                        $price = (float) ($product['price'] ?? 0);
                    }
                    $qty = (int) ($product['quantity'] ?? 1);
                    $extrasPrice = (float) ($product['extras_price'] ?? 0);
                    $total += ($price * $qty) + $extrasPrice;
                }
            }
            $delivery = (float) ($row->deliveryCharge ?? 0);
            $discount = (float) ($row->discount ?? 0);
            $tip = (float) ($row->tip_amount ?? 0);
            $specialDiscount = 0.0;
            if (!empty($row->specialDiscount)) {
                $sd = is_string($row->specialDiscount) ? json_decode($row->specialDiscount, true) : $row->specialDiscount;
                if (json_last_error() === JSON_ERROR_NONE && is_array($sd)) {
                    $specialDiscount = (float) ($sd['special_discount'] ?? 0);
                }
            }
            $total = max(0.0, $total + $delivery + $tip - $discount - $specialDiscount);
        } catch (\Throwable $e) {
            $total = 0.0;
        }
        return $total;
    }

    private function formatCurrency(float $amount): string
    {
        // Simple formatting; extend to read active currency settings if needed
        $symbol = config('app.currency_symbol', '₹');
        $atRight = (bool) config('app.currency_symbol_at_right', false);
        $digits = (int) config('app.currency_decimal_digits', 2);
        $val = number_format($amount, $digits, '.', '');
        return $atRight ? ($val . $symbol) : ($symbol . $val);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|string'
            ]);

            $order = DB::table('restaurant_orders')->where('id', $id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $oldStatus = $order->status;
            $newStatus = $request->input('status');

            DB::table('restaurant_orders')
                ->where('id', $id)
                ->update(['status' => $newStatus]);

            // Log activity
            \Log::info('✅ Order status updated:', [
                'order_id' => $id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error updating order status:', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating order status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign driver to order
     */
    public function assignDriver(Request $request, $id)
    {
        try {
            $request->validate([
                'driver_id' => 'required|string'
            ]);

            $order = DB::table('restaurant_orders')->where('id', $id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $driverId = $request->input('driver_id');

            // Get driver details
            $driver = DB::table('users')->where('id', $driverId)->where('role', 'driver')->first();

            if (!$driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver not found'
                ], 404);
            }

            // Build driver JSON
            $driverData = [
                'id' => $driver->id,
                'firstName' => $driver->firstName ?? '',
                'lastName' => $driver->lastName ?? '',
                'email' => $driver->email ?? '',
                'phoneNumber' => $driver->phoneNumber ?? '',
                'carName' => $driver->carName ?? '',
                'carNumber' => $driver->carNumber ?? ''
            ];

            DB::table('restaurant_orders')
                ->where('id', $id)
                ->update([
                    'driverID' => $driverId,
                    'driver' => json_encode($driverData)
                ]);

            // Log activity
            \Log::info('✅ Driver assigned to order:', [
                'order_id' => $id,
                'driver_id' => $driverId,
                'driver_name' => ($driver->firstName ?? '') . ' ' . ($driver->lastName ?? '')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Driver assigned successfully',
                'driver_id' => $driverId,
                'driver_name' => ($driver->firstName ?? '') . ' ' . ($driver->lastName ?? '')
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error assigning driver:', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error assigning driver: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove driver from order
     */
    public function removeDriver(Request $request, $id)
    {
        try {
            $order = DB::table('restaurant_orders')->where('id', $id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $oldDriverId = $order->driverID;
            $oldDriverData = !empty($order->driver) ? json_decode($order->driver, true) : [];
            $oldDriverName = ($oldDriverData['firstName'] ?? '') . ' ' . ($oldDriverData['lastName'] ?? '');

            DB::table('restaurant_orders')
                ->where('id', $id)
                ->update([
                    'driverID' => null,
                    'driver' => null
                ]);

            // Log activity
            \Log::info('✅ Driver removed from order:', [
                'order_id' => $id,
                'driver_id' => $oldDriverId,
                'driver_name' => $oldDriverName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Driver removed successfully',
                'old_driver_id' => $oldDriverId,
                'old_driver_name' => $oldDriverName
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error removing driver:', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error removing driver: ' . $e->getMessage()
            ], 500);
        }
    }
}
