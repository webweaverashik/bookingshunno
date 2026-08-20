@extends('layouts.app')

@section('title', 'Maintenance')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">
            Maintenance
        </h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Administration</li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Maintenance</li>
        </ul>
    </div>
@endsection

@section('content')
    {{--
        Artisan from the browser, because the live server has no shell.

        Nine buttons, not a terminal. The list of commands lives in
        App\Enums\System\MaintenanceTask and cannot be added to from here — the
        long note at the top of that file explains why a text box would have
        been the wrong answer to the same problem.
    --}}

    <div class="app-container container-xxl">

        <div class="card mb-6">
            <div class="card-body py-5">
                <div class="d-flex align-items-start">
                    <i class="ki-outline ki-information-5 fs-2x text-primary me-4 mt-1"></i>
                    <div>
                        <div class="fw-bold text-gray-900 mb-1">This runs commands on the live server</div>
                        <div class="fs-7 text-gray-700">
                            The hosting has no shell, so this page stands in for one. Anything that changes
                            data asks for your password first, and every run is written to the log with your
                            name against it.
                            <br>
                            After uploading a release the usual order is
                            <strong>Check migration status</strong>, then <strong>Run pending
                                migrations</strong>, then <strong>Clear all caches</strong>.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5">
            @foreach ($tasks as $task)
                <div class="col-md-6 col-xl-4">
                    {{-- Unavailable cards are rendered, not hidden. A tool that
                         vanishes between machines sends somebody hunting for a
                         feature that seems to have disappeared; a greyed one
                         says it exists, where it works, and why it does not
                         work here. The button being disabled is presentation —
                         the controller refuses the request either way. --}}
                    <div @class([
                        'card h-100',
                        'border border-danger border-dashed' => $task->isDestructive() && $task->isAvailable(),
                        'bg-light-secondary' => !$task->isAvailable(),
                    ])>
                        <div class="card-body d-flex flex-column {{ $task->isAvailable() ? '' : 'opacity-75' }}">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                <span class="fw-bold text-gray-900 fs-5">{{ $task->label() }}</span>

                                @if ($task->isReadOnly())
                                    {{-- Marked so somebody working out what is wrong knows which
                                         buttons they can press freely. Making a status check look
                                         identical to a migration discourages exactly the diagnosis
                                         that avoids running a migration blind. --}}
                                    <span class="badge badge-light-success fs-8">Read only</span>
                                @endif

                                @if ($task->requiresLocal())
                                    <span class="badge badge-light-warning fs-8">Local only</span>
                                @endif

                                @if ($task->isDestructive())
                                    <span class="badge badge-danger fs-8">Destroys data</span>
                                @endif
                            </div>

                            <p class="fs-7 text-gray-700 flex-grow-1">{{ $task->description() }}</p>

                            @if ($reason = $task->unavailableReason())
                                <div class="d-flex align-items-start bg-light-warning rounded p-3 mb-4">
                                    <i class="ki-outline ki-lock-2 fs-4 text-warning me-2 mt-1"></i>
                                    <span class="fs-8 text-gray-700">{{ $reason }}</span>
                                </div>
                            @endif

                            <button type="button"
                                @class([
                                    'btn btn-sm align-self-start',
                                    'btn-light-primary' => $task->isReadOnly() && $task->isAvailable(),
                                    'btn-light-danger' => !$task->isReadOnly() && !$task->isDestructive() && $task->isAvailable(),
                                    // Solid, not tinted. The one button here that
                                    // cannot be undone should not look like the six
                                    // that can.
                                    'btn-danger' => $task->isDestructive() && $task->isAvailable(),
                                    'btn-light' => !$task->isAvailable(),
                                ])
                                @disabled(!$task->isAvailable())
                                data-maintenance-run="{{ $task->value }}" data-label="{{ $task->label() }}"
                                data-readonly="{{ $task->isReadOnly() ? '1' : '0' }}"
                                data-destructive="{{ $task->isDestructive() ? '1' : '0' }}"
                                data-timeout="{{ $task->timeout() }}">
                                <span class="indicator-label">
                                    {{ $task->isAvailable() ? 'Run' : 'Unavailable here' }}
                                </span>
                                <span class="indicator-progress">
                                    Running…
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Output. Kept on the page rather than in a dialog that has to be
             dismissed: migrate:status is a table somebody reads next to the
             buttons, and a modal would make them close it to press the next
             one. --}}
        <div class="card mt-6" id="maintenance-output-card" hidden>
            <div class="card-header">
                <div class="card-title">
                    <span class="fw-bold" id="maintenance-output-title">Output</span>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-light" id="maintenance-output-clear">Clear</button>
                </div>
            </div>
            <div class="card-body pt-0">
                {{-- Monospace and pre-wrapped: migrate:status is a table and
                     collapsing its whitespace makes it unreadable. --}}
                <pre class="bg-light rounded p-4 mb-0 fs-8" id="maintenance-output"
                    style="white-space: pre-wrap; word-break: break-word; max-height: 420px; overflow-y: auto;"></pre>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    {{-- Password confirmation for anything that writes. A stolen session is not
         a migration. --}}
    <div class="modal fade" id="maintenance-confirm-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-450px">
            <div class="modal-content">
                <form id="maintenance-confirm-form" action="{{ route('admin.maintenance.run') }}" novalidate>
                    @csrf
                    <input type="hidden" name="task" value="">

                    <div class="modal-header">
                        <h3 class="modal-title">Confirm it is you</h3>
                        <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                            <i class="ki-outline ki-cross fs-1"></i>
                        </div>
                    </div>

                    <div class="modal-body">
                        <p class="fs-7 text-gray-700">
                            About to run <strong id="maintenance-confirm-label"></strong> on the live server.
                        </p>

                        <label class="required form-label">Your password</label>
                        <input type="password" name="password" class="form-control form-control-solid border"
                            autocomplete="current-password" />
                        <div class="invalid-feedback d-block" data-error-for="password"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="maintenance-confirm-save">
                            <span class="indicator-label">Run it</span>
                            <span class="indicator-progress">
                                Running…
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('page-js')
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/maintenance.js') }}"></script>
@endpush
