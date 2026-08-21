---
paths:
  - 'resources/js/**'
---

# Js

## wayfinder:generate her zaman --with-form ile çalıştırılmalı
`php artisan wayfinder:generate` bayraksız çalıştırıldığında `.form` varyantlarını repo genelindeki TÜM route'lardan siler — sadece o an üzerinde çalışılanlardan değil.

Sonuç: `settings/{profile,security,team}`, `onboarding/*` gibi alakasız sayfalarda ani TS hataları. Hata mesajı "Property 'form' does not exist" der ve sebebi hiç göstermez, o yüzden teşhisi pahalıdır.

DOĞRU: `php artisan wayfinder:generate --with-form`

`formVariants: true` `vite.config.ts`'te tanımlıdır ama bu YALNIZCA Vite eklentisi için geçerlidir; artisan komutu bayrağı ayrıca ister. Vite dev/build çalıştırınca doğru üretim geri gelir, bu yüzden hata bazen "kendiliğinden" düzelir ve tekrar ortaya çıkar.

Paralel çalışan agent'lar bu tuzağa iki kez düştü.
