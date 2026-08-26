@component('mail::message')
# Stok Uyarısı ⚠️

**{{ $data['count'] }} ürün** güvenlik stoğu sınırının altına indi. Satış kaybı yaşamamak için stok takviyesi yapmanız önerilir.

@component('mail::table')
| SKU | Ürün | Mevcut | Güvenlik Stoğu | Depo |
|:----|:-----|-------:|---------------:|:-----|
@foreach($data['items'] as $item)
| {{ $item['sku'] }} | {{ $item['name'] }} | {{ $item['available'] }} | {{ $item['safetyStock'] }} | {{ $item['warehouse'] }} |
@endforeach
@endcomponent

@if($data['count'] > count($data['items']))
_...ve {{ $data['count'] - count($data['items']) }} ürün daha kritik seviyede._
@endif

@component('mail::button', ['url' => url('/'), 'color' => 'primary'])
Stok Durumunu İncele
@endcomponent

<p class="sub">Bu bildirimi almak istemiyorsanız Ayarlar → Bildirim Tercihleri ekranından kapatabilirsiniz.</p>

İyi çalışmalar,<br>
**KobiConnect**
@endcomponent
