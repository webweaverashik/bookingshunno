@extends('layouts.app')

@section('title', 'Workshops')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">
            Workshops
        </h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Catalogue</li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Workshops</li>
        </ul>
    </div>
@endsection

@section('content')
    <div class="card">

        <div class="card-header border-0 pt-6">
            <div class="card-title flex-column align-items-start">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" id="workshops-search" class="form-control form-control-solid w-250px ps-13"
                        placeholder="Search workshops" autocomplete="off" />
                </div>
                <span class="text-muted fs-7 mt-2" id="workshops-count">
                    {{ $workshops->where('is_active', true)->count() }} of {{ $workshops->count() }} visible on the website
                </span>
            </div>

            @can('create', App\Models\Workshop\Workshop::class)
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" data-workshop-create>
                        <i class="ki-outline ki-plus fs-2"></i>New workshop
                    </button>
                </div>
            @endcan
        </div>

        <div class="card-body pt-0">
            {{-- The public site orders by session length; this table follows the
                 admin's own sort_order so the menu can be arranged deliberately. --}}
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                    <thead>
                        <tr class="fw-bold text-muted text-uppercase fs-7">
                            <th class="min-w-250px">Workshop</th>
                            <th class="min-w-120px">Category</th>
                            <th class="min-w-100px text-end">Price</th>
                            <th class="min-w-100px text-end">Duration</th>
                            <th class="min-w-100px text-center">People</th>
                            <th class="min-w-100px text-center">Status</th>
                            <th class="min-w-70px text-center">Order</th>
                            <th class="min-w-120px text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="workshops-rows">
                        @include('admin.workshops.partials.rows', ['workshops' => $workshops])
                    </tbody>
                </table>

                <div id="workshops-no-match" class="text-center text-muted py-10" hidden>
                    No workshop matches that search.
                </div>
            </div>
        </div>
    </div>

    {{-- The modal serves both create and edit, so it is rendered for anyone
         holding either permission. Manager holds neither and gets a read-only
         table with a working search box. --}}
@endsection

@push('modals')
    @if (auth()->user()->canAny(['workshops.create', 'workshops.update']))
        @include('admin.workshops.partials.form-modal', ['categories' => $categories])
    @endif
@endpush

@push('page-js')
    <script>
        var WorkshopsConfig = {
            storeUrl: "{{ route('admin.workshops.store') }}",
            rowsUrl: "{{ route('admin.workshops.rows') }}"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/workshops.js') }}"></script>
@endpush
