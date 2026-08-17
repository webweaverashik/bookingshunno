{{--
    Sign-in history — the last {{ $limit }} entries.

    A DataTable, and this is the case where that fits. DataTables sorts and pages
    CLIENT-side, so every row it is given sits in the DOM — which is exactly why
    the reservation register does not use it, and exactly why a capped list of
    thirty can. There is no AJAX endpoint behind this; the rows arrive with the
    page and never grow.

    Initialised in profile.js. DataTables is a jQuery plugin with no vanilla API,
    and jQuery is already in Metronic's bundle — the project's rule is against a
    jQuery application architecture, not against initialising a bundled plugin.
--}}

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Sign-in history</h3>
            <span class="text-muted fs-7 mt-1">
                Your last {{ $limit }} sign-ins. If you see one that was not you, change your password.
            </span>
        </div>
    </div>

    <div class="card-body pt-0">
        <table class="table align-middle table-row-dashed fs-7 gy-4" id="activity-table">
            <thead>
                <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                    <th class="min-w-175px">When</th>
                    <th class="min-w-100px">Device</th>
                    <th class="min-w-125px">IP address</th>
                    <th class="min-w-200px">Browser</th>
                </tr>
            </thead>

            <tbody class="text-gray-700">
                @foreach ($activities as $activity)
                    <tr>
                        {{--
                            data-order carries the raw timestamp so DataTables
                            sorts on that rather than on the rendered string.
                            Without it, "1 Aug 2026" sorts alphabetically and
                            April lands before January.
                        --}}
                        <td data-order="{{ $activity->created_at?->timestamp }}">
                            <span class="fw-semibold text-gray-900 d-block">
                                {{ $activity->created_at?->format('j M Y, g:i A') }}
                            </span>
                            <span class="text-muted fs-8">{{ $activity->created_at?->diffForHumans() }}</span>
                        </td>

                        <td>
                            <span class="badge badge-light-primary">{{ $activity->device ?? 'Unknown' }}</span>
                        </td>

                        <td class="font-monospace">{{ $activity->ip_address ?? '—' }}</td>

                        <td class="text-muted text-truncate mw-300px" title="{{ $activity->user_agent }}">
                            {{ $activity->user_agent ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($activities->isEmpty())
            <div class="text-center text-muted py-10">No sign-ins recorded yet.</div>
        @endif
    </div>
</div>
