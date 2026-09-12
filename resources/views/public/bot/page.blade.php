@extends('public.bot.layout')

@php
    $organization = $site['organization'];
    $canonical = url('/p/'.$page['slug']);
@endphp

@section('title', $page['title'].' — '.$organization['name'])
@section('description', $page['meta_description'] ?? "Halaman {$page['title']} dari {$organization['name']}.")
@section('canonical', $canonical)

@section('content')
    <article>
        <h1>{{ $page['title'] }}</h1>
        {{-- Rich content is produced by the server-side Tiptap publish flow. --}}
        <div>{!! $page['content'] !!}</div>
    </article>
@endsection
