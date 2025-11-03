@extends('layouts.app')
@section('content')
<style>
.badge-danger {
    background-color: #dc3545;
    color: white;
    font-size: 0.75em;
    font-weight: bold;
    padding: 0.25em 0.5em;
    border-radius: 0.25rem;
}
.table-danger {
    background-color: #f8d7da !important;
}
</style>
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
            <h3 class="text-themecolor">Promotions</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{url('/dashboard')}}">Dashboard</a></li>
                <li class="breadcrumb-item active">Promotions</li>
            </ol>
        </div>
    </div>
    <div class="container-fluid">
        <div class="admin-top-section">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex top-title-section pb-4 justify-content-between">
                        <div class="d-flex top-title-left align-self-center">
                            <span class="icon mr-3"><img src="{{ asset('images/category.png') }}"></span>
                            <h3 class="mb-0">Promotions List</h3>
                            <span class="counter ml-3 promotion_count"></span>
                        </div>
                        <div class="d-flex top-title-right align-self-center">
                            <div class="select-box pl-3">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- <div class="row mb-4">
            <div class="col-12">
                <div class="card border">
                    <div class="card-header d-flex justify-content-between align-items-center border-0">
                        <div class="card-header-title">
                            <h3 class="text-dark-2 mb-2 h4">Bulk Import Promotions</h3>
                            <p class="mb-0 text-dark-2">Upload Excel file to import multiple promotions at once</p>
                        </div>
                        <div class="card-header-right d-flex align-items-center">
                            <div class="card-header-btn mr-3">
                                <a href="{{ route('promotions.download-template') }}" class="btn btn-outline-primary rounded-full">
                                    <i class="mdi mdi-download mr-2"></i>Download Template
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('promotions.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="importFile" class="control-label">Select Excel File (.xls/.xlsx)</label>
                                        <input type="file" name="file" id="importFile" accept=".xls,.xlsx" class="form-control" required>
                                        <div class="form-text text-muted">
                                            <i class="mdi mdi-information-outline mr-1"></i>
                                            File should contain: restaurant_id, product_id, special_price, extra_km_charge, free_delivery_km, start_time, end_time, payment_mode
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary rounded-full">
                                        <i class="mdi mdi-upload mr-2"></i>Import Promotions
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div> --}}

        <div class="table-list">
            <div class="row">
                <div class="col-12">
                    <div class="card border">
                        <div class="card-header d-flex justify-content-between align-items-center border-0">
                            <div class="card-header-title">
                                <h3 class="text-dark-2 mb-2 h4">Promotions Table</h3>
                                <p class="mb-0 text-dark-2">Manage all promotions and their details</p>
                            </div>
                            <div class="card-header-right d-flex align-items-center">
                                <div class="card-header-btn mr-3">
                                    <a href="{{ route('promotions.create') }}" class="btn-primary btn rounded-full">
                                        <i class="mdi mdi-plus mr-2"></i>Add Promotion
                                    </a>
                                </div>
                                <div class="card-header-btn mr-3">
                                    <select id="promotion_vtype_filter" class="form-control">
                                        <option value="">All Types</option>
                                        <option value="restaurant">Restaurant</option>
                                        <option value="mart">Mart</option>
                                    </select>
                                </div>
                                <div class="card-header-btn">
                                    <select id="promotion_zone_filter" class="form-control">
                                        <option value="">All Zones</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive m-t-10">
                                <table id="promotionsTable" class="display nowrap table table-hover table-striped table-bordered table table-striped" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th class="delete-all"><input type="checkbox" id="is_active"><label class="col-3 control-label" for="is_active"><a id="deleteAll" class="do_not_delete" href="javascript:void(0)"><i class="mdi mdi-delete"></i> All</a></label></th>
                                            <th>Type</th>
                                            <th>Zone</th>
                                            <th>Restaurant/Mart</th>
                                            <th>Product</th>
                                            <th>Special Price</th>
                                            <th>Item Limit</th>
                                            <th>Extra KM Charge</th>
                                            <th>Free Delivery KM</th>
                                            <th>Start Time</th>
                                            <th>End Time</th>
                                            <th>Payment Mode</th>
                                            <th>Available</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="promotion-table-body">
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
<div id="data-table_processing" class="data-table-processing" style="display: none">Processing...</div>
@endsection
@section('scripts')
<script>
var selectedVTypeFilter = '';
var selectedZoneFilter = '';

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    try {
        var date = new Date(dateStr);
        return date.toLocaleString();
    } catch (e) {
        return dateStr;
    }
}

