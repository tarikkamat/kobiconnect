---
paths:
  - resources/css/app.css
---

# Css

## Panel tek yonlu koyu tema — light mode geri getirilmez
Tasarim dili DESIGN.md'de. `:root` ve `.dark` AYNI koyu paleti tasir; `<html class="dark">` app.blade.php'de kosulsuzdur. `appearance` cerezi, HandleAppearance middleware'i, use-appearance hook'u ve Gorunum ayari ekrani bilerek silindi — geri ekleme.

Iki tuzak:
- `:focus-visible` mint outline kurali app.css'in SONUNDA, KATMANSIZ durur. Bilerek: shadcn primitifleri `outline-none` tasiyor ve utilities katmani `@layer base`'i yener. `@layer base` icine tasirsan odak halkasi sessizce kaybolur.
- Mint (`--primary`) DOLGU degil VURGU rengidir. Birincil buton BEYAZ zeminlidir (`bg-white text-background`). shadcn'in `bg-primary` varsayilanina geri donme.

Regresyon kilidi: tests/Feature/Smoke/DarkThemeTest.php
