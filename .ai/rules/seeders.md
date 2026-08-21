---
paths:
  - 'database/seeders/**'
---

# Seeders

## DatabaseSeeder tenant kök seeder'ıdır — demo veri koyma
`config/tenancy.php` → `seeder_parameters` `DatabaseSeeder`'ı kök tenant seeder'ı olarak gösterir ve `Jobs\SeedDatabase` HER tenant provisioning'inde çalıştırır.

Buraya yalnızca her gerçek müşterinin sahip olması gereken şeyler girer (roller/izinler). Demo veya test kullanıcısı KONMAZ — starter kit'in `test@example.com` satırı burada kalırsa her yeni müşteri şemasında tanıdık bir e-posta ve bilinen bir şifre oluşur. Testler factory kullanır, seeder'a ihtiyaç duymaz.

Central veriler (plans, plan_features) buraya EKLENMEZ; ayrı çalıştırılır: `php artisan db:seed --class=PlanSeeder`.

`seeder_parameters` içinde `'--force' => true` ZORUNLUDUR. `tenants:seed` `ConfirmableTrait` kullanır; production'da --force olmadan sessizce hiçbir şey yapmaz → roller kurulmaz → kayıtta `assignRole('Sahip')` patlar → her kayıt telafiyle geri alınır. Regresyon testi: tests/Feature/Tenancy/TenantRoleSeederTest.php

## Her tenant varsayılan bir depoyla açılır (ANA)
`DatabaseSeeder` roller dışında bir tane daha şey kurar: `code = ANA`, `is_default = true` varsayılan depo. Deposuz bir tenant çalışmaz — envanter matrisi, katalogtaki satır içi stok düzenlemesi ve stok gönderimi hepsi bir depo satırına bağlı, ve WarehouseController zaten "en az bir depo kalır" kuralını zorluyor.

`firstOrCreate` ile yazılır: seeder yeniden çalıştırılırsa ikinci depo açılmaz. Testler `TenantRoleSeeder`'ı doğrudan çağırır ve depoyu ALMAZ; depo bekleyen bir test onu factory ile kurmalı. Regresyon testi: tests/Feature/Tenancy/TenantRoleSeederTest.php ("provisioning opens a default warehouse").
