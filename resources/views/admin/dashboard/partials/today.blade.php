{{--
    Today at the studio.

    Above every chart on this page, deliberately. Whoever opens the panel at
    four in the afternoon wants to know who is coming at five — a twelve-week
    trend is interesting, and this is the thing somebody acts on within the
    hour.

    Bookings still awaiting payment are shown alongside confirmed ones, badged.
    They are people who may walk in, and staff need to know which ones those are
    before the door opens rather than after.
--}}

<div class="card mb-5">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Today &mdash; {{ now()->format('l, j F') }}</h3>
            <span class="text-muted fs-7 mt-1">
                @if ($today->isEmpty())
                    Nothing booked.
                @else
                    {{ $today->count() }} {{ \Illuminate\Support\Str::plural('session', $today->count()) }},
                    {{ $today->sum('participants') }} {{ \Illuminate\Support\Str::plural('guest', $today->sum('participants')) }}
                    expected.
                @endif
            </span>
        </div>
        <div class="card-toolbar">
            {{-- range=today, not from/to. The register's filter resolver only
                 recognises the named ranges its own dropdown offers —
                 upcoming, today, past, all — and unknown keys are ignored
                 silently, which would give a link that claims to filter and
                 quietly does not. --}}
            <a href="{{ route('admin.reservations.index', ['range' => 'today']) }}"
                class="btn btn-sm btn-light-primary">Open in the register</a>
        </div>
    </div>

    <div class="card-body pt-0">
        @if ($today->isEmpty())
            <div class="text-center text-muted py-8">
                No sessions today. The next seven days are below.
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-4 mb-0">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                            <th class="min-w-80px">Time</th>
                            <th class="min-w-150px">Experience</th>
                            <th class="min-w-150px">Visitor</th>
                            <th class="text-center min-w-70px">Guests</th>
                            <th class="min-w-125px">Status</th>
                            <th class="text-end min-w-100px">Owing</th>
                        </tr>
                    </thead>

                    <tbody class="text-gray-700">
                        @foreach ($today as $reservation)
                            <tr>
                                <td class="fw-bold text-gray-900">
                                    {{ \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time)->format('g:i A') }}
                                </td>

                                <td>
                                    {{-- Straight to the register, filtered to this
                                         one booking. The reference is what staff
                                         quote on the phone, so it is the link. --}}
                                    <a href="{{ route('admin.reservations.index', ['q' => $reservation->reference_code]) }}"
                                        class="text-gray-900 text-hover-primary fw-semibold">
                                        {{ $reservation->title() }}
                                    </a>
                                    <span class="text-muted fs-8 d-block">{{ $reservation->reference_code }}</span>
                                </td>

                                <td>
                                    <span class="d-block">{{ $reservation->user?->name ?? '—' }}</span>
                                    <span class="text-muted fs-8">{{ $reservation->user?->phone }}</span>
                                </td>

                                <td class="text-center fw-bold">{{ $reservation->participants }}</td>

                                <td>
                                    <span class="badge badge-light-{{ $reservation->status->colour() }}">
                                        {{ $reservation->status->label() }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    @if ($reservation->outstandingTotal() > 0)
                                        <span class="text-danger fw-bold">
                                            {{ number_format($reservation->outstandingTotal()) }}
                                        </span>
                                    @else
                                        <span class="text-muted">Settled</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
