{{--
    Requests against confirmations, twelve weeks.

    THE CHART/TABLE TAB PATTERN, and the reasoning behind it.

    The table is not a fallback. It is the CANONICAL rendering: every figure in
    it comes from Blade, server-side, the same way every other number in this
    panel does. The chart is a second view of the same rows, drawn by ApexCharts
    from arrays the server built.

    That ordering matters because of the standing rule that the browser does not
    compute or format anything. A chart is inherently JavaScript, so the table
    tab is where the rule is kept — if the two ever disagreed, the table is the
    one to believe, and having it one click away means somebody can check.

    Weekly rather than daily: an evening studio has days with nothing at all,
    and a daily line is mostly zeroes with spikes, which reads as a broken chart
    rather than as a business.
--}}

<div class="card h-100">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Requests and confirmations</h3>
            <span class="text-muted fs-7 mt-1">Last 12 weeks, by when the request arrived</span>
        </div>

        <div class="card-toolbar">
            <ul class="nav nav-pills nav-pills-sm nav-light" role="tablist">
                <li class="nav-item">
                    <a class="nav-link btn btn-active-light-primary btn-color-muted py-2 px-4 active"
                        data-bs-toggle="tab" href="#trend-chart" role="tab">
                        <i class="ki-outline ki-chart-line-up fs-5"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-active-light-primary btn-color-muted py-2 px-4" data-bs-toggle="tab"
                        href="#trend-table" role="tab">
                        <i class="ki-outline ki-row-horizontal fs-5"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="card-body pt-2">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="trend-chart" role="tabpanel">
                {{-- Rendered by dashboard.js. Sized here rather than in JS so
                     the card does not jump when the chart arrives. --}}
                <div id="chart-trend" style="height: 300px;"></div>
            </div>

            <div class="tab-pane fade" id="trend-table" role="tabpanel">
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-row-dashed align-middle fs-8 gy-2 mb-0">
                        <thead class="sticky-top bg-body">
                            <tr class="text-muted fw-bold fs-8 text-uppercase">
                                <th>Week</th>
                                <th class="text-center">Asked</th>
                                <th class="text-center">Confirmed</th>
                                <th class="text-center">Guests</th>
                                <th class="text-end">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trend['rows'] as $row)
                                <tr>
                                    <td class="text-nowrap">{{ $row['label'] }}</td>
                                    <td class="text-center">{{ $row['requested'] }}</td>
                                    <td class="text-center fw-bold text-gray-900">{{ $row['confirmed'] }}</td>
                                    <td class="text-center">{{ $row['guests'] }}</td>
                                    <td class="text-end text-muted">{{ $row['rate'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
