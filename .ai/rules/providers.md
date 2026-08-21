---
paths:
  - app/Providers/TenancyServiceProvider.php
---

# Providers

## Tenancy geçişinde singleton bellek durumu temizlenmeli
stancl/tenancy yalnızca DB/cache/filesystem/queue'yu tenant'a bağlar. Kendi belleğinde durum tutan HER singleton ayrıca temizlenmelidir; aksi halde tenant A'nın verisi tenant B'nin isteğinde görünür (Octane altında worker ömrü boyunca).

Kanıtlanmış vaka: spatie/laravel-permission'ın PermissionRegistrar'ı izinleri `$this->permissions` içinde tutar ve `loadPermissions()` doluysa erken döner. CacheTenancyBootstrapper yalnızca cache *store*'unu etiketler, bu kopyaya dokunmaz. Çözüm: App\Listeners\FlushPermissionCache, TenancyInitialized VE TenancyEnded olaylarına bağlı.

Yeni bir paket eklerken sor: bu paket singleton'da state tutuyor mu? Tutuyorsa aynı listener kalıbını uygula ve tests/Feature/Tenancy/PermissionCacheIsolationTest.php'deki gibi bir sızıntı testi yaz.

Test yazarken tuzak: `Tenant::create` provisioning pipeline'ını (TenantRoleSeeder dahil) çalıştırır, o da cache'i düşürür ve sızıntıyı maskeler. Diğer tenant'ı registrar'ı ısıtmadan ÖNCE yarat.
