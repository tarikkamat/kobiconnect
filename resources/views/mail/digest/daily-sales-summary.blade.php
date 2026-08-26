@component('mail::message')
# Günlük Satış Özeti 📊

Dünkü satış performansınız ve kanal metrikleriniz aşağıda özetlenmiştir:

@component('mail::table')
| Metrik | Değer |
|:-------|------:|
| Sipariş Adedi | **{{ $data['count'] }}** |
| Toplam Ciro | **{{ $data['total'] }}** |
| Ortalama Sepet | {{ $data['average'] }} |
@if($data['cancellations'] > 0)
| İptal Edilen | {{ $data['cancellations'] }} |
@endif
@endcomponent

@if($data['change'] !== null)
@component('mail::panel')
**Önceki güne göre değişim:** {{ $data['change'] }}
@endcomponent
@endif

@if(count($data['channels']) > 0)
## Kanal Dağılımı

@component('mail::table')
| Kanal | Sipariş | Ciro |
|:------|--------:|-----:|
@foreach($data['channels'] as $channel)
| {{ $channel['name'] }} | {{ $channel['count'] }} | {{ $channel['total'] }} |
@endforeach
@endcomponent
@endif

@if(count($data['topSkus']) > 0)
## En Çok Satan Ürünler

@component('mail::table')
| SKU | Ürün Adı | Adet |
|:----|:---------|-----:|
@foreach($data['topSkus'] as $sku)
| {{ $sku['sku'] }} | {{ $sku['name'] }} | {{ $sku['quantity'] }} |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => url('/'), 'color' => 'primary'])
Panelde Detaylı Raporu Gör
@endcomponent

<p class="sub">Bu bildirimi almak istemiyorsanız Ayarlar → Bildirim Tercihleri ekranından kapatabilirsiniz.</p>

İyi çalışmalar,<br>
**KobiConnect**
@endcomponent
