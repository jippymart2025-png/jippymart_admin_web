<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index()
    {
        return view("subscription_plans.index");
    }

    public function save($id='')
    {
        return view("subscription_plans.save")->with('id',$id);
    }

    /**
     * DataTables server-side data for subscription plans
     */
    public function data(Request $request)
    {
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 1);
        $search = strtolower((string) data_get($request->input('search'), 'value', ''));

        $q = DB::table('subscription_plans');
        
        if ($search !== '') {
            $q->where(function($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                      ->orWhere('price', 'like', '%'.$search.'%')
                      ->orWhere('expiryDay', 'like', '%'.$search.'%');
            });
        }
        
        $total = (clone $q)->count();
        
        // Order by place first (to ensure free plan is first), then by name
        $rows = $q->orderBy('place', 'asc')
                  ->orderBy('name', 'asc')
                  ->offset($start)
                  ->limit($length)
                  ->get();

        $canDelete = in_array('subscription-plans.delete', json_decode(@session('user_permissions'), true) ?: []);
        
        $data = [];
        foreach ($rows as $r) {
            $row = [];
            
            // Get subscriber count
            $subscriberCount = DB::table('vendors')
                ->where('subscriptionPlanId', $r->id)
                ->count();
            
            $editUrl = route('subscription-plans.save', $r->id);
            $subscriberUrl = route('current-subscriber.list', $r->id);
            
            // Image
            $imageHtml = $r->image 
                ? '<img alt="" width="70" height="70" src="'.$r->image.'">'
                : '<img alt="" width="70" height="70" src="">';
            
            // Checkbox column (if has delete permission and not free plan)
            if ($canDelete) {
                if ($r->id != 'J0RwvxCWhZzQQD7Kc2Ll') {
                    $row[] = '<input type="checkbox" class="is_open" dataId="'.$r->id.'">';
                } else {
                    $row[] = '';
                }
            }
            
            // Name with image
            $row[] = $imageHtml . '<a href="'.$subscriberUrl.'">'.e($r->name).'</a>';
            
            // Price
            if ($r->type == 'free') {
                $row[] = '<span style="color:red;">Free</span>';
            } else {
                $row[] = '₹' . number_format($r->price, 2);
            }
            
            // Duration
            $row[] = $r->expiryDay == '-1' ? 'Unlimited' : $r->expiryDay . ' Days';
            
            // Subscribers
            $row[] = '<a href="'.$subscriberUrl.'">'.$subscriberCount.'</a>';
            
            // Status toggle (not for free plan)
            if ($r->id != 'J0RwvxCWhZzQQD7Kc2Ll') {
                $checked = $r->isEnable ? 'checked' : '';
                $row[] = '<label class="switch"><input type="checkbox" '.$checked.' class="plan-toggle" data-id="'.$r->id.'"><span class="slider round"></span></label>';
            } else {
                $row[] = '';
            }
            
            // Actions
            $actions = '<span class="action-btn"><a href="'.$editUrl.'"><i class="mdi mdi-lead-pencil"></i></a>';
            if ($canDelete && $r->id != 'J0RwvxCWhZzQQD7Kc2Ll') {
                $actions .= ' <a href="javascript:void(0)" class="delete-plan" data-id="'.$r->id.'"><i class="mdi mdi-delete"></i></a>';
            }
            $actions .= '</span>';
            $row[] = $actions;
            
            $data[] = $row;
        }
        
        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data
        ]);
    }

    /**
     * Get subscription plan by ID (JSON)
     */
    public function showJson($id)
    {
        $plan = DB::table('subscription_plans')->where('id', $id)->first();
        if (!$plan) return response()->json(['error'=>'Not found'], 404);
        return response()->json($plan);
    }

    /**
     * Create or update subscription plan
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required',
            'type' => 'required|in:free,paid',
            'expiryDay' => 'required'
        ]);

        $id = $request->input('id', uniqid());
        
        $data = [
            'id' => $id,
            'name' => $request->input('name'),
            'price' => $request->input('price'),
            'type' => $request->input('type'),
            'expiryDay' => $request->input('expiryDay'),
            'description' => $request->input('description', ''),
            'image' => $request->input('image', ''),
            'features' => $request->input('features', ''),
            'plan_points' => $request->input('plan_points', ''),
            'itemLimit' => $request->input('itemLimit', ''),
            'orderLimit' => $request->input('orderLimit', ''),
            'isEnable' => $request->boolean('isEnable', true),
            'place' => $request->input('place', '0'),
            'createdAt' => now()->format('Y-m-d H:i:s')
        ];

        DB::table('subscription_plans')->updateOrInsert(['id' => $id], $data);
        
        return response()->json(['success' => true, 'id' => $id]);
    }

    /**
     * Toggle plan status
     */
    public function toggleStatus(Request $request, $id)
    {
        $isEnable = $request->boolean('isEnable');
        
        // If disabling, check if at least one other plan is enabled
        if (!$isEnable) {
            $enabledCount = DB::table('subscription_plans')
                ->where('isEnable', 1)
                ->where('id', '!=', 'J0RwvxCWhZzQQD7Kc2Ll') // Exclude free plan
                ->where('id', '!=', $id)
                ->count();
            
            if ($enabledCount == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one subscription plan should be active'
                ], 422);
            }
        }
        
        DB::table('subscription_plans')->where('id', $id)->update(['isEnable' => $isEnable]);
        
        return response()->json(['success' => true]);
    }

    /**
     * Delete subscription plan
     */
    public function destroy($id)
    {
        // Don't allow deleting the free plan
        if ($id == 'J0RwvxCWhZzQQD7Kc2Ll') {
            return response()->json(['success' => false, 'message' => 'Cannot delete free plan'], 422);
        }
        
        DB::table('subscription_plans')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Get subscription earnings by plan
     * Table: subscription_history with subscription_plan as TEXT/JSON
     */
    public function getEarnings($planId)
    {
        try {
            $total = 0;
            $records = DB::table('subscription_history')
                ->whereRaw("subscription_plan LIKE ?", ['%"id":"'.$planId.'"%'])
                ->get();
            
            foreach ($records as $record) {
                $planData = json_decode($record->subscription_plan, true);
                if ($planData && isset($planData['price'])) {
                    $total += floatval($planData['price']);
                }
            }
            
            return response()->json(['total' => $total]);
        } catch (\Exception $e) {
            \Log::error('Error calculating earnings: ' . $e->getMessage());
            return response()->json(['total' => 0]);
        }
    }

    /**
     * Get overview data for all plans
     * Table: subscription_history with subscription_plan as TEXT/JSON
     */
    public function getOverview()
    {
        try {
            $plans = DB::table('subscription_plans')
                ->where('id', '!=', 'J0RwvxCWhZzQQD7Kc2Ll')
                ->get();
            
            $overview = [];
            foreach ($plans as $plan) {
                $earnings = 0;
                $records = DB::table('subscription_history')
                    ->whereRaw("subscription_plan LIKE ?", ['%"id":"'.$plan->id.'"%'])
                    ->get();
                
                foreach ($records as $record) {
                    $planData = json_decode($record->subscription_plan, true);
                    if ($planData && isset($planData['price'])) {
                        $earnings += floatval($planData['price']);
                    }
                }
                
                $overview[] = [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'image' => $plan->image,
                    'earnings' => $earnings
                ];
            }
            
            return response()->json($overview);
        } catch (\Exception $e) {
            \Log::error('Error getting overview: ' . $e->getMessage());
            return response()->json([]);
        }
    }
   
}
