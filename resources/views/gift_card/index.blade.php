@extends('layouts.app')
@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{trans('lang.gift_card_plural')}}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{url('/dashboard')}}">{{trans('lang.dashboard')}}</a></li>
                <li class="breadcrumb-item active">{{trans('lang.gift_card_table')}}</li>
            </ol>
        </div>
        <div>
        </div>
    </div>
    <div class="container-fluid">
       <div class="admin-top-section">
        <div class="row">
            <div class="col-12">
                <div class="d-flex top-title-section pb-4 justify-content-between">
                    <div class="d-flex top-title-left align-self-center">
                        <span class="icon mr-3"><img src="{{ asset('images/gift_card.png') }}"></span>
                        <h3 class="mb-0">{{trans('lang.gift_card_plural')}}</h3>
                        <span class="counter ml-3 gift_count"></span>
                    </div>
                    <div class="d-flex top-title-right align-self-center">
                        <div class="select-box pl-3">
                        </div>
                    </div>
                </div>
            </div>
        </div>
       </div>
       <div class="table-list">
       <div class="row">
           <div class="col-12">
               <div class="card border">
                 <div class="card-header d-flex justify-content-between align-items-center border-0">
                   <div class="card-header-title">
                    <h3 class="text-dark-2 mb-2 h4">{{trans('lang.gift_card_table')}}</h3>
                    <p class="mb-0 text-dark-2">{{trans('lang.gift_card_table_text')}}</p>
                   </div>
                   <div class="card-header-right d-flex align-items-center">
                     <div class="card-header-btn mr-3">
                        <a class="btn-primary btn rounded-full" href="{!! route('gift-card.save') !!}"><i class="mdi mdi-plus mr-2"></i>{{trans('lang.create_gift_card')}}</a>
                     </div>
                   </div>
                 </div>
                 <div class="card-body">
                         <div class="table-responsive m-t-10">
                            <table id="giftCardTable" class="display nowrap table table-hover table-striped table-bordered table table-striped" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <?php if (in_array('gift-card.delete', json_decode(@session('user_permissions'),true))) { ?>
                                            <th class="delete-all">
                                                <input type="checkbox" id="is_active">
                                                <label class="col-3 control-label" for="is_active">
                                                    <a id="deleteAll" class="do_not_delete" href="javascript:void(0)"><i class="mdi mdi-delete"></i> {{trans('lang.all')}}</a>
                                                </label>
                                            </th>
                                        <?php } ?>
                                        <th>{{trans('lang.title')}}</th>
                                        <th>{{trans('lang.expires_in')}}</th>
                                        <th>{{trans('lang.status')}}</th>
                                        <th>{{trans('lang.actions')}}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script type="text/javascript">
    var user_permissions = '<?php echo @session("user_permissions")?>';
    user_permissions = Object.values(JSON.parse(user_permissions));
    var checkDeletePermission = ($.inArray('gift-card.delete', user_permissions) >= 0);

    $(document).ready(function(){
        const table = $('#giftCardTable').DataTable({
            pageLength: 10,
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: function (data, callback) {
                const params = { start: data.start, length: data.length, draw: data.draw, search: data.search.value };
                $.get('{{ route('gift-card.data') }}', params, function(json){
                    $('.gift_count').text(json.recordsTotal || 0);
                    callback(json);
                });
            },
            order: (checkDeletePermission) ? [1, 'asc'] : [0,'asc'],
            columnDefs: [ { orderable: false, targets: (checkDeletePermission) ? [0, 3, 4] : [2, 3] } ],
            language: { zeroRecords: "{{trans('lang.no_record_found')}}", emptyTable: "{{trans('lang.no_record_found')}}", processing: "" }
        });

        // Toggle enable/disable
        $('#giftCardTable').on('change', '.toggle-enable', function(){
            var id = $(this).data('id');
            var isEnable = $(this).is(':checked');
            var $cb = $(this);
            $cb.prop('disabled', true);
            $.post({ url: '{{ url('gift-card') }}' + '/' + id + '/toggle', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, data: { isEnable: isEnable } })
                .done(function(resp){ $cb.prop('checked', !!resp.isEnable); })
                .fail(function(xhr){ $cb.prop('checked', !isEnable); alert('Failed to update ('+xhr.status+'): '+xhr.statusText); })
                .always(function(){ $cb.prop('disabled', false); });
        });

        // Single delete
        $('#giftCardTable').on('click', '.delete-gift', function(){
            var id = $(this).data('id');
            if(!confirm("{{trans('lang.selected_delete_alert')}}")) return;
            $.post({ url: '{{ url('gift-card') }}' + '/' + id + '/delete', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .done(function(){ $('#giftCardTable').DataTable().ajax.reload(null,false); })
                .fail(function(xhr){ alert('Failed to delete ('+xhr.status+'): '+xhr.statusText); });
        });

        // Select all
        $(document).on('click','#is_active', function(){
            $("#giftCardTable .is_open").prop('checked', $(this).prop('checked'));
        });

        // Bulk delete
        $(document).on('click','#deleteAll', function(){
            var ids = [];
            $('#giftCardTable .is_open:checked').each(function(){ ids.push($(this).attr('dataId')); });
            if(ids.length===0){ alert("{{trans('lang.select_delete_alert')}}"); return; }
            if(!confirm("{{trans('lang.selected_delete_alert')}}")) return;
            $.post({ url: '{{ route('gift-card.bulkDelete') }}', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, data: { ids: ids } })
                .done(function(){ $('#giftCardTable').DataTable().ajax.reload(null,false); })
                .fail(function(xhr){ alert('Failed to delete ('+xhr.status+'): '+xhr.statusText); });
        });
    });
</script>
@endsection
