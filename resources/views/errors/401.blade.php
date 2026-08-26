@extends('errors.layout')

@section('title', 'Yetkisiz Erişim')
@section('code', '401')
@section('message', 'Bu sayfayı görüntülemek veya bu işlemi gerçekleştirmek için lütfen önce giriş yapın.')

@section('actions')
    <a href="{{ url('/login') }}" class="btn btn-primary">Giriş Yap</a>
    <a href="{{ url('/') }}" class="btn btn-outline">Ana Sayfaya Dön</a>
@endsection
