@component('mail::message')
# Haftalık Performans Raporu 📈

**{{ $data['period'] }}** dönemine ait haftalık mağaza ve satış performans özetiniz:

## Satış Özeti

@component('mail::table')
| Metrik | Değer |
|:-------|------:|
| Sipariş Adedi | **{{ $data['orders']['count'] }}** |
| Toplam Ciro | **{{ $data['orders']['total'] }}** |
| Ortalama Sepet | {{ $data['orders']['average'] }} |
@endcomponent

@if($data['orders']['change'] !== null)
@component('mail::panel')
**Önceki haftaya göre değişim:** {{ $data['orders']['change'] }}
@endcomponent
@endif

@if(count($data['channels']) > 0)
## Kanal Karşılaştırması

@component('mail::table')
| Kanal | Sipariş | Ciro |
|:------|--------:|-----:|
@foreach($data['channels'] as $channel)
| {{ $channel['name'] }} | {{ $channel['count'] }} | {{ $channel['total'] }} |
@endforeach
@endcomponent
@endif

@if(count($data['topProducts']) > 0)
## En Çok Satan 5 Ürün

@component('mail::table')
| SKU | Ürün Adı | Adet | Ciro |
|:----|:---------|-----:|-----:|
@foreach($data['topProducts'] as $product)
| {{ $product['sku'] }} | {{ $product['name'] }} | {{ $product['quantity'] }} | {{ $product['total'] }} |
@endforeach
@endcomponent
@endif

## Operasyonel Durum

@component('mail::table')
| Gösterge | Değer |
|:---------|------:|
| İade Talebi | {{ $data['claims']['count'] }} ({{ $data['claims']['total'] }}) |
| Kritik Stok | {{ $data['criticalStock'] }} ürün |
| Başarısız Senkron | {{ $data['failedSyncs'] }} |
| Hatalı Bağlantı | {{ $data['erroredConnections'] }} |
@endcomponent

@component('mail::button', ['url' => url('/'), 'color' => 'primary'])
Haftalık Raporu Panelde İncele
@endcomponent

<p class="sub">Bu bildirimi almak istemiyorsanız Ayarlar → Bildirim Tercihleri ekranından kapatabilirsiniz.</p>

İyi çalışmalar,<br>
**KobiConnect**
@endcomponent
