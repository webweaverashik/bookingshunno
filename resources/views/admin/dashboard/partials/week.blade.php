{{--
    The next seven days.

    Guests are counted from bookings HOLDING CAPACITY only — the same scope
    AvailabilityService uses — so this panel and the public booking form can
    never disagree about how full a day is. An unanswered request holds nothing
    and must not appear to.

    "Unsettled" is the column staff act on: people coming who have not paid.
--}}

<div class="card h-100">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">The week ahead</h3>
            <span class="text-muted fs-7 mt-1">Committed places only</span>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle fs-8 gy-3 mb-0">
                <thead>
                    <tr class="text-muted fw-bold fs-8 text-uppercase">
                        <th>Day</th>
                        <th class="text-center">Sessions</th>
                        <th class="text-center">Guests</th>
                        <th class="text-end">Unpaid</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($week as $day)
                        <tr @class(['bg-light-primary bg-opacity-25' => $loop->first])>
                            <td>
                                {{-- Today links to range=today; the rest to
                                     "today and ahead", because the register has
                                     no single-arbitrary-date filter and inventing
                                     a from/to link here would produce one that
                                     silently ignores its own parameters. Adding
                                     a date filter to the register is a small
                                     change and would make these exact. --}}
                                <a href="{{ route('admin.reservations.index', ['range' => $loop->first ? 'today' : 'upcoming']) }}"
                                    class="text-gray-900 text-hover-primary fw-semibold">
                                    {{ $day['date']->format('D j M') }}
                                </a>
                                @if ($loop->first)
                                    <span class="badge badge-light-primary fs-9">Today</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $day['sessions'] ?: '—' }}</td>
                            <td class="text-center fw-bold text-gray-900">{{ $day['guests'] ?: '—' }}</td>
                            <td class="text-end">
                                @if ($day['unsettled'] > 0)
                                    <span class="badge badge-light-warning">{{ $day['unsettled'] }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
