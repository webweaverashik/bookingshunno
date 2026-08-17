{{--
    Money received, six months.

    Ranged on when the money ARRIVED, matching the payments report exactly — so
    the two can be read side by side without reconciling.

    Voucher settlements are a SEPARATE series, not part of the cash line. The
    studio was paid when the gift voucher was sold; counting the redemption
    again would overstate income by the value of every coupon honoured. Shown
    rather than dropped, because a month with heavy redemption looks like a bad
    month otherwise.
--}}

<div class="card h-100">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Money received</h3>
            <span class="text-muted fs-7 mt-1">Last 6 months, by when it arrived</span>
        </div>

        <div class="card-toolbar gap-2">
            <ul class="nav nav-pills nav-pills-sm nav-light" role="tablist">
                <li class="nav-item">
                    <a class="nav-link btn btn-active-light-primary btn-color-muted py-2 px-4 active"
                        data-bs-toggle="tab" href="#revenue-chart" role="tab">
                        <i class="ki-outline ki-chart-simple fs-5"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-active-light-primary btn-color-muted py-2 px-4" data-bs-toggle="tab"
                        href="#revenue-table" role="tab">
                        <i class="ki-outline ki-row-horizontal fs-5"></i>
                    </a>
                </li>
            </ul>

            @can('reports.view')
                <a href="{{ route('admin.reports.show', 'payments') }}" class="btn btn-sm btn-light-primary">Report</a>
            @endcan
        </div>
    </div>

    <div class="card-body pt-2">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="revenue-chart" role="tabpanel">
                <div id="chart-revenue" style="height: 300px;"></div>
            </div>

            <div class="tab-pane fade" id="revenue-table" role="tabpanel">
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-row-dashed align-middle fs-8 gy-2 mb-0">
                        <thead class="sticky-top bg-body">
                            <tr class="text-muted fw-bold fs-8 text-uppercase">
                                <th>Month</th>
                                <th class="text-end">Cash</th>
                                <th class="text-end">Voucher</th>
                                <th class="text-center">Receipts</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($revenue['rows'] as $row)
                                <tr>
                                    <td class="text-nowrap">{{ $row['label'] }}</td>
                                    <td class="text-end fw-bold text-gray-900">{{ $row['cash'] }}</td>
                                    <td class="text-end text-muted">{{ $row['voucher'] }}</td>
                                    <td class="text-center">{{ $row['receipts'] }}</td>
                                    <td class="text-end">{{ $row['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
