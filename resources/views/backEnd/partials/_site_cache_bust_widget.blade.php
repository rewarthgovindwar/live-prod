@if(app(\App\Services\SiteCacheBustService::class)->canBust())
<link rel="stylesheet" href="{{ asset('public/css/site-cache-bust.css') }}?v={{ asset_version() }}">
<div class="scb-floating-widget" id="scbFloatingWidget" aria-label="Hard refresh site">
    <button type="button" class="scb-edge-tab" id="scbHardRefreshBtn" title="Hard refresh site for everyone">
        <i class="ti-reload" aria-hidden="true"></i>
        <span class="scb-edge-label">Refresh all</span>
    </button>
</div>
<script>
(function () {
    var btn = document.getElementById('scbHardRefreshBtn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (!confirm('Hard refresh the site for everyone? This clears caches and bumps asset versions.')) {
            return;
        }

        btn.disabled = true;
        btn.classList.add('is-loading');

        fetch('{{ route('site-cache-bust') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (res.ok && res.data.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(res.data.message || 'Site refreshed for everyone.');
                    }
                    setTimeout(function () { window.location.reload(true); }, 800);
                    return;
                }
                var msg = (res.data && res.data.message) ? res.data.message : 'Could not refresh site.';
                if (typeof toastr !== 'undefined') toastr.error(msg);
                else alert(msg);
            })
            .catch(function () {
                if (typeof toastr !== 'undefined') toastr.error('Network error while refreshing site.');
                else alert('Network error while refreshing site.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.classList.remove('is-loading');
            });
    });
})();
</script>
@endif
