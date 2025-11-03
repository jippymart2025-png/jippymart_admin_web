<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Mealtime;

class MenuPeriodController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('menu_periods.index');
    }

    public function create()
    {
        return view('menu_periods.create');
    }

    public function edit($id)
    {
        return view('menu_periods.edit')->with('id', $id);
    }

    // Data for DataTables
    public function data(Request $request)
    {
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 1);
        $search = strtolower((string) data_get($request->input('search'), 'value', ''));

        $q = DB::table('mealtimes as m');
        if ($search !== '') {
            $q->where(function($qq) use ($search){
                $qq->where('m.label','like','%'.$search.'%')
                   ->orWhere('m.from','like','%'.$search.'%')
                   ->orWhere('m.to','like','%'.$search.'%');
            });
        }
        $total = (clone $q)->count();
        $rows = $q->orderBy('m.label','asc')->offset($start)->limit($length)->get();

        $canDelete = in_array('menu-periods.delete', json_decode(@session('user_permissions'), true) ?: []);
        $data = [];
        foreach ($rows as $r) {
            $hasId = !empty($r->id);
            if (!$hasId) {
                // Normalize missing IDs so rows become actionable
                $newId = (string) Str::uuid();
                $updated = DB::table('mealtimes')
                    ->where(function($qq) use ($r){
                        $qq->whereNull('id')->orWhere('id','');
                    })
                    ->where('label', $r->label)
                    ->where('from', $r->from)
                    ->where('to', $r->to)
                    ->limit(1)
                    ->update(['id'=>$newId]);
                if ($updated) {
                    $r->id = $newId;
                    $hasId = true;
                }
            }
            $editUrl = $hasId ? route('menu-periods.edit', $r->id) : 'javascript:void(0)';
            $deleteCell = '<td class="delete-all"><input type="checkbox" name="record" class="is_open" dataId="'.$r->id.'"'.($hasId ? '' : ' disabled').'><label class="col-3 control-label"></label></td>';
            $labelHtml = $hasId ? '<a href="'.$editUrl.'">'.e($r->label ?: '').'</a>' : e($r->label ?: '');
            $fromHtml = '<span class="badge badge-info">'.e($r->from ?: '').'</span>';
            $toHtml = '<span class="badge badge-success">'.e($r->to ?: '').'</span>';
            $actionsHtml = '<span class="action-btn">'.($hasId ? '<a href="'.$editUrl.'"><i class="mdi mdi-lead-pencil" title="Edit"></i></a>' : '');
            if ($canDelete && $hasId) {
                $actionsHtml .= ' <a href="javascript:void(0)" data-id="'.$r->id.'" class="delete-menu-period"><i class="mdi mdi-delete" title="Delete"></i></a>';
            }
            $actionsHtml .= '</span>';
            $data[] = $canDelete ? [ $deleteCell, $labelHtml, $fromHtml, $toHtml, $actionsHtml ]
                                 : [ $labelHtml, $fromHtml, $toHtml, $actionsHtml ];
        }
        return response()->json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$total,'data'=>$data]);
    }

    public function showJson($id)
    {
        $mp = Mealtime::find($id);
        if (!$mp) return response()->json(['error'=>'Not found'],404);
        return response()->json($mp);
    }

    public function store(Request $request)
    {
        $request->validate([
            'label'=>'required|string|max:255',
            'from'=>'required|string|max:50',
            'to'=>'required|string|max:50',
        ]);
        $id = (string) Str::uuid();
        Mealtime::create([
            'id'=>$id,
            'label'=>$request->input('label'),
            'from'=>$request->input('from'),
            'to'=>$request->input('to'),
        ]);
        return response()->json(['success'=>true,'id'=>$id]);
    }

    public function update(Request $request, $id)
    {
        $mp = Mealtime::findOrFail($id);
        $request->validate([
            'label'=>'required|string|max:255',
            'from'=>'required|string|max:50',
            'to'=>'required|string|max:50',
        ]);
        $mp->update([
            'label'=>$request->input('label'),
            'from'=>$request->input('from'),
            'to'=>$request->input('to'),
        ]);
        return response()->json(['success'=>true]);
    }

    public function destroy($id)
    {
        $mp = Mealtime::find($id);
        if (!$mp) return response()->json(['success'=>false],404);
        $mp->delete();
        return response()->json(['success'=>true]);
    }
}
