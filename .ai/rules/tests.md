---
paths:
  - 'tests/**'
---

# Tests

## Feature tests run inside a tenant
tests/Pest.php initializes tenant `test` (host test.kobiconnect.test) before every Feature test, so `route()` already yields tenant URLs — do not hand-write absolute hosts.
The tenant schema is provisioned once per process, not per test: RefreshDatabase's transaction lives on the central connection and `CREATE SCHEMA` there is invisible to the separate tenant PDO. Isolation between tests is TRUNCATE, not a transaction, because the tenant connection is purged on every initialize()/end().
TestCase::call() re-initializes tenancy after each request: the global EndTenancyAfterRequest terminating middleware ends it, and the test body still needs tenant context.
Central-domain assertions still work: hit http://kobiconnect.test/... explicitly.

## json_encode ile PII assertion yaparken JSON_UNESCAPED_UNICODE şart
`json_encode($props)` varsayılan olarak unicode kaçışı yapar: "Yılmaz" → "Yılmaz", "Ayşe" → "Ayşe".

Sonuç: `expect($props)->not->toContain('Yılmaz')` gibi negatif assertion'lar Türkçe karakter içeren HER dizede **hiçbir zaman eşleşmez** ve sızıntı olsa bile geçer. KVKK testlerinde bu yanlış güven üretir — testin geçmesi hiçbir şey kanıtlamaz.

Doğrusu: `json_encode($props, JSON_UNESCAPED_UNICODE)`.

Aynı tuzak `assertDontSee()` için de geçerli değildir (o HTML'e bakar) ama Inertia prop'larını string olarak inceleyen her testte vardır. Örnek: tests/Feature/Orders/OrderPageTest.php

İlgili: maskeleme yaparken yıldız sayısını gizlenen değerin uzunluğuna bağlama — `a**********@x.com` yerel kısmın uzunluğunu sızdırır. Sabit genişlik kullan.