function isExpired(endTime) {
    if (!endTime) return false;
    try {
        var endDate = new Date(endTime);
        var currentDate = new Date();
        return endDate < currentDate;
    } catch (e) {
        return false;
    }
}

function renderTable(promotions) {
    var tbody = '';
    var visibleCount = 0;
    promotions.forEach(function(promo) {
        var isExpiredPromo = promo.isExpired || false;
        var expiredText = isExpiredPromo ? '<br><span class="badge badge-danger">EXPIRED</span>' : '';
        var rowClass = isExpiredPromo ? 'table-danger' : '';
        
        var typeText = promo.vType ? (promo.vType.charAt(0).toUpperCase() + promo.vType.slice(1)) : '-';
        var zoneText = promo.zone_name || '-';
        
        tbody += '<tr class="' + rowClass + '">' +
            '<td class="delete-all"><input type="checkbox" id="is_open_' + promo.id + '" class="is_open" dataId="' + promo.id + '"><label class="col-3 control-label" for="is_open_' + promo.id + '"></label></td>' +
            '<td>' + typeText + '</td>' +
            '<td>' + zoneText + '</td>' +
            '<td>' + promo.restaurant_title + '</td>' +
            '<td>' + promo.product_title + '</td>' +
            '<td>₹' + promo.special_price + '</td>' +
            '<td>' + promo.item_limit + '</td>' +
            '<td>' + promo.extra_km_charge + '</td>' +
            '<td>' + promo.free_delivery_km + '</td>' +
            '<td>' + formatDateTime(promo.start_time) + '</td>' +
            '<td>' + formatDateTime(promo.end_time) + expiredText + '</td>' +
            '<td>' + promo.payment_mode + '</td>' +
            '<td>' + (promo.isAvailable ? '<label class="switch"><input type="checkbox" checked id="'+promo.id+'" name="isAvailable"><span class="slider round"></span></label>' : '<label class="switch"><input type="checkbox" id="'+promo.id+'" name="isAvailable"><span class="slider round"></span></label>') + '</td>' +
            '<td>' +
                '<span class="action-btn">' +
                    '<a href="'+editUrl(promo.id)+'"><i class="mdi mdi-lead-pencil" title="Edit"></i></a> ' +
                    '<a id="'+promo.id+'" name="promotion-delete" class="delete-btn" href="javascript:void(0)"><i class="mdi mdi-delete" title="Delete"></i></a>' +
                '</span>' +
            '</td>' +
            '</tr>';
        visibleCount++;
    });
    $('#promotion-table-body').html(tbody);
    $('.promotion_count').text(visibleCount);
}

function editUrl(id) {
    return '{{ route('promotions.edit', ['id' => 'PROMOID']) }}'.replace('PROMOID', id);
}

