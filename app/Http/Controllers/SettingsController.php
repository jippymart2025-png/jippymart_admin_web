<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Vendor;

class SettingsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function social()
    {
        return view("settings.app.social");
    }

    public function globals()
    {
        return view("settings.app.global");
    }

    public function notifications()
    {
        return view("settings.app.notification");
    }

    public function cod()
    {
        return view('settings.app.cod');
    }

    public function applePay()
    {
        return view('settings.app.applepay');
    }

    public function stripe()
    {
        return view('settings.app.stripe');
    }

    public function mobileGlobals()
    {
        return view('settings.mobile.globals');
    }

    public function razorpay()
    {
        return view('settings.app.razorpay');
    }

    public function paytm()
    {
        return view('settings.app.paytm');
    }

    public function payfast()
    {
        return view('settings.app.payfast');
    }

    public function paypal()
    {
        return view('settings.app.paypal');
    }

    public function orangepay()
    {
        return view('settings.app.orangepay');
    }

    public function xendit()
    {
        return view('settings.app.xendit');
    }

    public function midtrans()
    {
        return view('settings.app.midtrans');
    }

    public function adminCommission()
    {
        return view("settings.app.adminCommission");
    }

    public function radiosConfiguration()
    {
        return view("settings.app.radiosConfiguration");
    }

    public function wallet()
    {
        return view('settings.app.wallet');
    }

    public function bookTable()
    {
        return view('settings.app.bookTable');
    }


    public function paystack()
    {
        return view('settings.app.paystack');
    }

    public function flutterwave()
    {
        return view('settings.app.flutterwave');
    }

    public function mercadopago()
    {
        return view('settings.app.mercadopago');
    }

    public function deliveryCharge()
    {
        return view("settings.app.deliveryCharge");
    }
    public function martSettings()
    {
        return view("settings.app.martSettings");
    }
    public function appSettings()
    {
        return view("settings.app.appSettings");
    }
    public function priceSetting()
    {
        return view("settings.app.priceSettings");
    }
    public function languages()
    {
        return view('settings.languages.index');
    }

    public function languagesedit($id)
    {
        return view('settings.languages.edit')->with('id', $id);
    }

    public function languagescreate()
    {
        return view('settings.languages.create');
    }

    public function specialOffer()
    {
        return view('settings.app.specialDiscountOffer');
    }

    public function story()
    {
        return view('settings.app.story');

    }

    public function footerTemplate()
    {
        return view('footerTemplate.index');
    }

    public function homepageTemplate()
    {
        return view('homepage_Template.index');
    }

    public function emailTemplatesIndex()
    {
        return view('email_templates.index');
    }

    public function emailTemplatesSave($id = '')
    {

        return view('email_templates.save')->with('id', $id);
    }
    public function documentVerification()
    {
        return view('settings.app.documentVerificationSetting');
    }

    public function surgeRules()
    {
      return view('settings.app.surgeRules');
    }

    // Email templates SQL endpoints
    public function emailTemplatesData()
    {
        $start = (int) request('start', 0);
        $length = (int) request('length', 10);
        $draw = (int) request('draw', 1);
        $search = strtolower((string) data_get(request('search'), 'value', ''));

        $q = DB::table('email_templates');
        if ($search !== '') {
            $q->where(function($qq) use ($search){
                $qq->where('type','like','%'.$search.'%')
                   ->orWhere('subject','like','%'.$search.'%');
            });
        }
        $total = (clone $q)->count();
        $rows = $q->orderBy('type','asc')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($rows as $r) {
            $editUrl = route('email-templates.save', $r->id);
            $typeLabel = $r->type; // mapping done in view if needed
            $actions = '<span class="action-btn"><a href="'.$editUrl.'"><i class="mdi mdi-lead-pencil" title="Edit"></i></a> '
                    .'<a href="javascript:void(0)" class="delete-template" data-id="'.$r->id.'"><i class="mdi mdi-delete" title="Delete"></i></a></span>';
            $data[] = [ $typeLabel, e($r->subject ?: ''), $actions ];
        }
        return response()->json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$total,'data'=>$data]);
    }

    public function emailTemplatesJson($id)
    {
        $rec = DB::table('email_templates')->where('id',$id)->first();
        if(!$rec) return response()->json(['error'=>'Not found'],404);
        return response()->json($rec);
    }

    public function emailTemplatesUpdate($id)
    {
        request()->validate([
            'subject'=>'required|string',
            'message'=>'required|string',
            'isSendToAdmin'=>'nullable'
        ]);
        $updated = DB::table('email_templates')->where('id',$id)->update([
            'subject'=>request('subject'),
            'message'=>request('message'),
            'isSendToAdmin'=>request()->boolean('isSendToAdmin') ? 1 : 0,
        ]);
        if ($updated === false) return response()->json(['success'=>false],500);
        return response()->json(['success'=>true]);
    }

    public function emailTemplatesDelete($id)
    {
        $exists = DB::table('email_templates')->where('id',$id)->exists();
        if(!$exists) return response()->json(['success'=>false],404);
        DB::table('email_templates')->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }

    /**
     * Admin Commission: SQL-backed settings stored in `settings` table as JSON in `fields`.
     */
    public function getAdminCommissionSettings()
    {
        $admin = DB::table('settings')->where('doc_id', 'AdminCommission')->first();
        $restaurant = DB::table('settings')->where('doc_id', 'restaurant')->first();

        $adminFields = $admin && $admin->fields ? json_decode($admin->fields, true) : [];
        $restaurantFields = $restaurant && $restaurant->fields ? json_decode($restaurant->fields, true) : [];

        return response()->json([
            'adminCommission' => [
                'isEnabled' => (bool) ($adminFields['isEnabled'] ?? false),
                'fix_commission' => (string) ($adminFields['fix_commission'] ?? ''),
                'commissionType' => (string) ($adminFields['commissionType'] ?? 'Percent'),
            ],
            'restaurant' => [
                'subscription_model' => (bool) ($restaurantFields['subscription_model'] ?? false),
            ],
        ]);
    }

    public function updateAdminCommission(Request $request)
    {
        $payload = $request->validate([
            'isEnabled' => 'nullable|boolean',
            'fix_commission' => 'nullable|string',
            'commissionType' => 'nullable|string|in:Percent,Fixed',
        ]);

        $fields = [
            'isEnabled' => (bool) ($payload['isEnabled'] ?? false),
            'fix_commission' => (string) ($payload['fix_commission'] ?? '0'),
            'commissionType' => (string) ($payload['commissionType'] ?? 'Percent'),
        ];

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'AdminCommission'],
            ['fields' => json_encode($fields, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['success' => true]);
    }

    public function updateSubscriptionModel(Request $request)
    {
        $payload = $request->validate([
            'subscription_model' => 'required|boolean',
        ]);

        $restaurant = DB::table('settings')->where('doc_id', 'restaurant')->first();
        $existing = $restaurant && $restaurant->fields ? json_decode($restaurant->fields, true) : [];
        $existing['subscription_model'] = (bool) $payload['subscription_model'];

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'restaurant'],
            ['fields' => json_encode($existing, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['success' => true]);
    }

    public function getVendorsForCommission(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $query = Vendor::query()->select(['id','title'])->orderBy('title','asc');
        if ($search !== '') {
            $query->where('title','like','%'.$search.'%');
        }
        $vendors = $query->limit(100)->get()->map(function($v){
            return ['id' => $v->id, 'text' => $v->title];
        });
        return response()->json(['results' => $vendors]);
    }

    public function bulkUpdateVendorCommission(Request $request)
    {
        $payload = $request->validate([
            'scope' => 'required|in:all,custom',
            'vendor_ids' => 'array',
            'vendor_ids.*' => 'string',
            'commissionType' => 'required|in:Percent,Fixed',
            'fix_commission' => 'required|string',
        ]);

        $adminCommission = [
            'commissionType' => $payload['commissionType'],
            'fix_commission' => $payload['fix_commission'],
            'isEnabled' => true,
        ];

        $q = Vendor::query();
        if ($payload['scope'] === 'custom') {
            $ids = $payload['vendor_ids'] ?? [];
            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'No vendors selected'], 422);
            }
            $q->whereIn('id', $ids);
        }

        $updated = $q->update(['adminCommission' => json_encode($adminCommission, JSON_UNESCAPED_UNICODE)]);
        return response()->json(['success' => true, 'updated' => $updated]);
    }

    /**
     * Radius Configuration: SQL-backed via `settings` table.
     */
    public function getRadiusSettings()
    {
        $restaurant = DB::table('settings')->where('doc_id', 'RestaurantNearBy')->first();
        $driver = DB::table('settings')->where('doc_id', 'DriverNearBy')->first();

        $restaurantFields = $restaurant && $restaurant->fields ? json_decode($restaurant->fields, true) : [];
        $driverFields = $driver && $driver->fields ? json_decode($driver->fields, true) : [];

        return response()->json([
            'distanceType' => (string) ($restaurantFields['distanceType'] ?? 'km'),
            'restaurantNearBy' => (string) ($restaurantFields['radios'] ?? ''),
            'driverNearBy' => (string) ($driverFields['driverRadios'] ?? ''),
            'driverOrderAcceptRejectDuration' => (int) ($driverFields['driverOrderAcceptRejectDuration'] ?? 0),
        ]);
    }

    public function updateRadiusSettings(Request $request)
    {
        $payload = $request->validate([
            'restaurantNearBy' => 'required|numeric|min:0',
            'driverNearBy' => 'required|numeric|min:0',
            'driverOrderAcceptRejectDuration' => 'required|integer|min:0',
            'distanceType' => 'required|string|in:km,miles',
        ]);

        $restaurantFields = [
            'radios' => (string) $payload['restaurantNearBy'],
            'distanceType' => (string) $payload['distanceType'],
        ];

        $driverFields = [
            'driverRadios' => (string) $payload['driverNearBy'],
            'driverOrderAcceptRejectDuration' => (int) $payload['driverOrderAcceptRejectDuration'],
        ];

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'RestaurantNearBy'],
            ['fields' => json_encode($restaurantFields, JSON_UNESCAPED_UNICODE)]
        );

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'DriverNearBy'],
            ['fields' => json_encode($driverFields, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Dine-in Future Settings: SQL-backed via `settings` table.
     */
    public function getDineInSettings()
    {
        $rec = DB::table('settings')->where('doc_id', 'DineinForRestaurant')->first();
        $fields = $rec && $rec->fields ? json_decode($rec->fields, true) : [];
        return response()->json([
            'isEnabled' => (bool) ($fields['isEnabled'] ?? false),
            'isEnabledForCustomer' => (bool) ($fields['isEnabledForCustomer'] ?? false),
        ]);
    }

    public function updateDineInSettings(Request $request)
    {
        $payload = $request->validate([
            'isEnabled' => 'nullable|boolean',
            'isEnabledForCustomer' => 'nullable|boolean',
        ]);

        $fields = [
            'isEnabled' => (bool) ($payload['isEnabled'] ?? false),
            'isEnabledForCustomer' => (bool) ($payload['isEnabledForCustomer'] ?? false),
        ];

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'DineinForRestaurant'],
            ['fields' => json_encode($fields, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Delivery Charge Settings: SQL-backed via `settings` table.
     */
    public function getDeliveryChargeSettings()
    {
        $rec = DB::table('settings')->where('doc_id', 'DeliveryCharge')->first();
        $fields = $rec && $rec->fields ? json_decode($rec->fields, true) : [];

        return response()->json([
            'vendor_can_modify' => (bool) ($fields['vendor_can_modify'] ?? false),
            'delivery_charges_per_km' => $fields['delivery_charges_per_km'] ?? null,
            'minimum_delivery_charges' => $fields['minimum_delivery_charges'] ?? null,
            'minimum_delivery_charges_within_km' => $fields['minimum_delivery_charges_within_km'] ?? null,
            'base_delivery_charge' => $fields['base_delivery_charge'] ?? 23,
            'free_delivery_distance_km' => $fields['free_delivery_distance_km'] ?? 5,
            'per_km_charge_above_free_distance' => $fields['per_km_charge_above_free_distance'] ?? 7,
            'item_total_threshold' => $fields['item_total_threshold'] ?? 199,
        ]);
    }

    public function updateDeliveryChargeSettings(Request $request)
    {
        // We allow optional numeric fields; merge with existing values to preserve unspecified ones
        $payload = $request->all();

        $rec = DB::table('settings')->where('doc_id', 'DeliveryCharge')->first();
        $existing = $rec && $rec->fields ? json_decode($rec->fields, true) : [];

        $merged = $existing;
        $merged['vendor_can_modify'] = $request->boolean('vendor_can_modify');

        $numericKeys = [
            'delivery_charges_per_km',
            'minimum_delivery_charges',
            'minimum_delivery_charges_within_km',
            'base_delivery_charge',
            'free_delivery_distance_km',
            'per_km_charge_above_free_distance',
            'item_total_threshold',
        ];

        foreach ($numericKeys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== '' && $payload[$key] !== null) {
                $merged[$key] = (int) $payload[$key];
            }
        }

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'DeliveryCharge'],
            ['fields' => json_encode($merged, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['success' => true]);
    }

    /**
     * App Settings: SQL-backed via `app_settings` table.
     */
    public function getAppSettingsData()
    {
        $row = DB::table('app_settings')->where('id', 'version_info')->first();
        if (!$row) {
            return response()->json([
                'force_update' => true,
                'latest_version' => '2.3.4',
                'min_required_version' => '1.0.0',
                'update_message' => 'Please Update',
                'update_url' => null,
                'android_version' => null,
                'android_build' => null,
                'android_update_url' => null,
                'ios_version' => null,
                'ios_build' => null,
                'ios_update_url' => null,
                'last_updated' => null,
            ]);
        }
        return response()->json([
            'force_update' => (bool) ($row->force_update ?? false),
            'latest_version' => $row->latest_version,
            'min_required_version' => $row->min_required_version,
            'update_message' => $row->update_message,
            'update_url' => $row->update_url,
            'android_version' => $row->android_version,
            'android_build' => $row->android_build,
            'android_update_url' => $row->android_update_url,
            'ios_version' => $row->ios_version,
            'ios_build' => $row->ios_build,
            'ios_update_url' => $row->ios_update_url,
            'last_updated' => $row->last_updated,
        ]);
    }

    public function updateAppSettingsData(Request $request)
    {
        $payload = $request->validate([
            'force_update' => 'nullable|boolean',
            'latest_version' => 'required|string',
            'min_required_version' => 'required|string',
            'update_message' => 'required|string',
            'update_url' => 'required|string',
            'android_version' => 'required|string',
            'android_build' => 'required|string',
            'android_update_url' => 'required|string',
            'ios_version' => 'required|string',
            'ios_build' => 'required|string',
            'ios_update_url' => 'required|string',
        ]);

        $data = [
            'force_update' => $request->boolean('force_update'),
            'latest_version' => $payload['latest_version'],
            'min_required_version' => $payload['min_required_version'],
            'update_message' => $payload['update_message'],
            'update_url' => $payload['update_url'],
            'android_version' => $payload['android_version'],
            'android_build' => $payload['android_build'],
            'android_update_url' => $payload['android_update_url'],
            'ios_version' => $payload['ios_version'],
            'ios_build' => $payload['ios_build'],
            'ios_update_url' => $payload['ios_update_url'],
            'last_updated' => now()->format('Y-m-d H:i:s'),
        ];

        DB::table('app_settings')->updateOrInsert(['id' => 'version_info'], $data);
        return response()->json(['success' => true]);
    }

    /**
     * Mart Settings: SQL-backed via `mart_settings` table.
     */
    public function getMartSettingsData()
    {
        $row = DB::table('mart_settings')->where('id', 'delivery_settings')->first();
        if (!$row) {
            return response()->json([
                'is_active' => true,
                'free_delivery_distance_km' => 3,
                'free_delivery_threshold' => 199,
                'per_km_charge_above_free_distance' => 7,
                'min_order_value' => 99,
                'min_order_message' => 'Min Item value is ₹99',
                'delivery_promotion_text' => 'Daily',
            ]);
        }
        return response()->json([
            'is_active' => (bool) ($row->is_active ?? false),
            'free_delivery_distance_km' => (int) ($row->free_delivery_distance_km ?? 0),
            'free_delivery_threshold' => (int) ($row->free_delivery_threshold ?? 0),
            'per_km_charge_above_free_distance' => (int) ($row->per_km_charge_above_free_distance ?? 0),
            'min_order_value' => (int) ($row->min_order_value ?? 0),
            'min_order_message' => $row->min_order_message,
            'delivery_promotion_text' => $row->delivery_promotion_text,
        ]);
    }

    public function updateMartSettingsData(Request $request)
    {
        $payload = $request->validate([
            'is_active' => 'nullable|boolean',
            'free_delivery_distance_km' => 'required|integer|min:0',
            'free_delivery_threshold' => 'required|integer|min:0',
            'per_km_charge_above_free_distance' => 'required|integer|min:0',
            'min_order_value' => 'required|integer|min:0',
            'min_order_message' => 'required|string',
            'delivery_promotion_text' => 'required|string',
        ]);

        $data = [
            'is_active' => $request->boolean('is_active'),
            'free_delivery_distance_km' => (int) $payload['free_delivery_distance_km'],
            'free_delivery_threshold' => (int) $payload['free_delivery_threshold'],
            'per_km_charge_above_free_distance' => (int) $payload['per_km_charge_above_free_distance'],
            'min_order_value' => (int) $payload['min_order_value'],
            'min_order_message' => $payload['min_order_message'],
            'delivery_promotion_text' => $payload['delivery_promotion_text'],
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];

        DB::table('mart_settings')->updateOrInsert(['id' => 'delivery_settings'], $data);
        return response()->json(['success' => true]);
    }

    /**
     * Surge Rules: SQL-backed via `surge_rules` table for main fields, plus 'settings' JSON for admin_surge_fee.
     */
    public function getSurgeRulesData()
    {
        $row = DB::table('surge_rules')->where('firestore_id', 'surge_settings')->first();
        $extra = DB::table('settings')->where('doc_id', 'surge_rules_config')->first();
        $extraFields = $extra && $extra->fields ? json_decode($extra->fields, true) : [];

        return response()->json([
            'bad_weather' => (int) ($row->bad_weather ?? 0),
            'rain' => (int) ($row->rain ?? 0),
            'summer' => (int) ($row->summer ?? 0),
            'admin_surge_fee' => (int) ($extraFields['admin_surge_fee'] ?? 0),
        ]);
    }

    public function updateSurgeRulesData(Request $request)
    {
        $payload = $request->validate([
            'bad_weather' => 'required|integer|min:0',
            'rain' => 'required|integer|min:0',
            'summer' => 'required|integer|min:0',
            'admin_surge_fee' => 'required|integer|min:0',
        ]);

        DB::table('surge_rules')->updateOrInsert(
            ['firestore_id' => 'surge_settings'],
            [
                'bad_weather' => (string) $payload['bad_weather'],
                'rain' => (string) $payload['rain'],
                'summer' => (string) $payload['summer'],
                'updated_at' => now(),
            ]
        );

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'surge_rules_config'],
            ['fields' => json_encode(['admin_surge_fee' => (int) $payload['admin_surge_fee']], JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Document Verification Settings: SQL-backed via `settings` table.
     */
    public function getDocumentVerificationSettings()
    {
        $rec = DB::table('settings')->where('doc_id', 'document_verification_settings')->first();
        $fields = $rec && $rec->fields ? json_decode($rec->fields, true) : [];
        return response()->json([
            'isDriverVerification' => (bool) ($fields['isDriverVerification'] ?? false),
            'isRestaurantVerification' => (bool) ($fields['isRestaurantVerification'] ?? false),
        ]);
    }

    public function updateDocumentVerificationSettings(Request $request)
    {
        $payload = $request->validate([
            'isDriverVerification' => 'nullable|boolean',
            'isRestaurantVerification' => 'nullable|boolean',
        ]);

        $fields = [
            'isDriverVerification' => (bool) ($payload['isDriverVerification'] ?? false),
            'isRestaurantVerification' => (bool) ($payload['isRestaurantVerification'] ?? false),
        ];

        DB::table('settings')->updateOrInsert(
            ['doc_id' => 'document_verification_settings'],
            ['fields' => json_encode($fields, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json(['success' => true]);
    }
}
