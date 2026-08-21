# KobiConnect — Frontend Mimari Planı

> Durum: taslak · Inertia v3.7.0 · React 19 (React Compiler açık) · Tailwind v4 · TypeScript strict
> Kardeş dokümanlar: [`BACKEND-PLAN.md`](./BACKEND-PLAN.md) · [`TRENDYOL.md`](./TRENDYOL.md)

---

## 0. Mevcut zemin

Starter kit sağlam ve modern; sıfırdan kurmak yerine **üzerine inşa edilecek**.

| Alan | Durum |
|---|---|
| Inertia | **v3.7.0** — `@inertiajs/vite` eklentisi, sayfa başına `@vite` ile otomatik kod bölme |
| React | 19 + `babel-plugin-react-compiler` **açık** — manuel `useMemo`/`useCallback` gerekmiyor |
| Layout | `app.tsx`'te **merkezî çözümleyici** (`layout:` switch); sayfalar statik `Page.layout = {…}` ile prop veriyor; iç içe layout dizi olarak (`[AppLayout, SettingsLayout]`) |
| Tailwind | v4, CSS-first (`@theme`), oklch token'ları, `.dark` sınıf tabanlı dark mode, `tailwind.config.js` **yok** |
| Bileşenler | 26 shadcn bileşeni (`new-york`, `neutral`, lucide) |
| Route tipleri | Wayfinder (`formVariants: true`) — `@/routes/*` ve `@/actions/*` üretiliyor |
| Flash | `Inertia::flash('toast', …)` → `router.on('flash')` → sonner köprüsü **hazır** (`use-flash-toast.ts`) |
| Paylaşılan prop tipleri | Modül augmentation ile (`declare module '@inertiajs/core'` → `InertiaConfig.sharedPageProps`), generic gerekmiyor |
| Auth UI | Login, register, 2FA, passkey (`@laravel/passkeys`), şifre sıfırlama — tamamı `<Form>` bileşeniyle |

### Düzeltilecek tutarsızlıklar

1. **`types/auth.ts` `Auth.user`'ı non-nullable tipliyor** ama `HandleInertiaRequests` misafirler için `null` paylaşıyor. Bileşenler zaten `auth.user?` ile korunuyor — tip yalan söylüyor. `User | null` yapılacak.
2. **`config/inertia.php` SSR'ı açık ama `resources/js/ssr.tsx` yok.** Bu bir kapalı panel; SEO gereksinimi yok. **SSR kapatılacak** (yapılandırma yalanını sürdürmektense).
3. `pages/settings/profile.tsx` içinde gereksiz yerel `type PageProps = { auth: Auth }` var — modül augmentation zaten sağlıyor, kaldırılacak.
4. `layouts/app/app-header-layout.tsx` ve `layouts/auth/auth-{card,split}-layout.tsx` hiç kullanılmıyor. Kullanılacaksa bağlanacak, kullanılmayacaksa silinecek — ölü layout taşınmaz.

---

## 1. Bilgi mimarisi

Kenar çubuğu bugün tek bir "Dashboard" öğesi içeriyor. Hedef yapı:

```
Panel
├── Gösterge Paneli
├── Katalog
│   ├── Ürünler            (liste · detay · varyantlar · toplu düzenleme)
│   ├── Markalar
│   └── Kategoriler
├── Envanter
│   ├── Stok Durumu
│   └── Depolar
├── Kanallar
│   ├── Bağlantılar        (kimlik bilgisi, sağlık, webhook durumu)
│   ├── Eşleme             (kategori · özellik · marka sihirbazı)
│   └── Listelemeler       (varyant ↔ uzak listeleme, senkron durumu)
├── Satış
│   ├── Siparişler
│   ├── Kargo / Paketler
│   └── İadeler
├── Müşteri
│   └── Sorular
├── Operasyon
│   ├── Senkron Monitörü
│   └── İşlem Kuyruğu      (channel_operations defteri)
└── Ayarlar
    ├── Profil · Güvenlik · Görünüm   (mevcut)
    ├── Ekip & Roller
    ├── Bildirim Tercihleri
    └── Lisans & Kullanım
```

Menü öğeleri **yetki ve yeteneğe göre filtrelenir**: bir kanal soru-cevap desteklemiyorsa (`SupportsQuestions` implement etmiyorsa) "Sorular" görünmez. Bu bilgi `channel_connections.capabilities`'ten paylaşılan prop'a taşınır.

`components/nav-main.tsx` bugün düz bir liste; iç içe gruplar için `Collapsible` (zaten kurulu) ile genişletilecek.

---

