@extends('errors.layout')

@section('title', 'İstek Limiti Aşıldı')
@section('code', '429')
@section('message', 'Kısa süre içerisinde çok fazla işlem yaptınız. Lütfen biraz bekleyip tekrar deneyin.')

@section('actions')
    <button onclick="window.location.reload()" type="button" class="btn btn-primary">Sayfayı Yenile</button>
    <a href="{{ url('/') }}" class="btn btn-outline">Ana Sayfaya Dön</a>
@endsection
