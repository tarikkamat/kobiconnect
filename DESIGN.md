# KobiConnect — Tasarım Dili

Kaynak: `src/input.css` (`@theme`) + `index.html`. Değerler mintlify.com'dan
`getComputedStyle` ile ölçülmüş, tahmin değil. Bu dosya landing'in dilini
shadcn/ui panelinin değişken sözleşmesine çevirir.

**Tema tek yönlü: karanlık.** Panelde light mode yok. `:root` ve `.dark` aynı
paleti taşır ki shadcn bileşenleri hangi sınıf altında olursa olsun doğru çıksın.

---

## 1. Panele yapıştırılacak blok

`globals.css` içindeki shadcn değişken bloğunu bununla değiştir:

```css
:root, .dark {
  --background: #0a0b0f;
  --foreground: #faf8f5;

  --card: #0f0f12;
  --card-foreground: #faf8f5;
  --popover: #0f0f12;
  --popover-foreground: #faf8f5;

  --primary: #18e299;
  --primary-foreground: #04140d;

  --secondary: #141519;          /* beyaz %4 zemin üstünde */
  --secondary-foreground: #faf8f5;

  --muted: #111216;              /* beyaz %3 — hücre dolgusu (--wash) */
  --muted-foreground: #cfcdca;

  --accent: #0c1818;             /* mint-tint, opak karşılığı */
  --accent-foreground: #18e299;

  --destructive: #f04438;        /* markada yok, eklendi — §7 */
  --destructive-foreground: #faf8f5;

  --border: #1e1f21;             /* --line; beyaz %8'e denk */
  --input: #1e1f21;
  --ring: #18e299;

  --radius: 0.375rem;            /* sm 2 · md 4 · lg 6 · xl 10 */

  --chart-1: hsl(158 84% 46%);
  --chart-2: hsl(141 84% 50%);
  --chart-3: hsl(125 84% 53%);
  --chart-4: hsl(108 84% 55%);
  --chart-5: hsl(92  84% 58%);

  --sidebar: #0a0b0f;            /* içerikle aynı zemin, ayrım 1px border */
  --sidebar-foreground: #cfcdca;
  --sidebar-primary: #18e299;
  --sidebar-primary-foreground: #04140d;
  --sidebar-accent: #0c1818;
  --sidebar-accent-foreground: #18e299;
  --sidebar-border: #1e1f21;
  --sidebar-ring: #18e299;
}
```

Yarı saydam olanların opak karşılıkları yukarıda; üst üste binen katman
varsa (dropdown, sheet) landing'in orijinal `rgba` değerlerini kullan:

| Rol | rgba | Nerede |
|---|---|---|
| `--hair` | `rgba(255,255,255,.08)` | hücre + alıntı kenarı |
| `--tile-line` | `rgba(255,255,255,.07)` | bento kutu kenarı, nav buton kenarı |
| `--wash` | `rgba(255,255,255,.03)` | hücre dolgusu, tablo başlık satırı |
| hover katmanı | `rgba(255,255,255,.04–.06)` | satır/buton hover |
| üçüncül metin | `rgba(255,255,255,.30)` | placeholder, pasif menü, meta |
| pasif metin | `rgba(255,255,255,.25)` | devre dışı menü grubu |

---

## 2. Tipografi

| Rol | Aile | Değer |
|---|---|---|
| Display | Petrona (serif) | 50/52, w400, ls −0.04em, `#ffffff` |
| h2 | Inter | 36/40, w500, ls −0.72px — **iki tonlu**: iddia `--foreground`, niteleyici `--muted-foreground` |
| h2-sm | Inter | 24/28, w500, ls −0.24px, beyaz %60; `<strong>` tam beyaz, w500 |
| Gövde | Inter | 16/1.5 |
| Küçük | Inter | 14/1.5 |
| Mono | Geist Mono | 12, w500, ls 0.02em |
| Tablo başlığı | Geist Mono | 10, ls 0.1em, `--muted-foreground` |