## 2. Paylaşılan prop sözleşmesi

`types/global.d.ts`'teki augmentation genişletilir:

```ts
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: { user: User | null };          // null'a düzeltildi
            sidebarOpen: boolean;
            tenant: { id: string; name: string; logoUrl: string | null } | null;
            license: {
                plan: string;
                status: 'active' | 'grace' | 'expired' | 'suspended';
                endsAt: string | null;
                readOnly: boolean;
                quotas: Record<string, { used: number; max: number | null }>;
            } | null;
            permissions: string[];
            [key: string]: unknown;
        };
    }
}
```

`license.readOnly` tüm yazma aksiyonlarının devre dışı bırakılmasını sürer (§6). `permissions` düz bir dize dizisidir; `usePermission('products.update')` kancası okur.

**Kural:** paylaşılan prop'lar her yanıtta gider. Ağır olan hiçbir şey buraya konmaz — bildirim sayacı bile `Inertia::optional()` ile ayrı çekilir.

---

## 3. Inertia v3 özelliklerinin kullanımı

Bugün **hiçbiri kullanılmıyor** (`useForm` bile — tüm formlar `<Form>` bileşeniyle, ki doğru). Nereye uygulanacağı:

| Özellik | Nerede | Neden |
|---|---|---|
| **`<Deferred>`** | Gösterge paneli widget'ları (satış grafiği, düşük stok listesi, senkron sağlığı) | İlk boyama pazaryeri özetini beklemesin. Her biri **pulsing skeleton** ile (`skeleton.tsx` kurulu) |
| **`<InfiniteScroll>`** | Sipariş listesi, ürün listesi, işlem kuyruğu | Sayfalama tıklaması olmadan büyük listeler |
| **`usePoll`** | Senkron monitörü, batch işlem ilerlemesi, webhook sağlık rozeti | Trendyol batch sonuçları asenkron gelir (§`BACKEND-PLAN` 7.2); UI'ın canlı olması gerekir. Sayfa görünürlüğüne bağlı durdurma ile |
| **`optimistic`** | Satır içi stok ve fiyat düzenleme | En sık yapılan işlem. Başarısızlıkta otomatik geri alma; bir listede 50 hücre düzenlerken her biri için tam sayfa yenilemesi kabul edilemez |
| **`instant`** | Liste → detay geçişleri (ürün, sipariş) | Anında algılanan gezinme |
| **`<WhenVisible>`** | Ürün detayındaki "kanal listelemeleri" ve "geçmiş" sekmeleri | Sekmeye inilmeden veri çekilmesin |
| **`useHttp`** | Arka plan aksiyonları: "şimdi senkronla", "webhook'u yeniden etkinleştir", "bağlantıyı test et" | Sayfa gezinmesi olmadan istek; zaten `use-two-factor-auth.ts`'te bu desen var |
| **`prefetch`** | Kenar çubuğu bağlantıları | Zaten kullanılıyor, korunacak |

**`optimistic` kullanım sınırı:** yalnızca sonucu öngörülebilir işlemlerde. Stok değeri değiştirmek öngörülebilir; "ürünü Trendyol'a gönder" **değildir** (sonuç 4 saat sonra item bazında gelir). İkincisi asla iyimser gösterilmez — kuyruğa alındığı dürüstçe söylenir.

---

## 4. Kritik ekranlar

### 4.1 Kanal eşleme sihirbazı — en zor ekran

Bu ekran ürünün ayakta kalıp kalmayacağını belirler. Kullanıcı, kendi kategorisini ve özelliklerini pazaryerinin kategorisine ve özelliklerine bağlar.

Adımlar:

1. **Kategori eşleme** — kendi kategori ağacı solda, pazaryeri ağacı sağda. Arama, otomatik öneri (isim benzerliği), **yalnızca yaprak kategori seçilebilir** (Trendyol kısıtı — üst kategori seçilirse ürün aktarılamaz).
2. **Özellik eşleme** — seçilen pazaryeri kategorisinin özellik listesi çekilir ve bayrakları **görsel olarak** yansıtılır:
   - `required` → zorunlu rozeti, eşlenmeden ilerlenemez
   - `allowCustom: false` → serbest metin girişi **kapalı**, yalnızca listeden seçim
   - `allowMultipleAttributeValues: false` → tek seçim kontrolü
   - `varianter` → "bu varyantı belirler" rozeti; kategori başına **tam bir tane**
   - `slicer` → "ayrı ürün kartı açar" uyarısı
   - `varianter`/`slicer` **onaydan sonra değiştirilemez** → onaylı ürünlerde bu alanlar kilitli gösterilir, kilit sebebi tooltip'te
