<script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
<script src="{{ asset('js/admin/core.js') }}"></script>

<script>
    // Flash messages handed to the JS layer as data. The old template
    // interpolated them straight into a toastr call.
    window.ShunnoFlash = @json(['success' => session('success'), 'error' => session('error'), 'warning' => session('warning')]);
</script>

@stack('scripts')
