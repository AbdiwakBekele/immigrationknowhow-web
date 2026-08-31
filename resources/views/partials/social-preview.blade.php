@if (!empty($socialPreview))
    <meta name="description" content="{{ $socialPreview['description'] }}">
    <link rel="canonical" href="{{ $socialPreview['url'] }}">
    <meta property="og:title" content="{{ $socialPreview['title'] }}">
    <meta property="og:description" content="{{ $socialPreview['description'] }}">
    <meta property="og:url" content="{{ $socialPreview['url'] }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $socialPreview['site_name'] }}">
    <meta property="og:image" content="{{ $socialPreview['image'] }}">
    <meta property="og:image:secure_url" content="{{ $socialPreview['image'] }}">
    <meta property="og:image:alt" content="{{ $socialPreview['title'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $socialPreview['title'] }}">
    <meta name="twitter:description" content="{{ $socialPreview['description'] }}">
    <meta name="twitter:image" content="{{ $socialPreview['image'] }}">
@endif
