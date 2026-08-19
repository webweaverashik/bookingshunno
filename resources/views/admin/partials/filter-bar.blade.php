{{--
    The filter menu, shared by every index page.

    Driven by a field spec rather than written out per page: eight registers
    each carrying their own copy of this markup would be eight places to fix the
    next thing wrong with it, and they would drift within a month.

    USAGE

        @include('admin.partials.filter-bar', [
            'id'        => 'reservations-filter',
            'exportUrl' => route(...),      // optional
            'fields'    => [ ... ],
        ])

    A FIELD IS

        key          the query parameter it produces
        label        shown above the control
        type         'select' (default) or 'date'
        value        what is currently chosen, from the controller's filters
        default      the value that means "not filtering on this". A field
                     sitting at its default is left out of the URL and does not
                     count towards the badge.
        options      [value => label], for a select
        placeholder  shown when nothing is chosen
        when         optional "otherKey:value" — this field is hidden unless the
                     other field holds that value
        width        Bootstrap column class, default col-12

    Everything here is markup. The values are read, the badge is recounted and
    the request is made by public/js/admin/filters.js, which the page must load
    before the register script that calls Shunno.filterBar().

    NO .card-toolbar WRAPPER, deliberately. The page supplies one and puts its
    own buttons — Export, "New workshop", "Gift voucher" — inside it beside
    this. A card header is a flex row with space-between, so two toolbars mean
    two groups pushed to opposite ends and the Filter button marooned in the
    middle of the table. One toolbar keeps every control in a single
    right-aligned cluster:

        <div class="card-toolbar">
            @include('admin.partials.filter-bar', [...])

            @can('vouchers.create')
                <button type="button" class="btn btn-primary" id="voucher-create">…</button>
            @endcan
        </div>
--}}

@php
    $fields = $fields ?? [];
    $exportUrl = $exportUrl ?? null;

    // Counted server-side so the badge is right on the first paint, before any
    // script has run. filters.js recounts it from then on.
    $active = collect($fields)
        ->reject(fn($field) => (string) ($field['value'] ?? '') === (string) ($field['default'] ?? ''))
        ->count();
@endphp

{{-- ================= Filter ================= --}}
<button type="button" class="btn btn-light-primary me-3 position-relative" data-kt-menu-trigger="click"
    data-kt-menu-placement="bottom-end">
    <i class="ki-outline ki-filter fs-2"></i>Filter
    {{-- How many things are narrowing this list. Worth the pixels: with
         "any date, every status" as the default, a filtered view and a full
         one otherwise look identical until you read the rows. --}}
    <span class="badge badge-circle badge-primary position-absolute top-0 start-100 translate-middle"
        data-filter="count" @if (!$active) hidden @endif>{{ $active }}</span>
</button>

<div class="menu menu-sub menu-sub-dropdown w-300px w-md-350px" data-kt-menu="true" id="{{ $id }}-menu">
    <div class="px-7 py-5">
        <div class="fs-5 text-gray-900 fw-bold">Filter options</div>
    </div>

    <div class="separator border-gray-200"></div>

    <div class="px-7 py-5" id="{{ $id }}" data-filter="form">
        <div class="row">
            @foreach ($fields as $field)
                <div class="{{ $field['width'] ?? 'col-12' }} mb-5"
                    @isset($field['when']) data-filter-when="{{ $field['when'] }}" @endisset>

                    <label class="form-label fs-6 fw-semibold">{{ $field['label'] }}:</label>

                    @if (($field['type'] ?? 'select') === 'date')
                        {{-- shunno-filter-date, NOT shunno-datepicker. The
                             house scan on DOM ready would claim these first
                             and give them a calendar rendered on <body> —
                             outside the menu, where the first click on a
                             date registers as a click outside the KTMenu and
                             closes it mid-selection. filters.js initialises
                             these with static: true instead. --}}
                        <input type="text" class="form-control form-control-solid fw-bold shunno-filter-date"
                            data-filter-field="{{ $field['key'] }}"
                            data-filter-default="{{ $field['default'] ?? '' }}"
                            value="{{ $field['value'] ?? '' }}"
                            placeholder="{{ $field['placeholder'] ?? 'Any date' }}">
                    @else
                        {{-- data-kt-select2, NOT data-control="select2".
                             Metronic auto-initialises the latter on page
                             load; these are initialised by filters.js with
                             the menu's own placement, and a field
                             initialised twice gets two dropdowns stacked on
                             each other.

                             Select2 is safe in this menu specifically
                             because nothing listens for its change event —
                             the Apply button reads the values. That is what
                             the standing "plain form-select for short
                             filters" rule was working around.

                             data-dropdown-parent points at THIS MENU and is
                             not optional. Select2 appends its dropdown to
                             <body> by default; KTMenu closes on any click
                             outside its own DOM; so choosing an option
                             would shut the menu underneath the person
                             choosing it. --}}
                        <select class="form-select form-select-solid fw-bold"
                            data-filter-field="{{ $field['key'] }}"
                            data-filter-default="{{ $field['default'] ?? '' }}" data-kt-select2="true"
                            data-placeholder="{{ $field['placeholder'] ?? 'Select' }}"
                            data-allow-clear="{{ ($field['default'] ?? '') === '' ? 'true' : 'false' }}"
                            data-hide-search="{{ count($field['options'] ?? []) > 8 ? 'false' : 'true' }}"
                            data-dropdown-parent="#{{ $id }}-menu">>
                            <option></option>
                            @foreach ($field['options'] ?? [] as $value => $label)
                                <option value="{{ $value }}"
                                    @selected((string) ($field['value'] ?? '') === (string) $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end">
            <button type="reset" class="btn btn-light btn-active-light-primary fw-semibold me-2 px-6"
                data-kt-menu-dismiss="true" data-filter="reset">Reset</button>
            <button type="button" class="btn btn-primary fw-semibold px-6" data-kt-menu-dismiss="true"
                data-filter="apply">Apply</button>
        </div>
    </div>
</div>

{{-- ================= Export =================

     Rendered only where the page passes an endpoint that honours the same
     filters the menu above sets. An export that quietly ignores two of them
     hands somebody a spreadsheet that is wrong in a way they cannot see,
     which is worse than no export button. --}}
@if ($exportUrl)
    <div class="dropdown d-inline-block me-3" data-filter="export" data-url="{{ $exportUrl }}">
        <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
            data-kt-menu-placement="bottom-end">
            <i class="ki-outline ki-exit-up fs-2"></i>Export
        </button>
        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4"
            data-kt-menu="true">
            <div class="menu-item px-3">
                <a href="#" class="menu-link px-3" data-row-export="xlsx">Export as Excel</a>
            </div>
            <div class="menu-item px-3">
                <a href="#" class="menu-link px-3" data-row-export="csv">Export as CSV</a>
            </div>
            <div class="menu-item px-3">
                <a href="#" class="menu-link px-3" data-row-export="pdf">Export as PDF</a>
            </div>
        </div>
    </div>
@endif
