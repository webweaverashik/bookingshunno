<?php

namespace App\Http\Controllers\Admin\Availability;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Availability\BlockedDateRequest;
use App\Models\Availability\BlockedDate;
use App\Services\Availability\AvailabilityAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Same shape as WorkshopController: Blade renders the rows, every mutation
 * returns the re-rendered table body, the JavaScript swaps innerHTML and never
 * builds markup.
 */
class BlockedDateController extends Controller
{
    public function __construct(private readonly AvailabilityAdminService $admin)
    {
    }

    public function store(BlockedDateRequest $request): JsonResponse
    {
        Gate::authorize('create', BlockedDate::class);

        $data = $request->validated();

        if ($clash = $this->clashResponse($data)) {
            return $clash;
        }

        $block = $this->admin->block($data);

        return $this->rowsResponse(
            $block->date->format('j M Y') . ' has been blocked.'
        );
    }

    public function edit(BlockedDate $blockedDate): JsonResponse
    {
        Gate::authorize('view', $blockedDate);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $blockedDate->id,
                'date'        => $blockedDate->date->toDateString(),
                'is_full_day' => $blockedDate->is_full_day,
                'starts_at'   => $blockedDate->starts_at ? substr($blockedDate->starts_at, 0, 5) : null,
                'ends_at'     => $blockedDate->ends_at ? substr($blockedDate->ends_at, 0, 5) : null,
                'reason'      => $blockedDate->reason,
                'update_url'  => route('admin.availability.blocked.update', $blockedDate->id),
            ],
        ]);
    }

    public function update(BlockedDateRequest $request, BlockedDate $blockedDate): JsonResponse
    {
        Gate::authorize('update', $blockedDate);

        $data = $request->validated();

        if ($clash = $this->clashResponse($data, $blockedDate)) {
            return $clash;
        }

        $block = $this->admin->updateBlock($blockedDate, $data);

        return $this->rowsResponse(
            $block->date->format('j M Y') . ' has been updated.'
        );
    }

    public function destroy(BlockedDate $blockedDate): JsonResponse
    {
        Gate::authorize('delete', $blockedDate);

        $label = $blockedDate->date->format('j M Y');

        $this->admin->unblock($blockedDate);

        return $this->rowsResponse("{$label} is open for bookings again.");
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Blocking a date does not cancel anything. If reservations already hold
     * capacity in that period the admin is told, once, and has to confirm —
     * because someone will need to contact those visitors, and a silent block
     * would strand them.
     */
    private function clashResponse(array $data, ?BlockedDate $ignore = null): ?JsonResponse
    {
        if (! empty($data['acknowledge'])) {
            return null;
        }

        $affected = $this->admin->reservationsAffectedBy($data, $ignore);

        if ($affected === 0) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $affected === 1
                ? 'There is already 1 reservation in that period. Blocking will not cancel it — you will need to contact the visitor.'
                : "There are already {$affected} reservations in that period. Blocking will not cancel them — you will need to contact those visitors.",
            'data'    => ['requires_acknowledgement' => true, 'affected' => $affected],
        ], 409);
    }

    private function rowsResponse(string $message): JsonResponse
    {
        $blocks = BlockedDate::query()
            ->with('creator:id,name')
            ->upcoming()
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'html'  => view('admin.availability.partials.blocked-rows', compact('blocks'))->render(),
                'count' => $blocks->count(),
            ],
        ]);
    }
}
