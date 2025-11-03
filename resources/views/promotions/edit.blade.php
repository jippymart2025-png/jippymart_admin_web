    @extends('layouts.app')
@section('content')
    <div class="page-wrapper">
        <div class="row page-titles">
            <div class="col-md-5 align-self-center">
                <h3 class="text-themecolor">Edit Promotion</h3>
            </div>
            <div class="col-md-7 align-self-center">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{url('/dashboard')}}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{!! route('promotions') !!}">Promotions</a></li>
                    <li class="breadcrumb-item active">Edit Promotion</li>
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
                                        <legend>Edit Promotion</legend>
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
                                                    <input type="checkbox" class="form-check-input" id="promotion_is_available">
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
                            Update
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
var promotionId = '{{ $id ?? '' }}';
console.log('Promotion ID from controller:', '{{ $id ?? "NOT_SET" }}');

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

function populateRestaurants(selectedId, selectedVType, selectedZoneId) {
    console.log('Populating restaurants with selected ID:', selectedId);
    restaurantSelect.empty();
    restaurantSelect.append('<option value="">Select Restaurant</option>');
    $.ajax({
        url: '{{ route('promotions.vendors') }}',
        method: 'GET',
        data: {
            vType: selectedVType,
            zoneId: selectedZoneId
        },
        success: function(response) {
            if (response.success) {
                console.log('Found', response.data.length, 'restaurants');
                restaurantList = response.data;
                response.data.forEach(function(vendor) {
                    var selected = (selectedId && vendor.id === selectedId) ? 'selected' : '';
                    restaurantSelect.append('<option value="' + vendor.id + '" data-vtype="' + (vendor.vType || '') + '" data-zoneid="' + (vendor.zoneId || '') + '" ' + selected + '>' + vendor.title + '</option>');
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading restaurants:', error);
        }
    });
}

function populateProducts(restaurantId, selectedProductId) {
    console.log('Populating products for restaurant:', restaurantId, 'with selected product:', selectedProductId);
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
                console.log('Found', response.data.length, 'products');
                productList = response.data;
                if (response.data.length === 0) {
                    productSelect.append('<option value="">No products found</option>');
                } else {
                    response.data.forEach(function(product) {
                        var selected = (selectedProductId && product.id === selectedProductId) ? 'selected' : '';
                        productSelect.append('<option value="' + product.id + '" data-price="' + product.price + '" ' + selected + '>' + product.name + '</option>');
                    });
                    
                    if (selectedProductId) {
                        var selectedProduct = response.data.find(function(p) { return p.id === selectedProductId; });
                        if (selectedProduct && selectedProduct.price > 0) {
                            $('#actual_price_display').show().text('Actual price: ₹' + selectedProduct.price);
                        }
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading products:', error);
        }
    });
}

function formatDateTimeForInput(timestamp) {
    if (!timestamp) return '';
    
    try {
        // Remove quotes if present
        timestamp = timestamp.toString().replace(/"/g, '');
        
        let date = new Date(timestamp);
        
        console.log('Original timestamp:', timestamp);
        console.log('Parsed date:', date);
        
        // Format for datetime-local input (YYYY-MM-DDTHH:MM)
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        const formatted = `${year}-${month}-${day}T${hours}:${minutes}`;
        console.log('Formatted for input:', formatted);
        
        return formatted;
    } catch (e) {
        console.error('Error formatting date:', e);
        return timestamp;
    }
}

function loadPromotionData() {
    if (!promotionId) {
        console.log('No promotion ID provided');
        return;
    }
    console.log('Loading promotion data for ID:', promotionId);

    $.ajax({
        url: '{{ route('promotions.show', ['id' => 'PROMOTION_ID']) }}'.replace('PROMOTION_ID', promotionId),
        method: 'GET',
        success: function(response) {
            if (response.success) {
                var data = response.data;
                console.log('Promotion data loaded:', data);
                
                // Pre-fill fields
                if (data.vType) {
                    vtypeSelect.val((data.vType || '').toString().toLowerCase());
                }
                if (data.zoneId) {
                    zoneSelect.val(data.zoneId);
                }
                
                populateRestaurants(data.restaurant_id, (data.vType || '').toString().toLowerCase(), data.zoneId || '');
                
                setTimeout(function() {
                    populateProducts(data.restaurant_id, data.product_id);
                }, 500); // Wait for restaurant dropdown to populate
                
                $('#promotion_special_price').val(data.special_price || 0);
                $('#promotion_item_limit').val(data.item_limit || 2);
                $('#promotion_extra_km_charge').val(data.extra_km_charge || 7);
                $('#promotion_free_delivery_km').val(data.free_delivery_km || 3);
                $('#promotion_is_available').prop('checked', data.isAvailable ? true : false);
                
                if (data.start_time) {
                    $('#promotion_start_time').val(formatDateTimeForInput(data.start_time));
                }
                if (data.end_time) {
                    $('#promotion_end_time').val(formatDateTimeForInput(data.end_time));
                }
            } else {
                console.log('Promotion not found:', response.error);
                alert('Promotion not found');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading promotion data:', error);
            alert('Error loading promotion data');
        }
    });
}

$(document).ready(function () {
    console.log('Document ready, promotionId:', promotionId);
    
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
    
    populateZones('');

    if (promotionId) {
        loadPromotionData();
    } else {
        console.log('No promotion ID, just populating restaurants');
        populateRestaurants(null, '', '');
    }
    // Filters
    vtypeSelect.on('change', function() {
        var selectedVType = ($(this).val() || '').toString().toLowerCase();
        var zoneId = (zoneSelect.val() || '').toString();
        populateRestaurants(null, selectedVType, zoneId);
        productSelect.empty();
        productSelect.append('<option value="">Select Product</option>');
        $('#actual_price_display').hide();
    });
    zoneSelect.on('change', function() {
        var selectedVType = (vtypeSelect.val() || '').toString().toLowerCase();
        var zoneId = ($(this).val() || '').toString();
        populateRestaurants(null, selectedVType, zoneId);
        productSelect.empty();
        productSelect.append('<option value="">Select Product</option>');
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
        var isAvailable = $('#promotion_is_available').is(':checked');
        
        // Resolve vType and zone to save on document
        var selectedVendorOption = restaurantSelect.find('option:selected');
        var vType = (vtypeSelect.val() || selectedVendorOption.data('vtype') || '').toString().toLowerCase();
        var zoneId = (zoneSelect.val() || selectedVendorOption.data('zoneid') || '').toString();

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
            url: '{{ route('promotions.update', ['id' => 'PROMOTION_ID']) }}'.replace('PROMOTION_ID', promotionId),
            method: 'PUT',
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
                $('.error_top').show().html('<p>Error updating promotion: ' + error + '</p>');
                window.scrollTo(0, 0);
            }
        });
    });
});
</script>
@endsection
