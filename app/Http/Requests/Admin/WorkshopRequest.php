<?php
namespace App\Http\Requests\Admin;

use App\Enums\WorkshopCategory;
use App\Models\Workshop;
use App\Support\SessionSlots;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;

/**
 * One request class for create and update. The only rule that differs is the
 * slug's unique-ignore, which route model binding gives us for free.
 */
class WorkshopRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is the policy's job; the controller gates before this
        // runs. Returning true here avoids a second, divergent rule set.
        return true;
    }

    private function workshop(): ?Workshop
    {
        return $this->route('workshop');
    }

    public function rules(): array
    {
        $window = SessionSlots::windowMinutes();

        return [
            'title'               => ['required', 'string', 'min:2', 'max:150'],

            'slug'                => [
                'nullable', 'string', 'max:150', 'alpha_dash',
                Rule::unique('workshops', 'slug')->ignore($this->workshop()?->id)->withoutTrashed(),
            ],

            'category'            => ['required', Rule::in(WorkshopCategory::values())],
            'medium'              => ['nullable', 'string', 'max:150'],

            'short_description'   => ['nullable', 'string', 'max:400'],
            'description'         => ['nullable', 'string', 'max:5000'],

            'price'               => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'price_basis'         => ['required', Rule::in(['per_person', 'per_session'])],

            // Multiples of 30 only: slots step every 30 minutes, so a 40-minute
            // session would produce start times that do not line up with
            // anything else running that evening.
            'duration_minutes'    => ['required', 'integer', 'min:30', 'max:' . $window, 'multiple_of:30'],

            'min_participants'    => ['required', 'integer', 'min:1', 'max:100'],
            'max_participants'    => ['required', 'integer', 'min:1', 'max:100', 'gte:min_participants'],

            'materials_included'  => ['boolean'],
            'requires_experience' => ['boolean'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],

            'sort_order'          => ['nullable', 'integer', 'min:0', 'max:999'],

            'image'               => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_image'        => ['boolean'],
        ];
    }

    public function messages(): array
    {
        $window = SessionSlots::windowMinutes();
        $hours  = round($window / 60, 1);

        return [
            'duration_minutes.multiple_of' => 'Duration must be a multiple of 30 minutes so start times line up.',
            'duration_minutes.max'         => "The studio window is only {$hours} hours ({$window} minutes). A longer session could never be scheduled.",
            'max_participants.gte' => 'Maximum participants cannot be below the minimum.',
            'image.max'            => 'The image must be 2 MB or smaller.',
            'slug.alpha_dash'      => 'The slug may only contain letters, numbers, dashes and underscores.',
        ];
    }

    /**
     * Checkboxes that are unticked are simply absent from FormData, so they
     * have to be normalised before validation rather than defaulted after it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title'               => trim((string) $this->input('title')),
            'slug'                => $this->filled('slug') ? Str::slug($this->input('slug')) : null,
            'materials_included'  => $this->boolean('materials_included'),
            'requires_experience' => $this->boolean('requires_experience'),
            'is_active'           => $this->boolean('is_active'),
            'is_featured'         => $this->boolean('is_featured'),
            'remove_image'        => $this->boolean('remove_image'),
            'sort_order'          => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['duration_minutes'])) {
                    return;
                }

                // A duration can be legal on its own and still leave no usable
                // start time — catch it here rather than letting the popup show
                // an empty time dropdown.
                $minutes = (int) $this->input('duration_minutes');

                if ($minutes > 0 && SessionSlots::forMinutes($minutes) === []) {
                    $validator->errors()->add(
                        'duration_minutes',
                        'No start time in the 4:00 PM – 9:30 PM window is long enough for this session.'
                    );
                }
            },
            function (Validator $validator) {
                $image = $this->file('image');

                // An upload that PHP failed to write still arrives as an
                // UploadedFile and passes the mime rules; only the temp file is
                // missing. Catch it here rather than letting the filesystem
                // adapter throw on an empty path.
                if ($image && ! $image->isValid()) {
                    $validator->errors()->add(
                        'image',
                        'The image could not be uploaded. It may be too large, or the server temp folder is not writable.'
                    );
                }
            },
        ];
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store(self::IMAGE_DIR, self::IMAGE_DISK);

        if (! $path) {
            throw new RuntimeException(
                'The image could not be saved. Check that storage/app/public is writable and that php artisan storage:link has been run.'
            );
        }

        return $path;
    }
}
