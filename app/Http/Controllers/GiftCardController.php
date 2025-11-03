<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\GiftCard;

class GiftCardController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index()
    {
        return view("gift_card.index");

    }

    public function save($id="")
    {
        return view('gift_card.save')->with('id', $id);
    }
    public function edit($id)
    {
        return view('gift_card.save')->with('id', $id);
    }

    // DataTables
    public function data(Request $request)
    {
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 1);
        $search = strtolower((string) data_get($request->input('search'), 'value', ''));

        $q = DB::table('gift_cards as g');
        if ($search !== '') {
            $q->where(function($qq) use ($search){
                $qq->where('g.title','like','%'.$search.'%')
                   ->orWhere('g.expiryDay','like','%'.$search.'%');
            });
        }
        $total = (clone $q)->count();
        $rows = $q->orderBy('g.title','asc')->offset($start)->limit($length)->get();

        $canDelete = in_array('gift-card.delete', json_decode(@session('user_permissions'), true) ?: []);
        $placeholder = asset('assets/images/placeholder-image.png');
        $data = [];
        foreach ($rows as $r) {
            $editUrl = route('gift-card.edit', $r->id);
            $img = $r->image ?: $placeholder;
            $imgHtml = '<img onerror="this.onerror=null;this.src=\''.$placeholder.'\'" class="rounded" width="100%" style="width:70px;height:70px;" src="'.$img.'" alt="image">';
            $titleHtml = $imgHtml.'<a href="'.$editUrl.'">'.e($r->title ?: '').'</a>';
            $expiry = e($r->expiryDay ?: 0).' Days';
            $toggle = $r->isEnable ? '<label class="switch"><input type="checkbox" checked data-id="'.$r->id.'" class="toggle-enable"><span class="slider round"></span></label>'
                                   : '<label class="switch"><input type="checkbox" data-id="'.$r->id.'" class="toggle-enable"><span class="slider round"></span></label>';
            $actionsHtml = '<span class="action-btn"><a href="'.$editUrl.'"><i class="mdi mdi-lead-pencil" title="Edit"></i></a>';
            if ($canDelete) {
                $actionsHtml .= ' <a href="javascript:void(0)" data-id="'.$r->id.'" class="delete-gift"><i class="mdi mdi-delete" title="Delete"></i></a>';
            }
            $actionsHtml .= '</span>';
            if ($canDelete) {
                $select = '<td class="delete-all"><input type="checkbox" class="is_open" dataId="'.$r->id.'"><label class="col-3 control-label"></label></td>';
                $data[] = [ $select, $titleHtml, $expiry, $toggle, $actionsHtml ];
            } else {
                $data[] = [ $titleHtml, $expiry, $toggle, $actionsHtml ];
            }
        }
        return response()->json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$total,'data'=>$data]);
    }

    public function json($id)
    {
        $g = GiftCard::find($id);
        if(!$g) return response()->json(['error'=>'Not found'],404);
        return response()->json($g);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'=>'required|string|max:255',
            'message'=>'required|string',
            'expiryDay'=>'required|integer|min:1',
            'image'=>'nullable|image',
        ]);
        $id = (string) Str::uuid();
        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = Storage::url($request->file('image')->store('public/uploads/gift_cards'));
        }
        GiftCard::create([
            'id'=>$id,
            'title'=>$request->input('title'),
            'message'=>$request->input('message'),
            'expiryDay'=>$request->input('expiryDay'),
            'isEnable'=>$request->boolean('isEnable')?1:0,
            'image'=>$imageUrl,
            'createdAt'=>now()->format('Y-m-d H:i:s'),
        ]);
        return response()->json(['success'=>true,'id'=>$id]);
    }

    public function update(Request $request, $id)
    {
        $g = GiftCard::findOrFail($id);
        $request->validate([
            'title'=>'required|string|max:255',
            'message'=>'required|string',
            'expiryDay'=>'required|integer|min:1',
            'image'=>'nullable|image',
        ]);
        $imageUrl = $g->image;
        if ($request->hasFile('image')) {
            $imageUrl = Storage::url($request->file('image')->store('public/uploads/gift_cards'));
        }
        $g->update([
            'title'=>$request->input('title'),
            'message'=>$request->input('message'),
            'expiryDay'=>$request->input('expiryDay'),
            'isEnable'=>$request->boolean('isEnable',$g->isEnable)?1:0,
            'image'=>$imageUrl,
        ]);
        return response()->json(['success'=>true]);
    }

    public function toggle($id, Request $request)
    {
        $g = GiftCard::findOrFail($id);
        $g->isEnable = $request->boolean('isEnable') ? 1 : 0;
        $g->save();
        return response()->json(['success'=>true,'id'=>$g->id,'isEnable'=>(bool)$g->isEnable]);
    }

    public function destroy($id)
    {
        $g = GiftCard::find($id);
        if(!$g) return response()->json(['success'=>false],404);
        $g->delete();
        return response()->json(['success'=>true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        if (empty($ids)) return response()->json(['success'=>false,'message'=>'No ids'],400);
        GiftCard::whereIn('id',$ids)->delete();
        return response()->json(['success'=>true]);
    }

}
