@props([
    'icon' => 'bi bi-compass',
    'eyebrow' => 'Portal',
    'title' => 'Dashboard',
    'description' => '',
    'badge' => '',
    'badgeIcon' => 'bi bi-person-badge',
    'variant' => 'portal',
])

<header {{ $attributes->merge(['class' => 'portal-page-indicator portal-page-indicator--'.$variant]) }}>
    <div class="portal-page-indicator__heading">
        <span class="portal-page-indicator__icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
        <div class="portal-page-indicator__copy">
            <span class="portal-page-indicator__eyebrow">{{ $eyebrow }}</span>
            <h1>{{ $title }}</h1>
            @if(filled($description))
                <p>{{ $description }}</p>
            @endif
        </div>
    </div>

    @if(filled($badge) || (isset($actions) && $actions->isNotEmpty()))
        <div class="portal-page-indicator__side">
            @if(isset($actions) && $actions->isNotEmpty())
                <div class="portal-page-indicator__actions">{{ $actions }}</div>
            @endif
            @if(filled($badge))
                <span class="portal-page-indicator__badge">
                    <i class="{{ $badgeIcon }}" aria-hidden="true"></i><span>{{ $badge }}</span>
                </span>
            @endif
        </div>
    @endif
</header>
