---
paths:
  - config/passport.php
---

# Config

## OAuth sunucusu tenant basinadir; Passport anahtar yolu elle sabitlenir
MCP sunucusu Passport ile korunur ve HER TENANT KENDI yetkilendirme sunucusudur: issuer /{tenant}, passport.path = '{tenant}/oauth', oauth_* tablolari database/migrations/tenant/ altinda. Central'da kullanici olmadigi icin tek merkezi bir AS token'i hangi tenant'in user_id'sine yazacagini bilemezdi. Merkezilestirme yonunde degistirme.

passport.middleware panel yigininin AYNISI olmak zorunda ve ScopeSessions kozmetik degil: onsuz A tenant'inda oturumu olan biri /B/oauth/authorize adresine gidip `auth`in ayni user id'yi B'nin semasinda cozmesiyle BASKA birinin hesabina token verdirebilir.

Tuzak: Passport anahtarlari varsayilan olarak storage_path() altinda aranir, FilesystemTenancyBootstrapper ise o yolu tenant basina soneklendirir (storage/tenant{id}/). AppServiceProvider'daki Passport::loadKeysFrom(base_path('storage')) olmadan her tenant istegi "Invalid key supplied" ile 500 doner. Imza anahtari uygulamanin tamamina aittir, tenant'a degil.

POST {tenant}/oauth/token CSRF muafiyetindedir (bootstrap/app.php): Passport'un grubu `web` tasir, token takasini ise istemci sunucusu yapar. {tenant}/oauth/authorize POST'u BILEREK muaf degildir, o gercek bir tarayici formudur.

Deploy: `php artisan passport:keys` ve yeni/mevcut tenant'lar icin `php artisan tenants:migrate` sart.

Testte ikinci bir tenant kurarsan sonunda delete() et — sema surece bagli yasar ve birakirsan baska testler "database already exists" ile patlar.
