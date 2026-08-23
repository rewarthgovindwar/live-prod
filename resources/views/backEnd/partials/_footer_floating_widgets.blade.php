{{-- Include from backEnd/partials/footer.blade.php after the command center floating widget --}}
@includeWhen(
    View::exists('backEnd.commandCenter.partials.floating-widget'),
    'backEnd.commandCenter.partials.floating-widget'
)
@include('backEnd.partials._site_cache_bust_widget')
