<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Panel TEK YONLU koyu temadir (DESIGN.md). Tema secici, `appearance` cerezi ve
 * light paleti bilerek silindi.
 *
 * Bu test o karari kilitler: birisi shadcn starter kit'inden gelen tema
 * anahtarini geri getirirse ya da <html> uzerindeki `dark` sinifi kosullu hale
 * gelirse, koyu token'lar uzerine acik palet biner ve panel yariya kadar beyaz
 * render olur — gozle fark edilmesi kolay ama testsiz yakalanmasi zor bir hata.
 */
it('kabuk her zaman koyu tema ile render olur', function (): void {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    // Locale'den bagimsiz: <html> etiketinde `dark` sinifi KOSULSUZ durmali.
    expect($html)->toMatch('/<html[^>]*\sclass="dark"/')
        ->and($html)->toContain('background-color: #0a0b0f');
});

it('gorunum ayari ekrani kaldirildi', function (): void {
    expect(Route::has('appearance.edit'))->toBeFalse();
});
