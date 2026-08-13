<?php

namespace App\Services;

use App\Models\Workshop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Every write to a workshop goes through here, for two reasons:
 *  - the public menu cache must be dropped on any change, and one forgotten
 *    call would leave the landing page serving a stale price;
 *  - image files on disk have to be cleaned up in step with the column.
 */
class WorkshopService
{
    private const IMAGE_DIR  = 'workshops';
    private const IMAGE_DISK = 'public';

    public function create(array $data, ?UploadedFile $image = null): Workshop
    {
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['title']);

        return DB::transaction(function () use ($data, $image) {
            if ($image) {
                $data['image_path'] = $this->storeImage($image);
            }

            $workshop = Workshop::create($this->attributes($data));

            Workshop::forgetMenu();

            return $workshop;
        });
    }

    public function update(Workshop $workshop, array $data, ?UploadedFile $image = null): Workshop
    {
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['title'], $workshop);

        return DB::transaction(function () use ($workshop, $data, $image) {
            $previous = $workshop->image_path;

            if ($image) {
                $data['image_path'] = $this->storeImage($image);
            } elseif (! empty($data['remove_image'])) {
                $data['image_path'] = null;
            }

            $workshop->update($this->attributes($data));

            // Only after the row is safely written, and only for files this
            // application put there — the seeded images live in the repo.
            if (array_key_exists('image_path', $data) && $previous !== $workshop->image_path) {
                $this->deleteImage($previous);
            }

            Workshop::forgetMenu();

            return $workshop->refresh();
        });
    }

    public function toggleActive(Workshop $workshop): Workshop
    {
        $workshop->update(['is_active' => ! $workshop->is_active]);

        Workshop::forgetMenu();

        return $workshop;
    }

    /**
     * @throws RuntimeException when the workshop has reservation history.
     */
    public function delete(Workshop $workshop): void
    {
        if ($workshop->hasReservations()) {
            throw new RuntimeException(
                'This workshop appears on existing reservations and cannot be deleted. Deactivate it instead — it will disappear from the website but stay in reports.'
            );
        }

        DB::transaction(function () use ($workshop) {
            $image = $workshop->image_path;

            $workshop->delete();   // soft delete

            // The row is recoverable; the upload is not worth keeping alongside
            // it, since a restored workshop can be given a new image.
            $this->deleteImage($image);

            Workshop::forgetMenu();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Strip the keys that are not columns before touching the model, so a stray
     * form field can never reach the database even if $fillable changes.
     */
    private function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'slug', 'title', 'medium', 'category', 'short_description', 'description',
            'image_path', 'price', 'price_basis', 'duration_minutes',
            'min_participants', 'max_participants', 'materials_included',
            'requires_experience', 'is_active', 'is_featured', 'sort_order',
        ]));
    }

    private function resolveSlug(?string $slug, string $title, ?Workshop $ignore = null): string
    {
        $base = Str::slug($slug ?: $title) ?: 'workshop';
        $slug = $base;
        $n    = 2;

        // Validation already rejects a duplicate the admin typed. This loop
        // only covers the auto-generated case, where two workshops can share a
        // title without the admin ever seeing a slug field.
        while (
            Workshop::withTrashed()
                ->where('slug', $slug)
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists()
        ) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    private function storeImage(UploadedFile $image): string
    {
        return $image->store(self::IMAGE_DIR, self::IMAGE_DISK);
    }

    /** Only removes files this application uploaded, never repo-shipped assets. */
    private function deleteImage(?string $path): void
    {
        if ($path && Str::startsWith($path, self::IMAGE_DIR . '/')) {
            Storage::disk(self::IMAGE_DISK)->delete($path);
        }
    }
}
