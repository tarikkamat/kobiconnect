---
paths:
  - 'app/Support/TenantUserProvider.php, config/auth.php'
---

# Support

## Central isteklerde session'dan user cozulmez
auth.providers.users.driver = 'tenant-eloquent' (App\Support\TenantUserProvider, AppServiceProvider'da kayitli). Tenant baslatilmamisken retrieveById/retrieveByToken sorgu atmadan null doner — central'da users tablosu yok, tek host = tek session cookie oldugundan paneldeki oturum central sayfalarda "relation users does not exist" uretirdi. retrieveByCredentials bilerek korunmaz: giris akislari tenant baglaminda calismak zorundadir ve central'da yuksek sesle patlamalidir. Driver'i 'eloquent'e geri cevirme.
