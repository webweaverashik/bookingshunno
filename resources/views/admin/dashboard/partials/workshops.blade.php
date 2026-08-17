{{--
    Which experiences people actually book, last 90 days.

    Counted from reservation ITEMS, not reservations: one booking can carry more
    than one workshop, and counting the booking would credit only the first.

    The table tab links each row through to the workshop itself, which is the
    action this panel invites — a session nobody books is one to reprice, move
    or retire, and the edit screen is where that happens.
--}}

<div class="card h-100">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">What people book</h3>
            <span class="text-muted fs-7 mt-1">Last 90 days, by visit date</span>
        </div>

        <div class="card-toolbar gap-2">
            <ul class="nav nav-pills nav-pills-sm nav-light" role="tablist">
                <li class="nav-item">
                    <a class="nav-link btn btn-active-light-primary btn-color-muted py-2 px-4 active"
                        data-bs-toggle="tab" href="#workshops-chart" role="tab">
                        <i class="ki-outline ki-chart-pie-simple fs-5"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-active-light-primary btn-color-muted py-2 px-4" data-bs-toggle="tab"
                        href="#workshops-table" role="tab">
                        <i class="ki-outline ki-row-horizontal fs-5"></i>
                    </a>
                </li>
            </ul>

            @can('workshops.view')
                <a href="{{ route('admin.workshops.index') }}" class="btn btn-sm btn-light-primary">Workshops</a>
            @endcan
        </div>
    </div>

    <div class="card-body pt-2">
        @if (empty($workshops['rows']))
            <div class="text-center text-muted py-10">Nothing booked in the last 90 days.</div>
        @else
            <div class="tab-content">
                <div class="tab-pane fade show active" id="workshops-chart" role="tabpanel">
                    <div id="chart-workshops" style="height: 300px;"></div>
                </div>

                <div class="tab-pane fade" id="workshops-table" role="tabpanel">
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-row-dashed align-middle fs-8 gy-2 mb-0">
                            <thead class="sticky-top bg-body">
                                <tr class="text-muted fw-bold fs-8 text-uppercase">
                                    <th>Experience</th>
                                    <th class="text-center">Bookings</th>
                                    <th class="text-end">Guests</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($workshops['rows'] as $row)
                                    <tr>
                                        <td>
                                            {{-- Plain text, not a link. Workshop
                                                 has no search scope, so a ?q=
                                                 link would land on an unfiltered
                                                 list and look broken. The
                                                 Workshops button in the header
                                                 is the honest route. --}}
                                            {{ $row['title'] }}
                                        </td>
                                        <td class="text-center fw-bold text-gray-900">{{ $row['bookings'] }}</td>
                                        <td class="text-end text-muted">{{ $row['guests'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
