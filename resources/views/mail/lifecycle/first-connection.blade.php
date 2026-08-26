@component('mail::message')
# Bağlantınız Kuruldu! 🔗

**{{ $connectionName }}** ({{ $marketplace }}) mağaza bağlantınız başarıyla oluşturuldu.

Sonraki adım olarak ürün kategorilerinizi ve varyant özelliklerinizi eşlemeye başlayabilirsiniz. Otonom eşleme sihirbazı, pazaryeri kategorilerini katalogdaki karşılıklarıyla hızlı ve hatasız eşleştirir.

@component('mail::button', ['url' => url('/'), 'color' => 'primary'])
Eşleme Sihirbazına Git
@endcomponent

İyi çalışmalar,<br>
**KobiConnect**
@endcomponent
