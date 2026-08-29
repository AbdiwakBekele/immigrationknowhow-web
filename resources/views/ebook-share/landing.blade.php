<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $item->title }} | {{ config('app.name') }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $item->title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    @if ($coverImageUrl)
        <meta property="og:image" content="{{ $coverImageUrl }}">
        <meta property="og:image:secure_url" content="{{ $coverImageUrl }}">
        <meta property="og:image:alt" content="{{ $item->title }} cover">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $item->title }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if ($coverImageUrl)
        <meta name="twitter:image" content="{{ $coverImageUrl }}">
    @endif
</head>
<body>
    <main style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem;">
        <h1>{{ $item->title }}</h1>
        @if ($coverImageUrl)
            <p><img src="{{ $coverImageUrl }}" alt="{{ $item->title }} cover" style="max-width: 240px; border-radius: 12px;"></p>
        @endif
        <p>{{ $description }}</p>
        <p><a href="{{ $destination }}">Read on {{ config('app.name') }}</a></p>
    </main>
</body>
</html>
