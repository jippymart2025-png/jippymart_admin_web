<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class restaurant_orders extends Model
{
    use HasFactory;

    protected $table = 'restaurant_orders';
    protected $guarded = [];
    public $timestamps = false;

    /**
     * Build the DataTables dataset using raw SQL/joins here (not in controller).
     * Returns [rows => Collection, recordsFiltered => int].
     */
    public static function fetchForDatatable(array $params): array
    {
        $vendorId = (string) ($params['vendor_id'] ?? '');
        $userId = (string) ($params['user_id'] ?? '');
        $driverId = (string) ($params['driver_id'] ?? '');
        $status = (string) ($params['status'] ?? '');
        $zoneId = (string) ($params['zone_id'] ?? '');
        $orderType = (string) ($params['order_type'] ?? '');
        $dateFrom = (string) ($params['date_from'] ?? '');
        $dateTo = (string) ($params['date_to'] ?? '');
        $searchValue = strtolower((string) ($params['search'] ?? ''));
        $orderBy = (string) ($params['order_by'] ?? 'ro.id');
        $orderDir = (string) ($params['order_dir'] ?? 'desc');
        $start = (int) ($params['start'] ?? 0);
        $length = (int) ($params['length'] ?? 10);

        $query = DB::table('restaurant_orders as ro')
            ->leftJoin('vendors as v', 'v.id', '=', 'ro.vendorID')
            ->leftJoin('users as u', 'u.id', '=', 'ro.authorID')
            ->select(
                'ro.id',
                'ro.status',
                'ro.takeAway',
                'ro.createdAt',
                'ro.toPayAmount',
                'ro.products',
                'ro.discount',
                'ro.deliveryCharge',
                'ro.tip_amount',
                'ro.specialDiscount',
                'ro.author',
                'ro.authorID',
                'ro.driver',
                'ro.driverID',
                'v.id as vendor_id',
                'v.title as vendor_title',
                'v.vType as vendor_type',
                'v.zoneId as vendor_zone_id',
                'u.firstName as user_first_name',
                'u.lastName as user_last_name'
            );

        if ($vendorId !== '') {
            $query->where('ro.vendorID', $vendorId);
        }
        if ($userId !== '') {
            $query->where('ro.authorID', $userId);
        }
        if ($driverId !== '') {
            $query->where('ro.driverID', $driverId);
        }
        if ($status !== '' && strtolower($status) !== 'all') {
            $query->where('ro.status', $status);
        }
        if ($orderType === 'takeaway') {
            $query->where('ro.takeAway', '1');
        } elseif ($orderType === 'delivery') {
            $query->where(function ($q) {
                $q->whereNull('ro.takeAway')->orWhere('ro.takeAway', '0');
            });
        }
        if ($zoneId !== '') {
            $query->where('v.zoneId', $zoneId);
        }
        if ($dateFrom !== '' && $dateTo !== '') {
            try {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $to = Carbon::parse($dateTo)->endOfDay();
                // createdAt is an ISO string in this DB; compare lexicographically
                $query->whereBetween('ro.createdAt', [$from->toIso8601ZuluString(), $to->toIso8601ZuluString()]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->orWhereRaw('LOWER(ro.id) LIKE ?', ["%{$searchValue}%"])
                  ->orWhereRaw('LOWER(v.title) LIKE ?', ["%{$searchValue}%"])
                  ->orWhereRaw('LOWER(ro.status) LIKE ?', ["%{$searchValue}%"]);
            });
        }

        $recordsFiltered = (clone $query)->count();
        $rows = $query->orderBy($orderBy, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'rows' => $rows,
            'recordsFiltered' => $recordsFiltered,
        ];
    }
}
