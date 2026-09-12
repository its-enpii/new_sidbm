@extends('public.bot.layout')

@php
    $organization = $site['organization'];
    $address = $settings['contact_address'] ?? $organization['address'];
    $phone = $settings['contact_phone'] ?? $organization['phone'];
    $email = $settings['contact_email'] ?? $organization['email'];
@endphp

@section('title', 'Kontak — '.$organization['name'])
@section('description', "Hubungi {$organization['name']} — alamat, telepon, dan formulir pesan.")
@section('canonical', url('/kontak'))

@section('content')
    <h1>Kontak Kami</h1>
    <p>Silakan hubungi kami melalui alamat, telepon, email, atau formulir di samping.</p>
    @if ($address)
        <h2>Alamat</h2>
        <p>{{ $address }}</p>
    @endif
    @if ($phone)
        <h2>Telepon</h2>
        <p><a href="tel:{{ $phone }}">{{ $phone }}</a></p>
    @endif
    @if ($email)
        <h2>Email</h2>
        <p><a href="mailto:{{ $email }}">{{ $email }}</a></p>
    @endif
@endsection
