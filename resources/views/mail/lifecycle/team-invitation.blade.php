@component('mail::message')
# Ekibe Davet Edildiniz 👥

Merhaba **{{ $userName }}**,

**{{ $tenantName }}** sizi KobiConnect ekibine davet etti.

Size atanan rol: **{{ $roleName }}**

Hesabınıza erişmek ve çalışmalarınıza başlamak için aşağıdaki butona tıklayarak giriş yapabilirsiniz.

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Panele Gir
@endcomponent

İyi çalışmalar,<br>
**KobiConnect**
@endcomponent
