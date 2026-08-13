<?php

namespace App\Http\Requests\Admin;

use App\Enums\WorkshopCategory;
use App\Models\Workshop;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One request class for create and update. The only rule that differs is the
 * slug's unique-ignore, which route model binding gives us for free.
 *
 * PHASE 7A: the duration ceiling and the step size now come from
 * AvailabilityService rather than the deleted SessionSlots, so editing the
 * operating hours immediately changes what durations are accepted here.
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

    private function availability(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    public function rules(): array
    {
        $window = $this->availability()->longestWindowMinutes();

        return [
            'title' => ['required', 'string', 'min:2', 'max:150'],

            'slug' => [
                'nullable', 'string', 'max:150', 'alpha_dash',
                Rule::unique('workshops', 'slug')->ignore($this->workshop()?->id)->withoutTrashed(),
            ],

            'category' => ['required', Rule::in(WorkshopCategory::values())],
            'medium'   => ['nullable', 'string', 'max:150'],

            'short_description' => ['nullable', 'string', 'max:400'],
            'description'       => ['nullable', 'string', 'max:5000'],

            'price'       => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'price_basis' => ['required', Rule::in(['per_person', 'per_session'])],

            // Multiples of the slot step only: start times move in 30-minute
            // increments, so a 40-minute session would produce times that line
            // up with nothing else running that evening.
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:' . $window, 'multiple_of:30'],

            'min_participants' => ['required', 'integer', 'min:1', 'max:100'],
            'max_participants' => ['required', 'integer', 'min:1', 'max:100', 'gte:min_participants'],

            'materials_included'  => ['boolean'],
            'requires_experience' => ['boolean'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],

            'image'        => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_image' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        $window = $this->availability()->longestWindowMinutes();
        $hours  = round($window / 60, 1);

        return [
            'duration_minutes.multiple_of' => 'Duration must be a multiple of 30 minutes so start times line up.',
            'duration_minutes.max'         => "The longest studio window is {$hours} hours ({$window} minutes). A longer session could never be scheduled.",
            'max_participants.gte'         => 'Maximum participants cannot be below the minimum.',
            'image.max'                    => 'The image must be 2 MB or smaller.',
            'slug.alpha_dash'              => 'The slug may only contain letters, numbers, dashes and underscores.',
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
                $image = $this->file('image');

                // An upload PHP failed to write still arrives as an
                // UploadedFile and passes the mime rules; only the temp file is
                // missing. Catch it before the filesystem adapter throws on an
                // empty path.
                if ($image && ! $image->isValid()) {
                    $validator->errors()->add(
                        'image',
                        'The image could not be uploaded. It may be too large, or the server temp folder is not writable.'
                    );
                }
            },
        ];
    }
}