Panelde kurallar:
- **Petrona sadece boş durumlar, onboarding ve pazarlama yüzeylerinde.** Tablo,
  form, menü hiçbir yerde serif kullanmaz.
- **Sayısal her şey mono.** Stok, fiyat, SKU, sipariş no, tarih, yüzde.
  `tabular-nums` ile hizala. Sayıyı Inter ile yazma — sütun kayar.
- Metrik kartlarındaki büyük rakam: Inter değil, **Geist Mono 28–32 w500**.

Font yüklemesi landing ile aynı olsun (Inter, Petrona, Geist Mono).
Değişkenler: `--font-sans: Inter`, `--font-serif: Petrona`, `--font-mono: 'Geist Mono'`.

---

## 3. Yarıçap ve yoğunluk

`--radius: 0.375rem` shadcn'in dört basamağını landing ölçülerine oturtur:

| shadcn | px | Landing karşılığı |
|---|---|---|
| `rounded-sm` | 2 | odak halkası, minik rozet |
| `rounded-md` | 4 | **buton, input, select, tab** |
| `rounded-lg` | 6 | hücre / cell, tablo çerçevesi |
| `rounded-xl` | 10 | kart (landing'de 12 — Card'ı `rounded-[12px]` ile eşitle) |

Yükseklikler:

| Öğe | Landing | Panelde |
|---|---|---|
| Ana CTA butonu | 42 | sadece pazarlama/boş durum CTA'sı |
| Nav butonu (`btn-sm`) | 34 | **panelin varsayılan buton boyu** — shadcn `default` h-9'u 34'e çek |
| İkon buton | 42×42 | panelde 34×34 |
| Pill / rozet | 30, `rounded-full` | durum damgası |
| Input | — | 34, `rounded-md`, kenar `--input` |

Buton varyantları landing'den birebir:
- `default` → beyaz zemin `#ffffff`, metin `#0a0b0f`, hover `#cfcdca`
- `outline` → 1px `rgba(255,255,255,.1)`, zemin `--background`, hover zemin %4 + kenar %20
- `ghost` → hover zemin %5
- `secondary` → `--secondary`

**Chevron kuralı:** landing'de her butonun sonunda 5px `›` var. Panelde bunu
taşıma — sadece bir yere *götüren* butonlarda (link-buton, "Tüm siparişler")
kalsın. Kaydet/Sil gibi eylem butonlarında chevron yok.

---

## 4. Odak, seçim, hareket

```css
:focus-visible { outline: 2px solid #18e299; outline-offset: 3px; border-radius: 2px; }
::selection    { background: #18e299; color: #04140d; }
```

shadcn'in `ring-ring/50 ring-[3px]` odak stilini bununla değiştir — mint outline
markanın imzası ve klavye erişilebilirliğinin tabanı, kırpma.

Geçişler: **150ms ease**, sadece `background-color`, `border-color`, `color`.
Kart/tablo hover'ı 200ms. `prefers-reduced-motion: reduce` altında hepsi durur —
panelde de aynı blok bulunsun.

---

## 5. Bileşen eşlemesi

Hero'daki panel mockup'ı (`index.html:319`) panelin ne olması gerektiğinin
referansı. Oradan çıkan kurallar:

**Sidebar** — 210px sabit, sağda 1px `--border`, iç boşluk 12px.
Üstte logo: 8px mint nokta + `kobiconnect` 13px w600 ls −0.02em.
Menü öğesi 12px, `rounded-md`, `px-2 py-1.5`, satır arası 2px.
Pasif: `--sidebar-foreground`. **Aktif: zemin `--sidebar-accent`, metin mint** —
sol çubuk veya dolu zemin yok, sadece bu.
İkincil grup üstte 1px ayraç + `rgba(255,255,255,.25)`.

