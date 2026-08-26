@component('mail::message')
# İlk Siparişiniz Geldi! 🎊

Tebrikler! KobiConnect üzerinden ilk siparişiniz başarıyla alındı ve sisteme işlendi.

@component('mail::table')
| Sipariş Bilgisi | Detay |
|:----------------|:------|
| **Sipariş No** | {{ $data['orderNumber'] }} |
| **Kanal** | {{ $data['channel'] }} |
| **Tutar** | {{ $data['total'] }} |
@endcomponent

@component('mail::button', ['url' => $orderUrl, 'color' => 'primary'])
Siparişi İncele
@endcomponent

İyi satışlar dileriz,<br>
**KobiConnect**
@endcomponent
