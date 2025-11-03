<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\VendorAttribute;

class AttributeController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view("attributes.index");
    }

    public function edit($id)
    {
        return view('attributes.edit')->with('id', $id);
    }

    public function create()
    {
        return view('attributes.create');
    }

    // DataTables server-side data
    public function data(Request $request)
    {
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 1);
        $search = strtolower((string) data_get($request->input('search'), 'value', ''));

        $q = DB::table('vendor_attributes');
        if ($search !== '') {
            $q->where('title', 'like', '%'.$search.'%');
        }
        $total = (clone $q)->count();
        $rows = $q->orderBy('title', 'asc')->offset($start)->limit($length)->get();

        $canDelete = in_array('attributes.delete', json_decode(@session('user_permissions'), true) ?: []);
        $data = [];
        foreach ($rows as $r) {
            $editUrl = route('attributes.edit', $r->id);
            $nameHtml = '<a href="'.$editUrl.'">'.e($r->title ?: '').'</a>';
            $actionsHtml = '<span class="action-btn"><a href="'.$editUrl.'"><i class="mdi mdi-lead-pencil" title="Edit"></i></a></span>';
            if ($canDelete) {
                $actionsHtml .= ' <span class="action-btn"><a href="javascript:void(0)" data-id="'.$r->id.'" class="delete-attribute"><i class="mdi mdi-delete" title="Delete"></i></a></span>';
            }
            $data[] = [ $nameHtml, $actionsHtml ];
        }
        return response()->json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$total,'data'=>$data]);
    }

    public function showJson($id)
    {
        $attr = VendorAttribute::find($id);
        if (!$attr) return response()->json(['error'=>'Not found'],404);
        return response()->json($attr);
    }

    public function store(Request $request)
    {
        $request->validate(['title'=>'required|string|max:255']);
        $id = uniqid();
        VendorAttribute::create(['id'=>$id,'title'=>$request->input('title')]);
        return response()->json(['success'=>true,'id'=>$id]);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['title'=>'required|string|max:255']);
        $attr = VendorAttribute::findOrFail($id);
        $attr->title = $request->input('title');
        $attr->save();
        return response()->json(['success'=>true]);
    }

    public function destroy($id)
    {
        $attr = VendorAttribute::find($id);
        if (!$attr) return response()->json(['success'=>false],404);
        $attr->delete();
        return response()->json(['success'=>true]);
    }
}


