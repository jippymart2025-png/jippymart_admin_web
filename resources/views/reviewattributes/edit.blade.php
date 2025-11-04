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
                <li class="breadcrumb-item"><a href="{!! route('reviewattributes') !!}">{{trans('lang.reviewattribute_plural')}}</a>
                </li>
                <li class="breadcrumb-item active">{{trans('lang.reviewattribute_edit')}}</li>
            </ol>
        </div>
    </div>
    <div class="card-body">
        <div class="error_top" style="display:none"></div>
        <div class="row restaurant_payout_create">
            <div class="restaurant_payout_create-inner">
                <fieldset>
                    <legend>{{trans('lang.reviewattribute_edit')}}</legend>
                    <div class="form-group row width-100">
                        <label class="col-3 control-label">{{trans('lang.reviewattribute_name')}}</label>
                        <div class="col-7">
                            <input type="text" class="form-control reviewattribute-name">
                            <div class="form-text text-muted">{{ trans("lang.reviewattribute_name_help") }}</div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    </div>
    <div class="form-group col-12 text-center btm-btn">
        <button type="button" class="btn btn-primary edit-form-btn"><i class="fa fa-save"></i>
            {{trans('lang.save')}}
        </button>
        <a href="{!! route('reviewattributes') !!}" class="btn btn-default"><i class="fa fa-undo"></i>{{trans('lang.cancel')}}</a>
    </div>
</div>
@endsection
@section('scripts')
<script>
    var id = "<?php echo $id;?>";
    
    $(document).ready(function () {
        console.log('Edit review attribute - SQL mode, ID:', id);
        jQuery("#data-table_processing").show();
        
        // Load review attribute data from SQL
        $.get("{{ route('reviewattributes.show.json', ':id') }}".replace(':id', id), function(reviewattribute) {
            console.log('Review attribute loaded:', reviewattribute);
            $(".reviewattribute-name").val(reviewattribute.title);
            jQuery("#data-table_processing").hide();
        }).fail(function(xhr) {
            console.error('Failed to load review attribute:', xhr.responseText);
            jQuery("#data-table_processing").hide();
            alert('Failed to load review attribute data');
        });
        
        $(".edit-form-btn").click(function () {
            var title = $(".reviewattribute-name").val();
            $(".error_top").hide();
            $(".error_top").html("");
            
            if (title == '') {
                $(".error_top").show();
                $(".error_top").append("<p>{{trans('lang.enter_reviewattribute_title_error')}}</p>");
                window.scrollTo(0, 0);
            } else {
                jQuery("#data-table_processing").show();
                
                $.ajax({
                    url: "{{ route('reviewattributes.update', ':id') }}".replace(':id', id),
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        title: title
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Review attribute updated successfully');
                            window.location.href = '{{ route("reviewattributes")}}';
                        } else {
                            alert('Failed to update review attribute');
                            jQuery("#data-table_processing").hide();
                        }
                    },
                    error: function(xhr) {
                        console.error('Update error:', xhr.responseText);
                        jQuery("#data-table_processing").hide();
                        $(".error_top").show();
                        $(".error_top").html("");
                        $(".error_top").append("<p>Error updating review attribute</p>");
                        window.scrollTo(0, 0);
                    }
                });
            }
        });
    });
</script>
@endsection