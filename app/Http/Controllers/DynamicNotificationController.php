<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DynamicNotificationController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view("dynamic_notifications.index");
    }


    public function save($id = null)
    {
        return view('dynamic_notifications.create')->with('id', $id);
    }

    /**
     * Data for DataTables (SQL-based)
     */
    public function data(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = strtolower((string) data_get($request->input('search'), 'value', ''));

        $base = DB::table('dynamic_notification');
        $total = $base->count();

        $q = DB::table('dynamic_notification')->select('id','type','subject','message','createdAt');
        if ($search !== '') {
            $q->where(function($qq) use ($search){
                $qq->where('type','like','%'.$search.'%')
                   ->orWhere('subject','like','%'.$search.'%')
                   ->orWhere('message','like','%'.$search.'%');
            });
        }
        $q->orderBy('createdAt','desc');
        $rows = $q->offset($start)->limit($length)->get();
        $filtered = ($search==='') ? $total : (clone $q)->count();

        $data = [];
        foreach ($rows as $row) {
            $rowArr = [];
            $rowArr[] = e($row->type ?? '');
            $rowArr[] = e($row->subject ?? '');
            $rowArr[] = e($row->message ?? '');
            $createdAt = '-';
            if ($row->createdAt) {
                try { $createdAt = Carbon::parse($row->createdAt)->format('M d, Y h:i A'); }
                catch (\Exception $e) { $createdAt = $row->createdAt; }
            }
            $rowArr[] = $createdAt;
            $editUrl = route('dynamic-notification.save', $row->id);
            $rowArr[] = '<span class="action-btn"><i class="text-dark fs-12 fa-solid fa fa-info" data-toggle="tooltip" title=""></i><a href="'.$editUrl.'"><i class="mdi mdi-lead-pencil" title="Edit"></i></a></span>';
            $data[] = $rowArr;
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    /** Create or update record */
    public function upsert(Request $request)
    {
        $id = $request->input('id');
        $subject = $request->input('subject');
        $message = $request->input('message');
        $type = $request->input('type');

        if (!$subject || !$message) {
            return response()->json(['success'=>false,'message'=>'Subject and message are required'], 422);
        }

        if ($id) {
            DB::table('dynamic_notification')->where('id',$id)->update([
                'subject' => $subject,
                'message' => $message,
                'type'    => $type,
            ]);
            return response()->json(['success'=>true,'message'=>'Notification updated']);
        } else {
            $newId = (string) Str::uuid();
            DB::table('dynamic_notification')->insert([
                'id' => $newId,
                'subject' => $subject,
                'message' => $message,
                'type'    => $type,
                'createdAt' => now()->toIso8601String(),
            ]);
            return response()->json(['success'=>true,'message'=>'Notification created','id'=>$newId]);
        }
    }

    /**
     * Get single notification for editing (API endpoint)
     */
    public function show($id)
    {
        $notification = DB::table('dynamic_notification')
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        return response()->json([
            'success' => true,
            'id' => $notification->id,
            'type' => $notification->type,
            'subject' => $notification->subject,
            'message' => $notification->message,
            'createdAt' => $notification->createdAt
        ]);
    }

    public function delete($id)
    {
        DB::table('dynamic_notification')->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }

}
