@extends('errors.layout')

@section('title', $exception?->getMessage() ?: 'Hata')
@section('code', $exception?->getStatusCode() ?: '500')
@section('message', $exception?->getMessage() ?: 'Beklenmeyen bir hata oluştu.')
