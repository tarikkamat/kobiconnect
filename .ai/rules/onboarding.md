---
paths:
  - 'app/Actions/Onboarding/**'
---

# Onboarding

## Tenant id'si sequence'ten gelen sira numarasidir
Tenant kimligi = URL'in ilk path segmenti (/1001/dashboard). Firma adindan slug TURETILMEZ; `App\Support\SequentialTenantIdGenerator` central `tenant_ids` sequence'inden alir (1001'den baslar) ve `config/tenancy.php` id_generator'a baglidir. Stancl GeneratesIds yalnizca id BOS birakilinca calisir, bu yuzden testler hala sabit id verebilir (TestCase::TENANT_ID = 'test').

Sonuclari: rezerve slug listesi ve slug-carpisma dongusu YOK (sayisal segment `login`/`register` gibi central route'larla cakisamaz); `domains` tablosuna kayit atilmaz, kayit akisinda kimse okumaz. Kayit sonrasi id'yi ogrenmek icin RegisterTenant `[User, Tenant]` doner. Tenant adi `tenants.data` jsonb icindedir — sorgularken `where('data->name', ...)`.
