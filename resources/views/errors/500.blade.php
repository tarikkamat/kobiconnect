@extends('errors.layout')

@section('title', 'Sunucu Hatası')
@section('code', '500')
@section('message', 'Beklenmeyen bir hata oluştu. Mühendislerimiz bu durumdan haberdar edildi ve üzerinde çalışıyor.')

@section('actions')
    <button onclick="window.location.reload()" type="button" class="btn btn-primary">Sayfayı Yenile</button>
    <a href="{{ url('/') }}" class="btn btn-outline">Ana Sayfaya Dön</a>
@endsection
