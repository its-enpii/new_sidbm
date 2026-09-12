@extends('public.bot.layout')

@php
    $organization = $site['organization'];
    $description = $post['meta_description'] ?? $post['excerpt'] ?? "Berita dari {$organization['name']}.";
    $canonical = url('/berita/'.$post['slug']);
@endphp

@section('title', $post['title'].' — '.$organization['name'])
@section('description', $description)
@section('canonical', $canonical)
@section('twitter_card', 'summary_large_image')

@section('content')
    <article>
        <h1>{{ $post['title'] }}</h1>
        @if ($post['published_at'])
            <p><time datetime="{{ $post['published_at'] }}">{{ \Illuminate\Support\Carbon::parse($post['published_at'])->locale('id')->translatedFormat('d F Y') }}</time></p>
        @endif
        @if ($post['excerpt'])
            <p>{{ $post['excerpt'] }}</p>
        @endif
        @if ($post['cover_image_url'])
            <img src="{{ $post['cover_image_url'] }}" alt="{{ $post['title'] }}">
        @endif
        {{-- Rich content is produced by the server-side Tiptap publish flow. --}}
        <div>{!! $post['content'] !!}</div>
    </article>
@endsection
