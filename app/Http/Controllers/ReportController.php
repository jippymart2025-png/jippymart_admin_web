<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index($type)
    {
        if ($type == "sales") {
            return view('reports.sales-report');
        }
    }

    // Return options for filters (vendors, drivers, customers, categories, currency)
    public function salesOptions()
    {
        $vendors = DB::table('vendors')->select('id','title')->orderBy('title')->get();
        $drivers = DB::table('users')->select('id','firstName','lastName')
            ->where('role','driver')->orderBy('firstName')->get();
        $customers = DB::table('users')->select('id','firstName','lastName')
            ->where('role','customer')->orderBy('firstName')->get();
        // Categories derived from vendors.categoryTitle (JSON array) - flatten uniques
        $rawCategories = DB::table('vendors')->select('categoryID','categoryTitle')->get();
        $categories = [];
        foreach ($rawCategories as $row) {
            $titlesRaw = $row->categoryTitle;
            $idsRaw = $row->categoryID;

            // Normalize IDs to array
            $ids = [];
            if (is_array($idsRaw)) {
                $ids = $idsRaw;
            } else if (is_string($idsRaw)) {
                $trim = trim($idsRaw);
                if ($trim !== '' && ($trim[0] === '[' || str_contains($trim, ',') || str_contains($trim, '"'))) {
                    $decoded = json_decode($idsRaw, true);
                    if (is_array($decoded)) { $ids = $decoded; }
                }
                if (empty($ids) && $trim !== '') { $ids = [$idsRaw]; }
            } else if (is_numeric($idsRaw)) {
                $ids = [(string)$idsRaw];
            }

            // Normalize titles to array (or scalar)
            $titles = [];
            if (is_array($titlesRaw)) {
                $titles = $titlesRaw;
            } else if (is_string($titlesRaw)) {
                $ttrim = trim($titlesRaw);
                $maybeJson = ($ttrim !== '' && $ttrim[0] === '[');
                if ($maybeJson) {
                    $decodedT = json_decode($titlesRaw, true);
                    if (is_array($decodedT)) { $titles = $decodedT; }
                }
                if (empty($titles) && $ttrim !== '') { $titles = [$titlesRaw]; }
            }

            foreach ($ids as $idx => $cid) {
                $cidStr = is_scalar($cid) ? (string)$cid : '';
                $ctitle = is_array($titles) ? ($titles[$idx] ?? $cidStr) : ($titles[0] ?? $cidStr);
                if ($cidStr !== '') { $categories[$cidStr] = $ctitle; }
            }
        }
        $categoriesOut = [];
        foreach ($categories as $cid => $ctitle) { $categoriesOut[] = ['id'=>$cid,'title'=>$ctitle]; }
        // Fetch currency with flexible column handling; fall back safely to defaults
        try {
            $currency = DB::table('currencies')->where('isActive',1)
                ->select('symbol','symbolAtRight','decimal_degits')->first();
            if (!$currency) {
                // Try alternative column naming conventions if needed
                $currency = DB::table('currencies')->where('isActive',1)
                    ->select('symbol','symbolAtRight','decimal_digits as decimal_degits')->first();
            }
        } catch (\Throwable $e) {
            $currency = null;
        }
        if (!$currency) { $currency = (object)['symbol'=>'₹','symbolAtRight'=>0,'decimal_degits'=>2]; }
        return response()->json([
            'vendors' => $vendors,
            'drivers' => $drivers,
            'customers' => $customers,
            'categories' => $categoriesOut,
            'currency' => $currency,
        ]);
    }

    // Return sales report rows based on filters
    public function salesData(Request $request)
    {
        $vendorId = $request->input('vendor_id');
        $driverId = $request->input('driver_id');
        $customerId = $request->input('customer_id');
        $categoryId = $request->input('category_id');
        $start = $request->input('start_date');
        $end = $request->input('end_date');

        $dateExpr = "CASE
            WHEN o.createdAt REGEXP '^[0-9]+$' THEN FROM_UNIXTIME(CASE WHEN LENGTH(o.createdAt)>10 THEN o.createdAt/1000 ELSE o.createdAt END)
            WHEN o.createdAt LIKE '\"%\"' THEN STR_TO_DATE(REPLACE(REPLACE(REPLACE(o.createdAt,'\"',''),'Z',''), 'T',' '), '%Y-%m-%d %H:%i:%s.%f')
            WHEN o.createdAt LIKE '%T%' THEN STR_TO_DATE(REPLACE(REPLACE(o.createdAt,'Z',''),'T',' '), '%Y-%m-%d %H:%i:%s.%f')
            WHEN STR_TO_DATE(o.createdAt, '%Y-%m-%d %H:%i:%s') IS NOT NULL THEN STR_TO_DATE(o.createdAt, '%Y-%m-%d %H:%i:%s')
            ELSE NULL END";

        $q = DB::table('restaurant_orders as o')
            ->leftJoin('vendors as v','v.id','=','o.vendorID')
            ->leftJoin('users as d','d.id','=','o.driverID')
            ->leftJoin('users as u','u.id','=','o.authorID')
            ->select(
                'o.*',
                'v.title as vendor_title',
                'v.categoryTitle as vendor_categoryTitle',
                'd.firstName as d_first',
                'd.lastName as d_last',
                'd.email as d_email',
                'd.phoneNumber as d_phone',
                'u.firstName as u_first',
                'u.lastName as u_last',
                'u.email as u_email',
                'u.phoneNumber as u_phone'
            )
            ->addSelect(DB::raw("$dateExpr as parsed_created_at"));

        // Include common variants of completed statuses
        $completedStatuses = [
            'restaurantorders Completed',
            'Order Completed',
            'Completed',
            'Driver Completed',
        ];
        $q->whereIn('o.status', $completedStatuses);
        if ($vendorId) $q->where('o.vendorID',$vendorId);
        if ($driverId) $q->where('o.driverID',$driverId);
        if ($customerId) $q->where('o.authorID',$customerId);
        if ($categoryId) {
            $q->where(function($qq) use ($categoryId) {
                $qq->where('v.categoryID', $categoryId)
                   ->orWhere('v.categoryID','like','%"'.$categoryId.'"%')
                   ->orWhere('v.categoryID','like','%'.$categoryId.'%');
            });
        }
        if ($start && $end) {
            // Robust date filtering: handle datetime strings and unix timestamps (ms or s)
            $start = (string)$start; $end = (string)$end;
            $q->whereBetween(DB::raw("($dateExpr)"), [$start, $end]);
        }

        $rows = $q->orderBy(DB::raw("($dateExpr)"),'desc')->limit(5000)->get();

        try {
            $currency = DB::table('currencies')->where('isActive',1)
                ->select('symbol','symbolAtRight','decimal_degits')->first();
            if (!$currency) {
                $currency = DB::table('currencies')->where('isActive',1)
                    ->select('symbol','symbolAtRight','decimal_digits as decimal_degits')->first();
            }
        } catch (\Throwable $e) {
            $currency = null;
        }
        if (!$currency) { $currency = (object)['symbol'=>'₹','symbolAtRight'=>0,'decimal_degits'=>2]; }

        $normalizeNumeric = function ($value) {
            if (is_null($value)) {
                return 0.0;
            }
            if (is_numeric($value)) {
                return (float) $value;
            }
            if (is_string($value)) {
                $trimmed = trim($value, "\"' ");
                if ($trimmed === '') {
                    return 0.0;
                }
                if (is_numeric($trimmed)) {
                    return (float) $trimmed;
                }
                $decoded = json_decode($value, true);
                if (is_numeric($decoded)) {
                    return (float) $decoded;
                }
            }
            return 0.0;
        };

        $out = [];
        foreach ($rows as $r) {
            $driverName = trim(($r->d_first ?? '').' '.($r->d_last ?? ''));
            $userName = trim(($r->u_first ?? '').' '.($r->u_last ?? ''));
            $rawDate = $r->parsed_created_at ?? $r->createdAt ?? '';
            $dateTxt = '';
            if (!empty($rawDate)) {
                $cleanDate = is_string($rawDate) ? trim($rawDate, "\"") : $rawDate;
                try {
                    $dateObj = Carbon::parse($cleanDate);
                    $dateTxt = $dateObj->format('M d, Y h:i A');
                } catch (\Throwable $e) {
                    $dateTxt = (string) $cleanDate;
                }
            }
            // category title: join array -> string
            $catTitles = json_decode($r->vendor_categoryTitle ?? '[]', true) ?: [];
            $categoryTxt = is_array($catTitles) ? implode(', ', $catTitles) : (string) ($r->vendor_categoryTitle ?? '');

            // Total: use ToPay if exists, else 0 (heavy calc skipped)
            $total = $normalizeNumeric($r->ToPay ?? 0);
            $adminCommission = $normalizeNumeric($r->adminCommission ?? 0);
            // currency formatting moved to frontend if needed; we keep raw numbers here
            $out[] = [
                'order_id' => $r->id,
                'restaurant' => $r->vendor_title ?? '',
                'driver_name' => $driverName,
                'driver_email' => $r->d_email ?? '',
                'driver_phone' => $r->d_phone ?? '',
                'user_name' => $userName,
                'user_email' => $r->u_email ?? '',
                'user_phone' => $r->u_phone ?? '',
                'date' => $dateTxt,
                'category' => $categoryTxt,
                'payment_method' => $r->payment_method ?? '',
                'total' => $total,
                'admin_commission' => $adminCommission,
            ];
        }

        return response()->json([
            'rows' => $out,
            'currency' => $currency,
        ]);
    }
}

?>
