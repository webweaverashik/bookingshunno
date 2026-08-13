<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkshopCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkshopRequest;
use App\Models\Workshop;
use App\Services\WorkshopService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Every mutation returns the { success, message, data } envelope fixed in §16,
 * and every mutation returns the freshly rendered table body with it. Rendering
 * rows in Blade rather than in JavaScript keeps one copy of the markup: the JS
 * swaps innerHTML and never has to know what a workshop row looks like.
 */
class WorkshopController extends Controller
{
    public function __construct(private readonly WorkshopService $workshops)
    {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Workshop::class);

        return view('admin.workshops.index', [
            'workshops'  => $this->collection(),
            'categories' => WorkshopCategory::options(),
        ]);
    }

    /** Table body only — used to refresh after any mutation. */
    public function rows(): JsonResponse
    {
        Gate::authorize('viewAny', Workshop::class);

        return $this->rowsResponse('');
    }

    public function store(WorkshopRequest $request): JsonResponse
    {
        Gate::authorize('create', Workshop::class);

        $workshop = $this->workshops->create(
            $request->validated(),
            $request->file('image'),
        );

        return $this->rowsResponse("“{$workshop->title}” has been added.");
    }

    /** Populates the edit modal. */
    public function edit(Workshop $workshop): JsonResponse
    {
        Gate::authorize('view', $workshop);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                  => $workshop->id,
                'title'               => $workshop->title,
                'slug'                => $workshop->slug,
                'category'            => $workshop->category?->value,
                'medium'              => $workshop->medium,
                'short_description'   => $workshop->short_description,
                'description'         => $workshop->description,
                'price'               => (float) $workshop->price,
                'price_basis'         => $workshop->price_basis,
                'duration_minutes'    => $workshop->duration_minutes,
                'min_participants'    => $workshop->min_participants,
                'max_participants'    => $workshop->max_participants,
                'materials_included'  => $workshop->materials_included,
                'requires_experience' => $workshop->requires_experience,
                'is_active'           => $workshop->is_active,
                'is_featured'         => $workshop->is_featured,
                'sort_order'          => $workshop->sort_order,
                'image_url'           => $workshop->imageUrl(),
                'update_url'          => route('admin.workshops.update', $workshop->id),
            ],
        ]);
    }

    /**
     * POST rather than PUT: the form carries a file, and FormData with a
     * spoofed _method is the only way to send multipart through PUT. One verb
     * is less to get wrong on every later module.
     */
    public function update(WorkshopRequest $request, Workshop $workshop): JsonResponse
    {
        Gate::authorize('update', $workshop);

        $workshop = $this->workshops->update(
            $workshop,
            $request->validated(),
            $request->file('image'),
        );

        return $this->rowsResponse("“{$workshop->title}” has been updated.");
    }

    public function toggle(Workshop $workshop): JsonResponse
    {
        Gate::authorize('update', $workshop);

        $workshop = $this->workshops->toggleActive($workshop);

        return $this->rowsResponse(
            $workshop->is_active
                ? "“{$workshop->title}” is now visible on the website."
                : "“{$workshop->title}” has been hidden from the website."
        );
    }

    public function destroy(Workshop $workshop): JsonResponse
    {
        Gate::authorize('delete', $workshop);

        $title = $workshop->title;

        try {
            $this->workshops->delete($workshop);
        } catch (RuntimeException $e) {
            // Business rule, not a system failure: 409 so the front end can
            // tell it apart from a validation error.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        return $this->rowsResponse("“{$title}” has been deleted.");
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function collection()
    {
        return Workshop::query()
            ->withCount('reservationItems')
            ->adminOrdered()
            ->get();
    }

    private function rowsResponse(string $message): JsonResponse
    {
        $workshops = $this->collection();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'html'   => view('admin.workshops.partials.rows', compact('workshops'))->render(),
                'count'  => $workshops->count(),
                'active' => $workshops->where('is_active', true)->count(),
            ],
        ]);
    }
}
