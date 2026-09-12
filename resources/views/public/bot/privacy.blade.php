@extends('public.bot.layout')

@php
    $organization = $document['publisher'];
@endphp

@section('title', 'Kebijakan Privasi — '.$organization)
@section('description', $document['description'])
@section('canonical', url('/privacy'))

@section('json_ld')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $document['title'],
            'description' => $document['description'],
            'url' => url('/privacy'),
            'inLanguage' => 'id-ID',
            'dateModified' => $document['last_updated'],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $document['publisher'],
                'url' => url('/'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endsection

@section('content')
    @include('public.bot.legal-sections', ['document' => $document, 'legalDocuments' => $legalDocuments])
@endsection