function loadPromotions() {
    jQuery('#data-table_processing').show();
    
    $.ajax({
        url: '{{ route('promotions.data') }}',
        method: 'GET',
        data: {
            vtype_filter: selectedVTypeFilter,
            zone_filter: selectedZoneFilter
        },
        success: function(response) {
            if (response.success) {
                renderTable(response.data);
                jQuery('#data-table_processing').hide();
                
                $('#promotionsTable').DataTable({
                    destroy: true,
                    pageLength: 10,
                    responsive: true,
                    searching: true,
                    ordering: true,
                    order: [[3, 'asc']],
                    columnDefs: [
                        { orderable: false, targets: [0, 12, 13] }
                    ],
                    "language": {
                        "zeroRecords": "No records found",
                        "emptyTable": "No records found",
                        "processing": ""
                    }
                });
            } else {
                jQuery('#data-table_processing').hide();
                alert('Error loading promotions: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            jQuery('#data-table_processing').hide();
            console.error('Error loading promotions:', error);
            alert('Error loading promotions');
        }
    });
}

$(document).ready(function() {
    loadPromotions();

    // Load zones for filter
    $.ajax({
        url: '{{ route('promotions.zones') }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                response.data.forEach(function(zone) {
                    $('#promotion_zone_filter').append('<option value="'+zone.id+'">'+zone.name+'</option>');
                });
            }
        }
    });

    $(document).on('change', '#promotion_vtype_filter', function() {
        selectedVTypeFilter = ($(this).val() || '').toString().toLowerCase();
        loadPromotions();
    });
    $(document).on('change', '#promotion_zone_filter', function() {
        selectedZoneFilter = ($(this).val() || '').toString();
        loadPromotions();
    });

    // Select all checkboxes
    $("#is_active").click(function () {
        $("#promotionsTable .is_open").prop('checked', $(this).prop('checked'));
    });

    // Delete selected
    $("#deleteAll").click(function () {
        if ($('#promotionsTable .is_open:checked').length) {
            if (confirm("Are you sure you want to delete selected promotions?")) {
                jQuery("#data-table_processing").show();
                var ids = [];
                $('#promotionsTable .is_open:checked').each(function () {
                    ids.push($(this).attr('dataId'));
                });
                
                $.ajax({
                    url: '{{ route('promotions.bulk-delete') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            loadPromotions();
                        } else {
                            alert('Error deleting promotions: ' + response.error);
                        }
                        jQuery("#data-table_processing").hide();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error deleting promotions:', error);
                        alert('Error deleting promotions');
                        jQuery("#data-table_processing").hide();
                    }
                });
            }
        } else {
            alert("Please select promotions to delete");
        }
    });

    // Single delete
    $(document).on("click", "a[name='promotion-delete']", function() {
        var id = this.id;
        if (confirm('Are you sure you want to delete this promotion?')) {
            jQuery('#data-table_processing').show();
            $.ajax({
                url: '{{ route('promotions.destroy', ['id' => 'PROMOTION_ID']) }}'.replace('PROMOTION_ID', id),
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        loadPromotions();
                    } else {
                        alert('Error deleting promotion: ' + response.error);
                    }
                    jQuery('#data-table_processing').hide();
                },
                error: function(xhr, status, error) {
                    console.error('Error deleting promotion:', error);
                    alert('Error deleting promotion');
                    jQuery('#data-table_processing').hide();
                }
            });
        }
    });

    // Toggle isAvailable
    $(document).on("click", "input[name='isAvailable']", function(e) {
        var checkbox = $(this);
        var ischeck = checkbox.is(':checked');
        var id = checkbox.attr('id');
        
        // Debug logging
        console.log('Toggle clicked - ID:', id, 'Checked:', ischeck, 'Type:', typeof ischeck);
        
        if (!id || id === '') {
            alert('Error: Promotion ID is missing');
            checkbox.prop('checked', !ischeck);
            return;
        }
        
        // Build URL manually to ensure it works
        var url = '{{ url("/promotions/toggle") }}/' + encodeURIComponent(id);
        console.log('Toggle URL:', url);
        console.log('Sending data:', {isAvailable: ischeck ? 1 : 0});
        
        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                isAvailable: ischeck ? 1 : 0  // Send as integer
            },
            success: function(response) {
                console.log('Toggle response:', response);
                if (response.success) {
                    console.log('✅ Successfully updated. Affected rows:', response.affected_rows);
                    if (response.affected_rows === 0) {
                        console.warn('⚠️ Warning: No rows were affected. Value might already be the same.');
                    }
                    // Reload the table to reflect changes
                    loadPromotions();
                } else {
                    alert('Error updating promotion availability: ' + (response.error || 'Unknown error'));
                    // Revert checkbox state
                    checkbox.prop('checked', !ischeck);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Error updating promotion availability:', error);
                console.error('Response:', xhr.responseText);
                alert('Error updating promotion availability: ' + error);
                // Revert checkbox state
                checkbox.prop('checked', !ischeck);
            }
        });
    });
});
</script>
@endsection
