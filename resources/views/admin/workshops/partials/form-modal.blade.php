{{--
    One modal serves create and edit. The form's action attribute is rewritten
    by the JS: store URL for a new workshop, the update URL returned by the edit
    endpoint otherwise. Nothing about the shape of the form changes between the
    two, so there is no second template to keep in step.
--}}
<div class="modal fade" id="workshop-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-750px sh-modal-scroll">
        <div class="modal-content">

            <div class="modal-header">
                <h3 class="modal-title" id="workshop-modal-title">New workshop</h3>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary ms-2"
                    data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>

            <form id="workshop-form" action="{{ route('admin.workshops.store') }}" enctype="multipart/form-data"
                novalidate>
                @csrf

                <div class="modal-body py-8 px-9">

                    <div class="row g-6">

                        {{-- Identity --}}
                        <div class="col-md-8">
                            <label class="required form-label">Title</label>
                            <input type="text" name="title" class="form-control form-control-solid border"
                                placeholder="Clay session" maxlength="150" />
                            <div class="invalid-feedback d-block" data-error-for="title"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="required form-label">Category</label>
                            <select name="category" class="form-select form-select-solid border" data-control="select2" data-dropdown-parent="#workshop-modal" data-hide-search="true" data-placeholder="Choose…">
                                <option value=""></option>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="category"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Medium</label>
                            <input type="text" name="medium" class="form-control form-control-solid border"
                                placeholder="Hand-building or the wheel" maxlength="150" />
                            <div class="form-text">The one-line subtitle shown under the title on the website.</div>
                            <div class="invalid-feedback d-block" data-error-for="medium"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" class="form-control form-control-solid border"
                                placeholder="Left blank, generated from the title" maxlength="150" />
                            <div class="form-text">Used in reservation records. Changing it does not affect past
                                reservations.</div>
                            <div class="invalid-feedback d-block" data-error-for="slug"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Short description</label>
                            <textarea name="short_description" rows="2" class="form-control form-control-solid border"
                                maxlength="400" placeholder="The paragraph shown on the experience card."></textarea>
                            <div class="invalid-feedback d-block" data-error-for="short_description"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Full description</label>
                            <textarea name="description" rows="4" class="form-control form-control-solid border"
                                maxlength="5000" placeholder="Optional. Longer copy for a future detail page."></textarea>
                            <div class="invalid-feedback d-block" data-error-for="description"></div>
                        </div>

                        <div class="col-12 separator my-2"></div>

                        {{-- Commercials --}}
                        <div class="col-md-4">
                            <label class="required form-label">Price (BDT)</label>
                            <input type="number" name="price" class="form-control form-control-solid border" min="0"
                                step="1" inputmode="decimal" />
                            <div class="invalid-feedback d-block" data-error-for="price"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="required form-label">Charged</label>
                            <select name="price_basis" class="form-select form-select-solid border" data-control="select2" data-dropdown-parent="#workshop-modal" data-hide-search="true">
                                <option value="per_person">Per person</option>
                                <option value="per_session">Per session</option>
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="price_basis"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="required form-label">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" class="form-control form-control-solid border"
                                min="30" max="{{ \App\Support\Availability\SessionSlots::windowMinutes() }}" step="30"
                                inputmode="numeric" />
                            <div class="form-text" id="workshop-duration-hint"></div>
                            <div class="invalid-feedback d-block" data-error-for="duration_minutes"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="required form-label">Minimum participants</label>
                            <input type="number" name="min_participants" class="form-control form-control-solid border"
                                min="1" max="100" inputmode="numeric" />
                            <div class="invalid-feedback d-block" data-error-for="min_participants"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="required form-label">Maximum participants</label>
                            <input type="number" name="max_participants" class="form-control form-control-solid border"
                                min="1" max="100" inputmode="numeric" />
                            <div class="form-text">Enforced against seats already taken once capacity checking is
                                switched on in Settings.</div>
                            <div class="invalid-feedback d-block" data-error-for="max_participants"></div>
                        </div>

                        {{--
                            CAFÉ CREDIT. The column, the validation rule and the
                            issuing code all existed; this field did not, so the
                            figure stayed at its default of zero on every
                            workshop and no coupon was ever minted. That is why
                            "A visit to the space" was not producing the 50 BDT
                            credit the proposal describes.

                            Per person and per visit: the coupon is issued once,
                            worth this figure multiplied by the party size, the
                            moment the payment request is settled. Zero means
                            this experience earns nothing, which is right for
                            every session — the client's rule is that only the
                            non-session visit types carry it.

                            Deliberately not a discount. Café credit is spent at
                            the counter on food and drink; it never comes off the
                            price of the thing that earned it, which is why it
                            lives nowhere near PricingService.
                        --}}
                        <div class="col-md-6">
                            <label class="form-label">Café credit per person (BDT)</label>
                            <input type="number" name="cafe_credit_per_person"
                                class="form-control form-control-solid border" min="0" max="1000" step="1"
                                inputmode="decimal" />
                            <div class="form-text">
                                Issued as a single coupon once the visit is paid for, worth this much per guest.
                                Leave at 0 for experiences that earn no credit.
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="cafe_credit_per_person"></div>
                        </div>

                        <div class="col-12 separator my-2"></div>

                        {{-- Image --}}
                        <div class="col-md-7">
                            <label class="form-label">Image</label>
                            <input type="file" name="image" class="form-control form-control-solid border"
                                accept="image/jpeg,image/png,image/webp" />
                            <div class="form-text">JPEG, PNG or WebP, up to 2 MB. Landscape crops sit best on the
                                experience card.</div>
                            <div class="invalid-feedback d-block" data-error-for="image"></div>

                            <div class="form-check form-check-custom form-check-sm mt-3" id="workshop-image-remove-wrap"
                                hidden>
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1"
                                    id="workshop-remove-image" />
                                <label class="form-check-label text-muted" for="workshop-remove-image">
                                    Remove the current image
                                </label>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div id="workshop-image-preview" hidden>
                                <label class="form-label d-block">Current</label>
                                <img src="" alt="" class="w-100 rounded object-fit-cover" style="max-height:120px">
                            </div>
                        </div>

                        <div class="col-12 separator my-2"></div>

                        {{-- Flags --}}
                        <div class="col-md-8">
                            <div class="d-flex flex-wrap gap-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" />
                                    <span class="form-check-label fw-semibold">Live on the website</span>
                                </label>

                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" />
                                    <span class="form-check-label fw-semibold">Featured</span>
                                </label>

                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="materials_included"
                                        value="1" />
                                    <span class="form-check-label fw-semibold">Materials included</span>
                                </label>

                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="requires_experience"
                                        value="1" />
                                    <span class="form-check-label fw-semibold">Experience required</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" class="form-control form-control-solid border" min="0"
                                max="999" inputmode="numeric" />
                            <div class="form-text">Lower first, within the same duration.</div>
                            <div class="invalid-feedback d-block" data-error-for="sort_order"></div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="workshop-save">
                        <span class="indicator-label">Save workshop</span>
                        <span class="indicator-progress">Saving…
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
