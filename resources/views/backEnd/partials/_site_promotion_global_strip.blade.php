@if (!empty($sitePromotionAlert))
    @include('backEnd.dashboard._site_promotion_styles')
    <div class="alert alert-warning alert-dismissible fade show vss-update-banner js-vss-update-banner" role="alert">
        <span class="ti-bell mr-2"></span>
        <strong>Update ready:</strong> {{ $sitePromotionAlert['title'] }}
        <span class="text-muted">({{ $sitePromotionAlert['file_count'] }} files
        @if (!empty($sitePromotionAlert['pushed_at_human']))
            &middot; {{ $sitePromotionAlert['pushed_at_human'] }}
        @endif
        )</span>
        <a href="{{ $sitePromotionAlert['detail_url'] }}" class="alert-link font-weight-bold ml-2">Review and apply &rarr;</a>
        <button type="button" class="close js-vss-strip-dismiss" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <script>
    (function () {
        var banner = document.querySelector('.js-vss-update-banner');
        var key = 'vss_strip_{{ $sitePromotionAlert['push_id'] ?? 'x' }}';
        if (banner && sessionStorage.getItem(key) === '1') banner.remove();
        document.querySelectorAll('.js-vss-strip-dismiss').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (banner) banner.remove();
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
            });
        });
    })();
    </script>
@endif
