{{--
    Money owed, in both directions.

    Two halves that are easy to confuse and important not to: what visitors owe
    the studio, and what the studio owes in goods. A voucher is a liability —
    the studio was paid for it once and still has to honour it — so it belongs
    beside the receivable rather than folded into revenue anywhere.

    Not ranged. "What is still owed" is not a question about a date window, and
    answering it as one would hide older unpaid requests.
--}}

<div class="card h-100">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Outstanding</h3>
            <span class="text-muted fs-7 mt-1">As at today, not for a date range</span>
        </div>
    </div>

    <div class="card-body pt-0">

        @can('payments.view')
            <a href="{{ route('admin.payments.index', ['status' => 'open']) }}"
                class="d-flex align-items-center justify-content-between py-4 text-decoration-none">
                <div>
                    <div class="fw-semibold text-gray-800">Owed to the studio</div>
                    <div class="fs-8 text-muted">
                        Across {{ $balances['unpaid_count'] }}
                        open {{ \Illuminate\Support\Str::plural('request', $balances['unpaid_count']) }}
                    </div>
                </div>
                <div class="fs-3 fw-bold text-{{ $balances['owed_to_studio'] > 0 ? 'warning' : 'gray-500' }}">
                    {{ number_format($balances['owed_to_studio']) }}
                </div>
            </a>
        @endcan

        @can('vouchers.view')
            {{-- status=usable, not 'active'. The voucher register distinguishes
                 the two deliberately: 'active' includes codes past their expiry
                 date, and this figure counts only what is still spendable. --}}
            <a href="{{ route('admin.vouchers.index', ['status' => 'usable']) }}"
                class="d-flex align-items-center justify-content-between py-4 border-top text-decoration-none">
                <div>
                    <div class="fw-semibold text-gray-800">Owed by the studio</div>
                    <div class="fs-8 text-muted">Every voucher still spendable</div>
                </div>
                <div class="fs-3 fw-bold text-gray-900">{{ number_format($balances['voucher_liability']) }}</div>
            </a>

            <div class="d-flex align-items-center justify-content-between py-3 border-top">
                <div class="fs-8 text-muted">&mdash; of which café credit</div>
                <div class="fs-6 fw-semibold text-muted">{{ number_format($balances['cafe_credit']) }}</div>
            </div>
        @endcan

    </div>
</div>
