@extends('errors.layout')

@section('title', 'Bakım Modu')
@section('code', '503')
@section('message', 'Sistemimiz şu anda planlı bir bakım veya güncelleme çalışması nedeniyle geçici olarak hizmet verememektedir.')

@section('actions')
    <button onclick="window.location.reload()" type="button" class="btn btn-primary">Tekrar Dene</button>
@endsection
