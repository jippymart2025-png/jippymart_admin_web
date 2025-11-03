@extends('layouts.app')
@section('content')
    <div class="page-wrapper">
        <div class="row page-titles">
            <div class="col-md-5 align-self-center">
                <h3 class="text-themecolor">Create Promotion</h3>
            </div>
            <div class="col-md-7 align-self-center">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{url('/dashboard')}}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{!! route('promotions') !!}">Promotions</a></li>
                    <li class="breadcrumb-item active">Create Promotion</li>
                </ol>
            </div>
        </div>
        <div class="container-fluid">
            <div class="cat-edite-page max-width-box">
                <div class="card pb-4">
                    <div class="card-header">
                        <ul class="nav nav-tabs align-items-end card-header-tabs w-100">
                            <li role="presentation" class="nav-item">
                                <a href="#promotion_information" aria-controls="description" role="tab" data-toggle="tab"
                                   class="nav-link active">Promotion Information</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="error_top" style="display:none"></div>
                        <div class="row restaurant_payout_create" role="tabpanel">
                            <div class="restaurant_payout_create-inner tab-content">
                                <div role="tabpanel" class="tab-pane active" id="promotion_information">
                                    <fieldset>
                                        <legend>Create Promotion</legend>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Type</label>
                                            <div class="col-7">
                                                <select id="promotion_vtype" class="form-control">
                                                    <option value="">Select Type</option>
                                                    <option value="restaurant">Restaurant</option>
                                                    <option value="mart">Mart</option>
                                                </select>
                                                <div class="form-text text-muted">Choose whether this promotion is for a Restaurant or Mart.</div>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Zone</label>
                                            <div class="col-7">
                                                <select id="promotion_zone" class="form-control"></select>
                                                <div class="form-text text-muted">Filter vendors by zone.</div>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Restaurant / Mart</label>
                                            <div class="col-7">
                                                <select id="promotion_restaurant" class="form-control"></select>
                                                <div class="form-text text-muted">Select the restaurant/mart for this promotion.</div>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Product</label>
                                            <div class="col-7">
                                                <select id="promotion_product" class="form-control"></select>
                                                <div class="form-text text-muted">
                                                    Select the product for this promotion (filtered by restaurant).
                                                    <span id="actual_price_display" class="text-warning" style="display: none;"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Special Price</label>
                                            <div class="col-7">
                                                <input type="text" class="form-control" id="promotion_special_price" min="0" step="0.01">
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Item Limit</label>
                                            <div class="col-7">
                                                <input type="text" class="form-control" id="promotion_item_limit" min="1" value="2">
                                                <div class="form-text text-muted">Maximum number of items that can be ordered with this promotion. Default: 2</div>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Extra KM Charge</label>
                                            <div class="col-7">
                                                <input type="text" class="form-control" id="promotion_extra_km_charge" min="0" value="7">
                                                <div class="form-text text-muted">Additional charge per kilometer beyond free delivery distance. Default: 7</div>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Free Delivery KM</label>
                                            <div class="col-7">
                                                <input type="text" class="form-control" id="promotion_free_delivery_km" min="0" value="3">
                                                <div class="form-text text-muted">Distance in kilometers for free delivery. Default: 3</div>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Start Time</label>
                                            <div class="col-7">
                                                <input type="datetime-local" class="form-control" id="promotion_start_time">
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">End Time</label>
                                            <div class="col-7">
                                                <input type="datetime-local" class="form-control" id="promotion_end_time">
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <label class="col-3 control-label">Payment Mode</label>
                                            <div class="col-7">
                                                <input type="text" class="form-control" value="prepaid" id="promotion_payment_mode" readonly>
                                            </div>
                                        </div>
                                        <div class="form-group row width-50">
                                            <div class="col-7 offset-3">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="promotion_is_available" checked>
                                                    <label class="form-check-label" for="promotion_is_available">
                                                        Available
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-12 text-center btm-btn">
                        <button type="button" class="btn btn-primary save-promotion-btn"><i class="fa fa-save"></i>
                            Save
                        </button>
                        <a href="{!! route('promotions') !!}" class="btn btn-default"><i class="fa fa-undo"></i>Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script>
var restaurantSelect = $('#promotion_restaurant');
var productSelect = $('#promotion_product');
var vtypeSelect = $('#promotion_vtype');
var zoneSelect = $('#promotion_zone');
var restaurantList = [];
var productList = [];

function populateZones(selectedId) {
    zoneSelect.empty();
    zoneSelect.append('<option value="">All Zones</option>');
    $.ajax({
        url: '{{ route('promotions.zones') }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                response.data.forEach(function(zone) {
                    var selected = (selectedId && zone.id === selectedId) ? 'selected' : '';
                    zoneSelect.append('<option value="' + zone.id + '" ' + selected + '>' + zone.name + '</option>');
                });
            }
        }
    });
}

function populateRestaurants(selectedVType, selectedZoneId) {
    restaurantSelect.empty();
    restaurantSelect.append('<option value="">Select Restaurant / Mart</option>');
    $.ajax({
        url: '{{ route('promotions.vendors') }}',
        method: 'GET',
        data: {
            vType: selectedVType,
            zoneId: selectedZoneId
        },
        success: function(response) {
            if (response.success) {
                restaurantList = response.data;
                response.data.forEach(function(vendor) {
                    restaurantSelect.append('<option value="' + vendor.id + '" data-vtype="' + (vendor.vType || '') + '" data-zoneid="' + (vendor.zoneId || '') + '">' + vendor.title + '</option>');
                });
            }
        }
    });
}

