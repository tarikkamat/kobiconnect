---
paths:
  - resources/js/components/ui/table.tsx
---

# Ui

## Tablo cercevesi sayfada, tabloda degil
`<Table>` KENDI dis cercevesini cizmez. Cerceve sayfa seviyesinde tabloyu saran div'dedir: `overflow-hidden rounded-lg border border-border`. Ikisine birden koyarsan cift kenar cikar.

Tablo basligi Geist Mono 10px / `tracking-[0.1em]` / uppercase; icindeki siralama butonlari bu tipografiyi preflight uzerinden MIRAS ALIR, tekrar tanimlama.

Sayisal her hucre (SKU, stok, fiyat, siparis no, tarih, yuzde) `font-mono tabular-nums` alir — Inter ile yazilan sayi sutunu kaydirir. DataTable kullanan sayfalarda bunu kolon `meta.className` uzerinden ver.
