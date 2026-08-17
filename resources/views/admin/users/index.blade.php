@extends('layouts.app')

@section('title', 'Staff')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">Staff</h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Staff</li>
        </ul>
    </div>
@endsection

@section('content')

    <div class="row g-5 mb-5">
        @foreach ([
        ['Staff accounts', $stats['total'], 'primary', $stats['active'] . ' active'],
        ['Admins', $stats['admins'], 'success', 'Full access'],
        ['Managers', $stats['managers'], 'info', 'No approvals, no settings'],
        ['Deactivated', $stats['total'] - $stats['active'], 'warning', 'Cannot sign in'],
    ] as [$label, $value, $tone, $hint])
            <div class="col-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center py-5">
                        <span class="symbol symbol-45px me-4">
                            <span class="symbol-label bg-light-{{ $tone }}">
                                <i class="ki-outline ki-profile-user fs-2 text-{{ $tone }}"></i>
                            </span>
                        </span>
                        <div class="min-w-0">
                            <div class="fs-3 fw-bold text-gray-900 lh-1">{{ number_format($value) }}</div>
                            <div class="fs-7 text-muted">{{ $label }}</div>
                            <div class="fs-8 text-gray-500">{{ $hint }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5"></i>
                    <input type="text" id="users-search" class="form-control form-control-solid w-250px ps-13"
                        placeholder="Search name, email or phone" />
                </div>
            </div>

            <div class="card-toolbar gap-3">
                {{-- Plain form-selects: three and four fixed options with nothing
                     to search, and both drive change listeners. --}}
                <select id="users-role" class="form-select form-select-solid w-auto">
                    <option value="">Every role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>

                <select id="users-status" class="form-select form-select-solid w-auto">
                    <option value="">Active and not</option>
                    <option value="active">Active only</option>
                    <option value="inactive">Deactivated only</option>
                </select>

                @can('users.create')
                    <button type="button" class="btn btn-primary" id="user-create">
                        <i class="ki-outline ki-plus fs-2"></i>Add staff
                    </button>
                @endcan
            </div>
        </div>

        <div class="card-body pt-0">
            <div id="users-list">
                @include('admin.users.partials.list', ['users' => $users])
            </div>
        </div>
    </div>
@endsection

@push('modals')
    @include('admin.users.partials.form-modal')
@endpush

@push('page-js')
    <script>
        var UsersConfig = {
            listUrl: "{{ route('admin.users.list') }}",
            storeUrl: "{{ route('admin.users.store') }}",
            baseUrl: "{{ url('admin/users') }}"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/users.js') }}"></script>
@endpush
