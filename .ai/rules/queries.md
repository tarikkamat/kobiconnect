---
paths:
  - 'app/Queries/**'
---

# Queries

## Rapor ve panel sorguları Query Object'te, controller'da değil
Toplama (GROUP BY / SUM) sorguları controller'a yazılmaz; `app/Queries/Reports/*` ve `app/Queries/Dashboard/*` altında kendi sınıfındadır. Controller yalnızca yetki + prop toplar.

Ortak parçalar tek yerde: tarih/kanal filtresi `ReportRange::apply()`, satır bazlı para ifadeleri `ReportQuery` sabitleri, `orders.totals` jsonb okuması `App\Queries\OrderTotals` sabitleri.

`totals` alanları hem sayı hem sayısal metin olarak yazılıyor: düz `(totals->>'x')::numeric` tek bozuk değerde ekranı düşürür. OrderTotals'daki regex'li CASE ifadesini kullanın, yenisini elle yazmayın.

`selectRaw`/`whereRaw` PHPStan'de literal-string ister — bu kasıtlı bir enjeksiyon kapısıdır. İfadeleri sabit olarak tanımlayın, değişken interpolasyonu yapmayın.
