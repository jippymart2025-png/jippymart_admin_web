@extends('layouts.app')
@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">{{trans('lang.reviewattribute_plural')}}</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{url('/dashboard')}}">{{trans('lang.dashboard')}}</a></li>
                <li class="breadcrumb-item active">{{trans('lang.reviewattribute_table')}}</li>
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
                        <span class="icon mr-3"><img src="{{ asset('images/attribute.png') }}"></span>
                        <h3 class="mb-0">{{trans('lang.reviewattribute_plural')}}</h3>
                        <span class="counter ml-3 attribute_count"></span>
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
                    <h3 class="text-dark-2 mb-2 h4">{{trans('lang.reviewattribute_table')}}</h3>
                    <p class="mb-0 text-dark-2">{{trans('lang.attribute_table_text')}}</p>
                   </div>
                   <div class="card-header-right d-flex align-items-center">
                    <div class="card-header-btn mr-3"> 
                        <a class="btn-primary btn rounded-full" href="{!! route('reviewattributes.create') !!}"><i class="mdi mdi-plus mr-2"></i>{{trans('lang.reviewattribute_create')}}</a>
                     </div>
                   </div>                
                 </div>
                 <div class="card-body">
                         <div class="table-responsive m-t-10">
                            <table id="attributeTable" class="display nowrap table table-hover table-striped table-bordered table table-striped" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th>{{trans('lang.reviewattribute_name')}}</th>
                                        <th>{{trans('lang.actions')}}</th>
                                    </tr>
                                </thead>
                                <tbody id="append_list1">
                                </tbody>
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
    // SQL mode - no Firebase
    var user_permissions = '<?php echo @session("user_permissions") ?>';
    user_permissions = Object.values(JSON.parse(user_permissions));
    var checkDeletePermission = false;
    if ($.inArray('reviewattributes.delete', user_permissions) >= 0) {
        checkDeletePermission = true;
    }
    
    $(document).ready(function () {
        console.log('Loading review attributes from SQL database...');
        jQuery("#data-table_processing").show();
        
        const table = $('#attributeTable').DataTable({
            pageLength: 10,
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('reviewattributes.data') }}",
                type: 'GET',
                data: function (d) {
                    console.log('DataTables request:', d);
                },
                dataSrc: function(json) {
                    console.log('DataTables response:', json);
                    $('.attribute_count').text(json.recordsTotal);
                    return json.data;
                },
                error: function(xhr, error, code) {
                    console.error('DataTables error:', error, code);
                    console.error('Response:', xhr.responseText);
                    $('#data-table_processing').hide();
                }
            },
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [1] },
            ],
            language: {
                "zeroRecords": "{{trans("lang.no_record_found")}}",
                "emptyTable": "{{trans("lang.no_record_found")}}",
                "processing": ""
            },
            drawCallback: function() {
                $('#data-table_processing').hide();
            }
        });
        
        table.columns.adjust().draw();
        
        function debounce(func, wait) {
            let timeout;
            const context = this;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }
        
        $('#search-input').on('input', debounce(function () {
            const searchValue = $(this).val();
            if (searchValue.length >= 3 || searchValue.length === 0) {
                $('#data-table_processing').show();
                table.search(searchValue).draw();
            }
        }, 300));
    });
    
    // Delete review attribute - SQL version
    $(document).on("click", "a[name='reviewattributes-delete']", function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        
        if (!confirm('Are you sure you want to delete this review attribute?')) {
            return;
        }
        
        jQuery("#data-table_processing").show();
        
        $.ajax({
            url: "{{ url('reviewattributes') }}/" + id,
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    console.log('Review attribute deleted successfully');
                    window.location.href = '{{ route("reviewattributes")}}';
                } else {
                    alert('Failed to delete review attribute');
                    jQuery("#data-table_processing").hide();
                }
            },
            error: function(xhr) {
                console.error('Delete error:', xhr.responseText);
                alert('Error deleting review attribute');
                jQuery("#data-table_processing").hide();
            }
        });
    });
</script>
@endsection