3. **Değer eşleme** — kendi özellik değerleri ↔ pazaryeri değerleri. Otomatik eşleşenler önceden işaretli, kullanıcı sadece farkları çözer.
4. **Marka eşleme** — pazaryeri marka araması **büyük/küçük harf duyarlıdır**; arama kutusunda bu belirtilir, eşleşme yoksa "marka oluştur" akışı sunulur.
5. **Önizleme** — gönderilecek payload'ın insan-okunur özeti + yerel doğrulama sonuçları (`BACKEND-PLAN` §7.5). Kullanıcı 4 saat bekleyip "reddedildi" görmemeli.

Durum yönetimi: sihirbaz uzun ve kesintiye uğrayabilir. `useRemember` ile adım durumu korunur, sunucu tarafında taslak eşleme kaydedilir.

### 4.2 Ürünler

- **Liste**: `<InfiniteScroll>`, sunucu tarafı arama (PG FTS — `BACKEND-PLAN` §5.1), kanal/durum/stok filtreleri, sütun görünürlüğü.
- **Satır içi düzenleme**: stok ve fiyat hücreleri `optimistic` ile. Toplu seçim → toplu fiyat/stok işlemi.
- **Detay**: sekmeler — Genel · Varyantlar · Görseller · Kanal Listelemeleri (`<WhenVisible>`) · Geçmiş (`<WhenVisible>`).
- **Kanal listeleme rozeti**: her varyant için kanal başına durum (gönderilmedi · kuyrukta · beklemede · onaylı · reddedildi). Reddedilme sebebi **tıklanabilir** ve doğrudan düzeltme formuna götürür.

**Sanallaştırma eşiği**: 200 satırı aşan tablolarda satır sanallaştırma devreye girer. Altında sanallaştırma **eklenmez** — erken optimizasyon ve erişilebilirlik maliyeti.

### 4.3 Siparişler ve kargo

- Liste `<InfiniteScroll>` + durum filtreleri (kanonik durumlar, pazaryeri ham durumları değil).
- **`PENDING_PAYMENT` (Trendyol `Awaiting`) ayrı ve görsel olarak farklı gösterilir.** Trendyol bu siparişlerin gönderilmesinde sorumluluk kabul etmiyor; UI "henüz hazırlamayın" uyarısını taşır.
- **Eşleşmemiş sipariş satırları** kendi kuyruğunda: barkodu katalogda bulunamayan satırlar. Operatör buradan ürünle eşler. Sipariş asla reddedilmez.
- Paket detayında statü geçiş butonları yalnızca **geçerli geçişler** için etkin; kargo takip numarası ve fatura bağlantısı buradan gönderilir.

### 4.4 Senkron monitörü ve işlem kuyruğu

Bu iki ekran, asenkron ve item-bazında kısmi başarısız olan bir sistemde **desteği ayakta tutan** ekranlardır.

- **Senkron monitörü**: `sync_runs` üzerinden kanal × kaynak matrisi, son çalışma zamanı, süre, işlenen/başarısız sayıları, `usePoll` ile canlı.
- **İşlem kuyruğu**: `channel_operations` defteri. Her satır `beklemede → gönderildi → sonuç bekleniyor → tamamlandı/başarısız` yaşam döngüsünü gösterir; `remote_batch_id` ve item-bazlı hata mesajı görünür. Filtre: yalnızca başarısızlar, yeniden dene, toplu yeniden dene.
- **Webhook sağlık rozeti**: Trendyol webhook'u 13 başarısızlıkta kendini kapatıyor ve bildirim **satıcıya** gidiyor, bize değil. Bağlantılar ekranında canlı bir sağlık göstergesi ve tek tıkla yeniden etkinleştirme.

### 4.5 Toplu işlemler

- CSV/XLSX içe aktarma: dosya yükle → sütun eşleme → önizleme + doğrulama → onay → arka plan işi.
- İlerleme `usePoll` ile; hata satırları indirilebilir rapor olarak.
- Toplu fiyat/stok: seçili satırlar üzerinde yüzde/sabit değişiklik, uygulamadan önce **etkilenecek satır sayısı ve örnek sonuç** gösterilir.

---

## 5. Tenant ve lisans arayüzü

