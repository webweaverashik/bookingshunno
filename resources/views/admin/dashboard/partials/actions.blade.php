{{--
    The action strip.

    Every tile is a LINK, not a number. That is the point of the strip: a count
    with no way to act on it is a fact, and this row is meant to be a to-do
    list. Each one lands on the register already filtered to exactly the rows it
    counted, so the click answers "which ones?" without anybody setting a filter
    by hand.

    Zero counts are shown rather than hidden. A quiet "0 overdue" is worth
    reading — it is the difference between nothing to do and not having looked.
--}}

<div class="row g-5 mb-5">
    @foreach ($actions as $action)
        <div class="col-6 col-xl-3">
            <a href="{{ $action['url'] }}"
                class="card h-100 text-decoration-none {{ $action['count'] > 0 ? 'border border-' . $action['tone'] : '' }}">
                <div class="card-body d-flex align-items-center py-5">
                    <span class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-{{ $action['tone'] }}">
                            <i class="ki-outline ki-notification-status fs-2 text-{{ $action['tone'] }}"></i>
                        </span>
                    </span>

                    <div class="min-w-0">
                        <div
                            class="fs-2 fw-bold lh-1 {{ $action['count'] > 0 ? 'text-' . $action['tone'] : 'text-gray-500' }}">
                            {{ number_format($action['count']) }}
                        </div>
                        <div class="fs-7 fw-semibold text-gray-800">{{ $action['label'] }}</div>
                        <div class="fs-8 text-muted">{{ $action['hint'] }}</div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
