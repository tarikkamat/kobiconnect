<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('404 blade sablonu koyu tema ve dogru mesajlarla render edilir', function (): void {
    $view = View::make('errors.404')->render();

    expect($view)->toContain('class="dark"')
        ->and($view)->toContain('background-color: #0a0b0f')
        ->and($view)->toContain('HTTP 404')
        ->and($view)->toContain('Sayfa Bulunamadı')
        ->and($view)->toContain('KobiConnect');
});

test('500 blade sablonu koyu tema ve yenile butonu ile render edilir', function (): void {
    $view = View::make('errors.500')->render();

    expect($view)->toContain('class="dark"')
        ->and($view)->toContain('HTTP 500')
        ->and($view)->toContain('Sunucu Hatası')
        ->and($view)->toContain('Sayfayı Yenile');
});

test('403 blade sablonu dogru render edilir', function (): void {
    $view = View::make('errors.403')->render();

    expect($view)->toContain('HTTP 403')
        ->and($view)->toContain('Erişim Engellendi');
});

test('419 blade sablonu dogru render edilir', function (): void {
    $view = View::make('errors.419')->render();

    expect($view)->toContain('HTTP 419')
        ->and($view)->toContain('Oturum Süresi Doldu');
});

test('503 blade sablonu dogru render edilir', function (): void {
    $view = View::make('errors.503')->render();

    expect($view)->toContain('HTTP 503')
        ->and($view)->toContain('Bakım Modu');
});

test('429 blade sablonu dogru render edilir', function (): void {
    $view = View::make('errors.429')->render();

    expect($view)->toContain('HTTP 429')
        ->and($view)->toContain('İstek Limiti Aşıldı');
});

test('401 blade sablonu dogru render edilir', function (): void {
    $view = View::make('errors.401')->render();

    expect($view)->toContain('HTTP 401')
        ->and($view)->toContain('Yetkisiz Erişim');
});