- **Markalama**: tenant logosu ve adı paylaşılan prop'tan; `app-logo.tsx` bunu okur. Renk temasına dokunulmaz (tek tasarım dili korunur).
- **Lisans banner'ı**: `license.status` `grace` ise kalıcı uyarı çubuğu (kalan gün + yenileme bağlantısı), `expired` ise engelleyici ekran.
- **Salt-okunur mod**: `license.readOnly` true iken tüm yazma aksiyonları **devre dışı bırakılır ve sebebi tooltip'te açıklanır** — gizlenmez. Kullanıcı verisinin durduğunu, kaybolmadığını görmeli.
- **Kota göstergeleri**: Ayarlar → Lisans & Kullanım ekranında ürün sayısı, aylık sipariş, kanal sayısı, koltuk sayısı için ilerleme çubukları. %80'de sarı, %100'de kırmızı + plan yükseltme çağrısı.

---

## 6. Yetki bazlı arayüz

```ts
const can = usePermission();          // permissions dizisini okur
<Button disabled={!can('products.update') || license.readOnly}>Kaydet</Button>
```

**Kural: aksiyonlar gizlenmez, devre dışı bırakılır.** Bir depo çalışanı fiyat alanını görmeli ama değiştirememeli — görmediği bir şeyi soramaz, gördüğü ama kilitli olan bir şey için yöneticisine gider. Devre dışı bırakma sebebi her zaman tooltip'te.

Tek istisna: tamamen alakasız bölümler (ör. muhasebe rolü için "Ekip & Roller") menüden çıkarılır.

Frontend yetki kontrolü **yalnızca UX içindir**; gerçek yaptırım sunucudaki Policy'lerdedir.

---

## 7. Türkçe yerelleştirme

- `APP_LOCALE=tr`, `APP_FALLBACK_LOCALE=tr`.
- Sunucu tarafında `Number::useLocale('tr')->useCurrency('TRY')` bir servis sağlayıcıda bir kez ayarlanır (`ext-intl` gerekir).
- Para, tarih ve sayı biçimlendirmesi **sunucuda** yapılır ve biçimlenmiş dize olarak gönderilir; ham değer de gönderilir (sıralama/hesap için). İki yerde biçimlendirme mantığı tutulmaz.
- Arayüz metinleri Türkçe. Pazaryeri alan adları, enum değerleri ve hata kodları **çevrilmez** — destek ekibi Trendyol paneliyle karşılaştırma yapabilmeli. Yanına Türkçe açıklama eklenir.
- Tarihler `Europe/Istanbul` saat diliminde gösterilir; Trendyol epoch-ms gönderir, dönüşüm sunucuda yapılır.

---

## 8. Tasarım dili ve bileşenler