**Tablo** — dış çerçeve 1px `--border` + `rounded-lg`, `overflow-hidden`.
Başlık satırı: zemin `--wash`, alt kenar `--border`, mono 10px ls 0.1em.
Satır ayracı 1px `--border`, hover zemin `rgba(255,255,255,.04)`.
Henüz bağlanmamış kanal sütunu `rgba(255,255,255,.20)` ile soluk kalır.

**Tab** — alt kenarlı, kapsül değil. Aktif: `border-b` mint + mint metin.
Pasif: `--muted-foreground`, kenarsız.

**Card** — zemin `--card`, kenar `--tile-line`, yarıçap 12, iç boşluk 32
(yoğun panelde 20–24). Landing'in bento kuralı: **görsel üstte, etiket dipte**
(`mt-auto pt-8`, 16px w500). Metrik kartlarında da aynı: rakam üstte, etiket altta.

**Durum damgası** — mint nokta (6px) + mono 10–12px metin.
`● senkron açık · Trendyol dinleniyor` formatı. Renkli rozet enflasyonu yok:
normal = mint, geri kalan §7.

**Komut/arama** — `rounded-md`, kenar `--border`, 11px, `Ara ⌘K`,
metin `rgba(255,255,255,.30)`.

**Boş durum** — tek yer Petrona serbest: 24–32px display başlık +
`--muted-foreground` gövde + tek beyaz buton.

**Grafik** — `--chart-1..5` yeşilden sarımsıya giden tel demeti gradyanı.
Izgara çizgileri `--border`, eksen etiketleri mono 10px. Alan dolgusu
`rgba(24,226,153,.07)` — hero'nun radial glow'uyla aynı.

---

## 6. Izgara — panele taşınan tek şey

Landing'in 1088px / 24 kolon çerçevesi ve 160px kuyruk boşluğu **panele
taşınmaz**; panel akışkan genişlikte. Taşınan sadece:
- İçerik ile kenar arasındaki 1px ayraç mantığı (gölge değil, çizgi).
- **Gölge yok.** Landing hiçbir yerde `box-shadow` kullanmıyor; panelde de
  yükseklik çizgiyle ve zemin tonuyla anlatılır. shadcn'in `shadow-sm`,
  `shadow-md` varsayılanlarını sil, yerine `border` koy.
- Yatay taşan listelerde `scroll-snap-type: x mandatory` + gizli scrollbar.

---

## 7. Markada olmayan, eklenmesi gereken renkler

Landing tek aksanlı (mint). Panelde durum bildirimi şart, o yüzden minimum set:

| Durum | Renk | Zemin (%10) |
|---|---|---|
| Başarılı / senkron | `#18e299` | `rgba(24,226,153,.10)` |
| Uyarı / eşleşmemiş | `#f5b544` | `rgba(245,181,68,.10)` |
| Hata | `#f04438` | `rgba(240,68,56,.10)` |
| Bilgi / beklemede | `#cfcdca` | `rgba(255,255,255,.06)` |

Bu üç renk ölçüm değil, tercih. Landing'e de geri taşınacaksa
`src/input.css` `@theme` bloğuna eklenmeli ki tek kaynak kalsın.

---

## 8. Refactor kontrol listesi

1. `globals.css` değişken bloğunu §1 ile değiştir, light mode bloğunu sil.
2. Font üçlüsünü yükle, `--font-sans/serif/mono` bağla.
3. `--radius: 0.375rem`, Card'ı `rounded-[12px]` yap.
4. Buton default yüksekliği 34, varyant renkleri §3.
5. Tüm `shadow-*` sınıflarını `border border-border` ile değiştir.
6. `focus-visible` stilini mint outline'a çevir (§4).
7. Sidebar'ı §5'teki aktif-öğe kuralına getir.
8. Tablo/metrik sayılarını mono + `tabular-nums` yap.
9. `prefers-reduced-motion` bloğunu ekle.
10. Rozet renklerini §7 setine indir.
