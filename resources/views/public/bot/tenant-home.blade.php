@extends('public.bot.layout')

@php
    $organization = $site['organization'];
    $settings = $site['settings'];
    $title = $organization['name'].' — Sistem Informasi Dana Bergulir Masyarakat';
    $description = $settings['hero_description'] ?? $settings['about_short'] ?? "Situs resmi {$organization['name']} — portal informasi dan pengelolaan dana bergulir masyarakat.";
@endphp

@section('title', $title)
@section('description', $description)
@section('canonical', url('/'))
@section('twitter_card', 'summary_large_image')

@section('content')
    <h1>{{ $settings['hero_tagline'] ?? 'Situs Resmi' }}</h1>
    <p>{{ $organization['legal_name'] }}</p>
    <p>{{ $settings['hero_description'] ?? "Portal informasi resmi {$organization['name']} — pengelolaan dana bergulir masyarakat yang transparan, akuntabel, dan berorientasi pada kesejahteraan warga." }}</p>
    @if ($settings['about_short'])
        <h2>Tentang Kami</h2>
        <p>{{ $settings['about_short'] }}</p>
    @endif
    @if ($organization['address'])
        <p>{{ $organization['address'] }}</p>
    @endif
@endsection
