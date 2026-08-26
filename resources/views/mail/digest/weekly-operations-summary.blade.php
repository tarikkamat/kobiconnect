@component('mail::message')
# Haftalık Operasyon Özeti ⚙️

Son 7 güne ait mağaza bağlantı durumları ve senkronizasyon operasyonları aşağıda özetlenmiştir:

## Bağlantı Durumları

@component('mail::table')
| Kanal | Pazaryeri | Durum |
|:------|:----------|:------|
@foreach($data['connections'] as $conn)
| {{ $conn['name'] }} | {{ $conn['marketplace'] }} | {{ $conn['status'] }} |
@endforeach
@endcomponent

## Operasyonel Sorunlar

@component('mail::table')
| Gösterge | Son 7 Gün |
|:---------|----------:|
| Başarısız Senkron | {{ $data['failedSyncs'] }} |
| Reddedilen Ürün | {{ $data['rejectedProducts'] }} |
| Webhook Sorunu | {{ $data['webhookIssues'] }} |
@endcomponent

@component('mail::button', ['url' => url('/'), 'color' => 'primary'])
Senkron Monitörüne Git
@endcomponent

<p class="sub">Bu bildirimi almak istemiyorsanız Ayarlar → Bildirim Tercihleri ekranından kapatabilirsiniz.</p>

İyi çalışmalar,<br>
**KobiConnect**
@endcomponent
