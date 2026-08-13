{{--
    Seven rows, always, in weekday order. Rendered on load and re-rendered after
    a save so the times come back normalised (16:00:00 from the database becomes
    16:00 in the input) rather than left as whatever was typed.
--}}
@php
    $canManage = auth()->user()->can('availability.update');
    $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
@endphp

@foreach ($hours as $index => $day)
    @php
        $dow = (int) $day->day_of_week;
        $opens = $day->opens_at ? substr($day->opens_at, 0, 5) : '';
        $closes = $day->closes_at ? substr($day->closes_at, 0, 5) : '';
    @endphp

    <tr data-hours-row>
        <td class="text-gray-800 fw-semibold">
            {{ $names[$dow] }}
            <input type="hidden" name="days[{{ $index }}][day_of_week]" value="{{ $dow }}">
        </td>

        <td>
            <input type="time" step="1800"
                name="days[{{ $index }}][opens_at]"
                class="form-control form-control-sm form-control-solid w-125px"
                value="{{ $opens }}"
                {{ $day->is_closed || !$canManage ? 'disabled' : '' }}>
            <div class="invalid-feedback d-block fs-8" data-error-for="days.{{ $index }}.opens_at"></div>
        </td>

        <td>
            <input type="time" step="1800"
                name="days[{{ $index }}][closes_at]"
                class="form-control form-control-sm form-control-solid w-125px"
                value="{{ $closes }}"
                {{ $day->is_closed || !$canManage ? 'disabled' : '' }}>
            <div class="invalid-feedback d-block fs-8" data-error-for="days.{{ $index }}.closes_at"></div>
        </td>

        <td class="text-center">
            {{-- Disabled inputs are not submitted, so the checkbox drives both
                 the visual state and what reaches the server. --}}
            <label class="form-check form-check-custom form-check-solid form-check-sm justify-content-center">
                <input class="form-check-input" type="checkbox" data-closed-toggle
                    name="days[{{ $index }}][is_closed]" value="1"
                    @checked($day->is_closed) {{ $canManage ? '' : 'disabled' }}>
            </label>
        </td>
    </tr>
@endforeach
