{{--
    System health. Admin only.

    THE SELECTION CRITERION IS SILENT FAILURE. Everything checked here breaks
    without telling anybody — a broken landing page is reported within the hour
    by whoever sees it; a stopped queue worker is reported by nobody, and the
    first symptom is a visitor three days later saying they never got their
    payment link.

    Each row links to the screen that fixes it. A warning with no route to the
    remedy is just an unpleasant fact.
--}}

<div class="card h-100">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">System</h3>
            <span class="text-muted fs-7 mt-1">The things that fail quietly</span>
        </div>
    </div>

    <div class="card-body pt-0">
        @foreach ($health as $check)
            @php($tone = ['ok' => 'success', 'warn' => 'warning', 'bad' => 'danger'][$check['state']])
            @php($icon = ['ok' => 'check-circle', 'warn' => 'information-5', 'bad' => 'shield-cross'][$check['state']])

            <div class="d-flex align-items-start gap-3 py-3 @if (!$loop->first) border-top @endif">
                <i class="ki-outline ki-{{ $icon }} fs-2 text-{{ $tone }} mt-1"></i>

                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-gray-800">{{ $check['label'] }}</div>
                    <div class="fs-8 text-muted">{{ $check['detail'] }}</div>
                </div>

                @if ($check['url'] && $check['state'] !== 'ok')
                    <a href="{{ $check['url'] }}" class="btn btn-sm btn-light-{{ $tone }} flex-shrink-0">Fix</a>
                @elseif ($check['url'])
                    <a href="{{ $check['url'] }}" class="btn btn-sm btn-light flex-shrink-0">View</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
