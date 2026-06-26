@php
    $active = $activeTab ?? 'inventory';
    $tabs = [
        'inventory' => [
            'route' => route('inv-kitchen'),
            'label' => __('kitchen.kitchen_inventory'),
            'icon' => 'ti-home',
        ],
        'utilization' => [
            'route' => route('inv-kitchen-utilization-create'),
            'label' => __('kitchen.log_utilization'),
            'icon' => 'ti-clipboard',
        ],
        'utilization_list' => [
            'route' => route('inv-kitchen-utilization-index'),
            'label' => __('kitchen.all_utilizations'),
            'icon' => 'ti-list',
        ],
        'recipes' => [
            'route' => route('inv-kitchen-recipes'),
            'label' => __('kitchen.recipes'),
            'icon' => 'ti-book',
        ],
        'report_daily' => [
            'route' => route('inv-kitchen-report-daily'),
            'label' => __('kitchen.report_daily'),
            'icon' => 'ti-bar-chart',
        ],
        'report_monthly' => [
            'route' => route('inv-kitchen-report-monthly'),
            'label' => __('kitchen.report_monthly'),
            'icon' => 'ti-pie-chart',
        ],
        'report_low_stock' => [
            'route' => route('inv-kitchen-report-low-stock'),
            'label' => __('kitchen.report_low_stock'),
            'icon' => 'ti-alert',
        ],
        'setup' => [
            'route' => route('inv-kitchen-setup'),
            'label' => __('kitchen.setup_wizard'),
            'icon' => 'ti-settings',
        ],
    ];
@endphp
<nav class="inv-kit-nav-tabs" aria-label="@lang('kitchen.kitchen_inventory')">
    @foreach($tabs as $key => $tab)
        <a href="{{ $tab['route'] }}"
           class="inv-kit-nav-tab {{ $active === $key ? 'is-active' : '' }}"
           @if($active === $key) aria-current="page" @endif>
            <span class="ti {{ $tab['icon'] }}"></span>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
