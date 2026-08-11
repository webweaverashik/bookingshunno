@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@php
    $cards = [
        ['label' => 'Awaiting review',  'value' => $pendingCount,  'icon' => 'ki-notification-status', 'colour' => 'warning'],
        ['label' => 'Open reservations','value' => $openCount,     'icon' => 'ki-calendar-8',          'colour' => 'primary'],
        ['label' => 'Booked today',     'value' => $todayCount,    'icon' => 'ki-time',                'colour' => 'success'],
        ['label' => 'Booked this week', 'value' => $thisWeekCount, 'icon' => 'ki-chart-simple',        'colour' => 'info'],
    ];
@endphp

@section('content')
    <div class="row g-5 mb-6">
        @foreach ($cards as $card)
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <span class="symbol symbol-50px me-4">
                            <span class="symbol-label bg-light-{{ $card['colour'] }}">
                                <i class="ki-outline {{ $card['icon'] }} fs-2x text-{{ $card['colour'] }}"></i>
                            </span>
                        </span>
                        <div>
                            <div class="fs-2hx fw-bold text-dark lh-1">{{ $card['value'] }}</div>
                            <div class="fs-7 text-muted fw-semibold">{{ $card['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-5">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Waiting for you</h3>
                    <div class="card-toolbar">
                        <span class="badge badge-light-warning">{{ $pendingCount }} pending</span>
                    </div>
                </div>
                <div class="card-body pt-3">
                    @forelse ($pending as $reservation)
                        <div class="d-flex align-items-center border-bottom border-gray-300 border-bottom-dashed py-3">
                            <div class="flex-grow-1">
                                <span class="text-dark fw-bold fs-6">{{ $reservation->user->name }}</span>
                                <span class="text-muted fw-semibold d-block fs-7">
                                    {{ $reservation->items->first()?->title_snapshot ?? 'Visit' }}
                                    &middot; {{ $reservation->participants }}
                                    {{ \Illuminate\Support\Str::plural('person', $reservation->participants) }}
                                    &middot; {{ $reservation->reserved_date->format('D j M') }}
                                    at {{ \Illuminate\Support\Carbon::parse($reservation->start_time)->format('g:i A') }}
                                </span>
                            </div>
                            <div class="text-end">
                                <span class="text-dark fw-bold d-block">{{ number_format((float) $reservation->total_amount) }} BDT</span>
                                <span class="text-muted fs-8">{{ $reservation->reference_code }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10">
                            <i class="ki-outline ki-check-circle fs-3x text-success mb-3 d-block"></i>
                            <span class="text-muted fw-semibold">Nothing waiting. The queue is clear.</span>
                        </div>
                    @endforelse

                    @if ($pending->isNotEmpty())
                        {{-- PHASE 9: this becomes the reservations table with approve and decline. --}}
                        <div class="text-muted fs-8 pt-4">Approving and declining arrives in Phase 9.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="card-title fw-bold">Coming up</h3>
                </div>
                <div class="card-body pt-3">
                    @forelse ($upcoming as $reservation)
                        <div class="d-flex align-items-center py-3">
                            <span class="bullet bullet-vertical h-40px bg-{{ $reservation->status->colour() }} me-4"></span>
                            <div class="flex-grow-1">
                                <span class="text-dark fw-bold fs-6 d-block">
                                    {{ $reservation->reserved_date->format('D j M') }},
                                    {{ \Illuminate\Support\Carbon::parse($reservation->start_time)->format('g:i A') }}
                                </span>
                                <span class="text-muted fw-semibold fs-7">
                                    {{ $reservation->user->name }} &middot;
                                    {{ $reservation->items->first()?->title_snapshot ?? 'Visit' }}
                                </span>
                            </div>
                            <span class="badge badge-light-{{ $reservation->status->colour() }}">
                                {{ $reservation->status->label() }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-10">
                            <span class="text-muted fw-semibold">Nothing booked yet.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
