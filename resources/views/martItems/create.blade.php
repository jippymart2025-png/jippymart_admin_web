@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center">
                <h3 class="text-themecolor">Mart Items</h3>
        </div>
        <div class="col-md-7 align-self-center">
            <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ trans('lang.dashboard') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('mart-items') }}">Mart Items</a></li>
                    <li class="breadcrumb-item active">Create</li>
            </ol>
        </div>
    </div>

        <div class="container-fluid">
            <div class="card border">
        <div class="card-body">
                    <h4 class="card-title mb-4">New Mart Item</h4>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                                        </div>
                    @endif

                    <form method="POST" action="{{ route('mart-items.store') }}" enctype="multipart/form-data">
                        @csrf

                        @if ($restaurantId)
                            <input type="hidden" name="vendorID" value="{{ $restaurantId }}">
                            <div class="alert alert-info">
                                <strong>Mart:</strong>
                                {{ optional($vendors->firstWhere('id', $restaurantId))->title ?? 'Selected mart' }}
                            </div>
                        @else
                            <div class="form-group">
                                <label for="vendorID">Mart <span class="text-danger">*</span></label>
                                <select class="form-control" name="vendorID" id="vendorID" required>
                                    <option value="">Select Mart</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ old('vendorID') == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->title }}
                                        </option>
                                    @endforeach
                                    </select>
                                    </div>
                        @endif

                        <div class="form-group">
                            <label for="name">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="price">Price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="{{ old('price') }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="disPrice">Discount Price</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="disPrice" name="disPrice" value="{{ old('disPrice') }}">
                        </div>
                            </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="categoryID">Category <span class="text-danger">*</span></label>
                                <select class="form-control" id="categoryID" name="categoryID" required>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" data-section="{{ $category->section }}" {{ old('categoryID') == $category->id ? 'selected' : '' }}>
                                            {{ $category->title }}
                                        </option>
                                    @endforeach
                                </select>
                </div>
                            <div class="form-group col-md-6">
                                <label for="subcategoryID">Subcategory</label>
                                <select class="form-control" id="subcategoryID" name="subcategoryID">
                                    <option value="">Select Subcategory</option>
                                    @foreach($subcategories as $subcategory)
                                        <option value="{{ $subcategory->id }}"
                                                data-parent="{{ $subcategory->parent_category_id }}"
                                                data-section="{{ $subcategory->section }}"
                                                {{ old('subcategoryID') == $subcategory->id ? 'selected' : '' }}>
                                            {{ $subcategory->title }}
                                        </option>
                                    @endforeach
                    </select>
                </div>
            </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="brandID">Brand</label>
                                <select class="form-control" id="brandID" name="brandID">
                                    <option value="">Select Brand</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ old('brandID') == $brand->id ? 'selected' : '' }}>
                                            {{ $brand->name }}
                                        </option>
                                    @endforeach
                    </select>
                </div>
                            <div class="form-group col-md-6">
                                <label for="section">Section</label>
                                <input type="text" class="form-control" id="section" name="section" value="{{ old('section', 'General') }}" readonly>
            </div>
        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="quantity">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="-1" value="{{ old('quantity', -1) }}">
                                <small class="form-text text-muted">Use -1 for unlimited stock.</small>
                </div>
                            <div class="form-group col-md-6">
                                <label for="photo">Item Image</label>
                                <input type="file" class="form-control-file" id="photo" name="photo" accept="image/*">
            </div>
        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="calories">Calories</label>
                                <input type="number" class="form-control" id="calories" name="calories" min="0" value="{{ old('calories') }}">
                </div>
                            <div class="form-group col-md-3">
                                <label for="grams">Grams</label>
                                <input type="number" class="form-control" id="grams" name="grams" min="0" value="{{ old('grams') }}">
            </div>
                            <div class="form-group col-md-3">
                                <label for="proteins">Proteins</label>
                                <input type="number" class="form-control" id="proteins" name="proteins" min="0" value="{{ old('proteins') }}">
                </div>
                            <div class="form-group col-md-3">
                                <label for="fats">Fats</label>
                                <input type="number" class="form-control" id="fats" name="fats" min="0" value="{{ old('fats') }}">
            </div>
        </div>

                <div class="form-group">
                            <label for="description">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
            </div>

                <div class="form-group">
                            <label>Item Features</label>
        <div class="row">
                                @php
                                    $featureFields = [
                                        'isSpotlight' => 'Spotlight',
                                        'isStealOfMoment' => 'Steal of Moment',
                                        'isFeature' => 'Featured',
                                        'isTrending' => 'Trending',
                                        'isNew' => 'New Arrival',
                                        'isBestSeller' => 'Best Seller',
                                        'isSeasonal' => 'Seasonal',
                                    ];
                                @endphp
                                @foreach($featureFields as $field => $label)
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="{{ $field }}" name="{{ $field }}" value="1" {{ old($field) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                </div>
            </div>
                                @endforeach
            </div>
        </div>

                <div class="form-group">
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" id="publish" name="publish" value="1" {{ old('publish', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="publish">Publish</label>
                </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" id="isAvailable" name="isAvailable" value="1" {{ old('isAvailable', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isAvailable">Available</label>
            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" id="nonveg" name="nonveg" value="1" {{ old('nonveg') ? 'checked' : '' }}>
                                <label class="form-check-label" for="nonveg">Non Veg</label>
            </div>
        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> Save Item
                            </button>
                            <a href="{{ route('mart-items') }}" class="btn btn-secondary">Cancel</a>
                </div>
                    </form>
            </div>
                    </div>
                </div>
            </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const categorySelect = document.getElementById('categoryID');
            const subcategorySelect = document.getElementById('subcategoryID');
            const sectionInput = document.getElementById('section');
            const subcategories = @json($subcategories);

            function filterSubcategories(categoryId) {
                const current = subcategorySelect.value;
                subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

                subcategories.forEach(sub => {
                    if (!categoryId || String(sub.parent_category_id) === String(categoryId)) {
                        const option = document.createElement('option');
                        option.value = sub.id;
                        option.dataset.section = sub.section || '';
                        option.textContent = sub.title;
                        if (current && String(current) === String(sub.id)) {
                            option.selected = true;
                        }
                        subcategorySelect.appendChild(option);
                    }
                });
            }

            function updateSectionFromSelection() {
                const subOption = subcategorySelect.options[subcategorySelect.selectedIndex];
                if (subOption && subOption.dataset.section) {
                    sectionInput.value = subOption.dataset.section;
                    return;
                }

                const catOption = categorySelect.options[categorySelect.selectedIndex];
                if (catOption && catOption.dataset.section) {
                    sectionInput.value = catOption.dataset.section;
                } else {
                    sectionInput.value = 'General';
                }
            }

            categorySelect.addEventListener('change', function () {
                filterSubcategories(this.value);
                updateSectionFromSelection();
            });

            subcategorySelect.addEventListener('change', updateSectionFromSelection);

            // Initialise
            filterSubcategories(categorySelect.value);
            updateSectionFromSelection();
        })();
    </script>
@endsection
