@props([
    'icon',
    'title',
    'description',
    'eyebrow' => 'Administration',
])

<header {{ $attributes->merge(['class' => 'admin-page-header']) }}>
    <div class="admin-page-heading">
        <span class="admin-page-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
        <div class="admin-page-copy">
            <span class="admin-page-eyebrow">{{ $eyebrow }}</span>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </div>
    </div>

    @isset($actions)
        <div class="admin-page-actions">{{ $actions }}</div>
    @endisset
</header>