Mevcut token seti (oklch, `neutral` taban) korunur. Eklenecek bileşenler (shadcn'den, mevcut stille tutarlı):

`table` · `tabs` · `popover` · `command` (arama paleti) · `calendar` + `date-range-picker` · `progress` · `switch` · `textarea` · `radio-group` · `alert-dialog` · `accordion` · `scroll-area` · `pagination`

`resources/js/components/ui/*` hem eslint hem prettier tarafından **yok sayılıyor** — shadcn çıktısı olduğu gibi bırakılacak, elle düzenlenmeyecek. Özelleştirme gerekirse sarmalayıcı bileşen yazılır (mevcut `ui/sonner.tsx`'in `useAppearance` + `useFlashToast` ile özelleştirilmesi bunun kabul edilmiş istisnası).

**Domain bileşenleri** `components/` altında düz değil, alan klasörlerinde toplanır: `components/catalog/`, `components/channels/`, `components/orders/`. Bugünkü düz yapı 26 dosyada çalışıyor, 120 dosyada çalışmaz.

### Durum rozetleri

Kanonik durumlar için tek bir renk sözlüğü tanımlanır ve **her ekranda aynı** kullanılır. Bir siparişin "Kargolandı" rengi listede ve detayda farklı olamaz. Renk tek başına bilgi taşımaz — her rozette metin de bulunur (erişilebilirlik).

---

## 9. Erişilebilirlik ve performans

- **Klavye**: tablo satırlarında ok tuşu gezinmesi, `Cmd/Ctrl+K` ile komut paleti, tüm modal'larda odak tuzağı (Radix zaten sağlıyor).
- **Renk**: durum bilgisi asla yalnızca renkle verilmez.
- **Form hataları**: `input-error.tsx` mevcut; hata alanına odaklanma zaten `settings/security.tsx`'te uygulanmış — bu desen tüm formlarda tekrarlanır.
- **Kod bölme**: Blade kökü zaten sayfa başına `@vite` yapıyor; ek yapılandırma gerekmiyor.
- **React Compiler açık** — manuel memoization yazılmayacak. Derleyicinin kaçırdığı ölçülebilir bir durum çıkarsa o zaman elle eklenir, önce değil.
- **Prefetch politikası**: kenar çubuğu bağlantılarında `prefetch` (mevcut), liste satırlarında `instant`. Her şeyi prefetch etmek sunucuyu gereksiz yorar.

---

## 10. Kod standartları

Mevcut yapılandırma sıkı ve korunacak:

- **eslint**: `consistent-type-imports` (`prefer: type-imports`, ayrı satır), `import/order` alfabetik gruplu, `curly: all`, `@stylistic/brace-style: 1tbs`, kontrol yapıları etrafında zorunlu boş satır.
- **prettier**: 4 boşluk, tek tırnak, `printWidth: 80`, `prettier-plugin-tailwindcss` (`clsx`, `cn`, `cva` fonksiyonları tanınıyor).
- **TypeScript strict**; `@typescript-eslint/no-explicit-any` kapalı ama `any` yazmak için gerekçe olmalı.
- **Wayfinder zorunlu**: URL'ler elle yazılmaz. `@/routes/*` bağlantılar için, `@/actions/*` form aksiyonları için. `href="/products"` bir kod incelemesi hatasıdır.
- Yeni kod `npm run lint:check && npm run format:check && npm run types:check` kapısından geçmeden birleştirilmez (`composer ci:check` bunları zaten çalıştırıyor).

---

## 11. Ekran ↔ veri kaynağı izlenebilirliği

Her ekranın arka uçta bir karşılığı olmalı; her kanonik varlığın bir arayüzü olmalı ya da bilinçli olarak "arayüz yok" işaretlenmeli.

| Ekran | Kaynak tablolar (`BACKEND-PLAN` §5) |
|---|---|
| Gösterge Paneli | `orders`, `sync_runs`, `inventory_items`, `channel_listings` |
| Ürünler | `products`, `product_variants`, `product_images`, `prices` |
| Markalar / Kategoriler | `brands`, `categories`, `attributes`, `attribute_values` |
| Stok Durumu / Depolar | `inventory_items`, `warehouses` |
| Bağlantılar | `channel_connections` |
| Eşleme | `channel_category_mappings`, `channel_attribute_mappings`, `channel_attribute_value_mappings`, `channel_brand_mappings` |
| Listelemeler | `channel_listings`, `channel_price_rules`, `channel_stock_rules` |
| Siparişler / Kargo | `orders`, `order_lines`, `shipment_packages`, `order_status_history`, `invoices` |
| İadeler | `claims`, `claim_items` |
| Sorular | `questions` |
| Senkron Monitörü | `sync_runs`, `sync_cursors` |
| İşlem Kuyruğu | `channel_operations` |
| Bildirimler | `notifications`, `notification_preferences` |
| Ekip & Roller | `users`, spatie `roles`/`permissions` |
| Lisans & Kullanım | central: `licenses`, `plans`, `usage_counters` |

**Arayüzü olmayan (bilinçli):** `webhook_events` (ham kayıt — yalnızca destek için, sağlık özeti Bağlantılar ekranında), `idempotency_keys` (tamamen dahili), `activity_log` (Faz 2'de denetim ekranı).

---

## 12. Yol haritası

`BACKEND-PLAN.md` §15 ile hizalı; her frontend fazı karşılık gelen backend fazının hemen ardından gelir.

| Faz | İçerik |
|---|---|
| **1** | Kabuk: navigasyon, paylaşılan prop'lar, tenant markalama, yetki kancası, lisans banner'ı, `Auth.user` tip düzeltmesi, SSR kapatma |
| **2** | Katalog CRUD, ürün listesi (`<InfiniteScroll>` + FTS arama), varyant düzenleme, görsel yönetimi |
| **3** | Bağlantılar ekranı + **eşleme sihirbazı** (§4.1) |
| **4** | Kanal listelemeleri, gönderim durumu rozetleri, reddetme sebebi → düzeltme akışı |
| **5** | Satır içi stok/fiyat düzenleme (`optimistic`), toplu işlemler, CSV içe aktarma |
| **6** | Siparişler, kargo/paket, eşleşmemiş satır kuyruğu |
| **7** | İadeler, sorular |
| **8** | Senkron monitörü, işlem kuyruğu, webhook sağlığı (`usePoll`) |
| **9** | Gösterge paneli widget'ları (`<Deferred>`), bildirim merkezi, cilalama |

Gösterge paneli bilinçli olarak **sona** bırakılmıştır: gerçek veri akmadan hangi metriklerin işe yaradığı bilinemez. Faz 1'de yer tutucu bir karşılama ekranı yeterlidir.