function populateProducts(restaurantId) {
    productSelect.empty();
    productSelect.append('<option value="">Select Product</option>');
    $('#actual_price_display').hide();
    if (!restaurantId) return;
    
    var selectedOption = restaurantSelect.find('option:selected');
    var vendorType = (selectedOption.data('vtype') || vtypeSelect.val() || '').toString().toLowerCase();

    $.ajax({
        url: '{{ route('promotions.products') }}',
        method: 'GET',
        data: {
            vendor_id: restaurantId,
            vType: vendorType
        },
        success: function(response) {
            if (response.success) {
                productList = response.data;
                if (response.data.length === 0) {
                    productSelect.append('<option value="">No products found</option>');
                } else {
                    response.data.forEach(function(product) {
                        productSelect.append('<option value="' + product.id + '" data-price="' + product.price + '">' + product.name + '</option>');
                    });
                }
            }
        }
    });
}

$(document).ready(function () {
    populateZones('');
    populateRestaurants('', '');

    // Input validation for numeric fields
    $('#promotion_special_price, #promotion_item_limit, #promotion_extra_km_charge, #promotion_free_delivery_km').on('input', function() {
        var value = $(this).val();
        // Remove non-numeric characters except decimal point
        value = value.replace(/[^0-9.]/g, '');
        // Ensure only one decimal point
        var parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        $(this).val(value);
    });

    // Type and Zone filters
    vtypeSelect.on('change', function() {
        var vtype = ($(this).val() || '').toString().toLowerCase();
        var zoneId = zoneSelect.val() || '';
        populateRestaurants(vtype, zoneId);
        productSelect.empty();
        productSelect.append('<option value=\"\">Select Product</option>');
        $('#actual_price_display').hide();
    });
    zoneSelect.on('change', function() {
        var vtype = (vtypeSelect.val() || '').toString().toLowerCase();
        var zoneId = ($(this).val() || '').toString();
        populateRestaurants(vtype, zoneId);
        productSelect.empty();
        productSelect.append('<option value=\"\">Select Product</option>');
        $('#actual_price_display').hide();
    });

    restaurantSelect.on('change', function() {
        var restId = $(this).val();
        populateProducts(restId);
    });

    productSelect.on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var price = selectedOption.data('price');
        if (price && price > 0) {
            $('#actual_price_display').show().text('Actual price: ₹' + price);
        } else {
            $('#actual_price_display').hide();
        }
    });
    $('.save-promotion-btn').click(function () {
        var restaurant_id = restaurantSelect.val();
        var product_id = productSelect.val();
        var special_price = parseFloat($('#promotion_special_price').val()) || 0;
        var item_limit = parseInt($('#promotion_item_limit').val()) || 2;
        var extra_km_charge = parseFloat($('#promotion_extra_km_charge').val()) || 7;
        var free_delivery_km = parseFloat($('#promotion_free_delivery_km').val()) || 3;
        var start_time = $('#promotion_start_time').val();
        var end_time = $('#promotion_end_time').val();
        var payment_mode = 'prepaid';
        var vType = (vtypeSelect.val() || '').toString().toLowerCase();
        var zoneId = zoneSelect.val() || '';
        var isAvailable = $('#promotion_is_available').is(':checked');

        if (!restaurant_id || !product_id || !start_time || !end_time) {
            $('.error_top').show().html('<p>Please fill all required fields.</p>');
            window.scrollTo(0, 0);
            return;
        }

        // Get restaurant and product titles
        var restaurant_title = restaurantSelect.find('option:selected').text();
        var product_title = productSelect.find('option:selected').text();

        // Check if end time is expired
        var endDateTime = new Date(end_time);
        var currentDateTime = new Date();
        if (endDateTime < currentDateTime) {
            isAvailable = false; // Force isAvailable to false if expired
        }

        $('.error_top').hide();
        jQuery('#data-table_processing').show();

        $.ajax({
            url: '{{ route('promotions.store') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                restaurant_id: restaurant_id,
                restaurant_title: restaurant_title,
                product_id: product_id,
                product_title: product_title,
                vType: vType,
                zoneId: zoneId,
                special_price: special_price,
                item_limit: item_limit,
                extra_km_charge: extra_km_charge,
                free_delivery_km: free_delivery_km,
                start_time: start_time,
                end_time: end_time,
                payment_mode: payment_mode,
                isAvailable: isAvailable
            },
            success: function(response) {
                jQuery('#data-table_processing').hide();
                if (response.success) {
                    window.location.href = '{!! route('promotions') !!}';
                } else {
                    $('.error_top').show().html('<p>' + response.error + '</p>');
                    window.scrollTo(0, 0);
                }
            },
            error: function(xhr, status, error) {
                jQuery('#data-table_processing').hide();
                $('.error_top').show().html('<p>Error creating promotion: ' + error + '</p>');
                window.scrollTo(0, 0);
            }
        });
    });
});
</script>
@endsection
