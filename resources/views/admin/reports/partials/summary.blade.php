{{--
    The four numbers at the top, swapped whole by reports.js when a filter
    changes.

    Four, not fourteen. Every tile here was chosen because somebody would act on
    it; a report page dense enough to need scanning is one nobody reads. The
    figures arrive already formatted from ReportService — no arithmetic in this
    file, and none in the browser either.
--}}

<div class="row g-5 mb-5">
    @foreach ($summary as $stat)
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center py-5">
                    <span class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-{{ $stat['tone'] }}">
                            <i class="ki-outline ki-chart-simple fs-2 text-{{ $stat['tone'] }}"></i>
                        </span>
                    </span>
                    <div class="min-w-0">
                        <div class="fs-3 fw-bold text-gray-900 lh-1 text-nowrap">{{ $stat['value'] }}</div>
                        <div class="fs-7 text-muted">{{ $stat['label'] }}</div>
                        @if (!empty($stat['hint']))
                            <div class="fs-8 text-gray-500">{{ $stat['hint'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
