@extends('public.bot.layout')

@php $organization = $site['organization']; @endphp

@section('title', 'Berita — '.$organization['name'])
@section('description', "Berita terbaru dari {$organization['name']} — informasi kegiatan dan pengumuman.")
@section('canonical', url('/berita'))

@section('content')
    <h1>Kabar Terbaru dari {{ $organization['name'] }}</h1>
    <p>Informasi kegiatan, pengumuman, dan laporan seputar pengelolaan dana bergulir masyarakat.</p>
    @forelse ($posts['data'] as $post)
        <article>
            <h2><a href="{{ url('/berita/'.$post['slug']) }}">{{ $post['title'] }}</a></h2>
            @if ($post['excerpt'])
                <p>{{ $post['excerpt'] }}</p>
            @endif
        </article>
    @empty
        <p>Belum ada berita yang dipublikasikan.</p>
    @endforelse
@endsection
