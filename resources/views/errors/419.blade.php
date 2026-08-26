@extends('errors.layout')

@section('title', 'Oturum Süresi Doldu')
@section('code', '419')
@section('message', 'Güvenliğiniz nedeniyle oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.')

@section('actions')
    <button onclick="window.location.reload()" type="button" class="btn btn-primary">Sayfayı Yenile</button>
    <a href="{{ url('/login') }}" class="btn btn-outline">Giriş Yap</a>
@endsection
