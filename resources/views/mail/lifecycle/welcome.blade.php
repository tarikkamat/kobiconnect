@component('mail::message')
# KobiConnect'a Hoş Geldiniz! 🎉

Merhaba **{{ $userName }}**,

KobiConnect hesabınız başarıyla oluşturuldu. Pazaryeri entegrasyonlarınızı ve e-fatura süreçlerinizi tek bir panelden otonom olarak yönetmeye hemen başlayabilirsiniz.

## Hızlı Başlangıç

**1. Pazaryeri Bağlayın** — Trendyol, Hepsiburada veya diğer pazaryeri mağazalarınızı bağlayın.

**2. Ürünlerinizi Eşleyin** — Kategorileri ve özellikleri otonom eşleme sihirbazıyla hızlıca eşleyin.

**3. Siparişleri Takip Edin** — Tüm kanallardan gelen sipariş ve stokları gerçek zamanlı yönetin.

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'primary'])
Panele Git
@endcomponent

Sorularınız veya kurulum desteği için dilediğiniz zaman bizimle iletişime geçebilirsiniz.

İyi çalışmalar,<br>
**KobiConnect**
@endcomponent
