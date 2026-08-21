# Trendyol Marketplace Adaptörü — Uygulama Sözleşmesi

> **Amaç.** Bu doküman, KobiConnect'in ilk pazaryeri adaptörü olan Trendyol entegrasyonunun tek referansıdır. Bir mühendis bu dokümanı okuyarak `developers.trendyol.com`'a dönmeden istemciyi yazabilmelidir.
> **Kaynak tarihi:** 19 Ağustos 2026. Trendyol doküman sayfalarının `updatedAt` damgaları bölüm 13'te.
> **İşaretleme:** Resmî Trendyol kaynağında doğrulanamayan her iddia **⚠️ doğrulanmadı** ile işaretlidir ve neyin onu doğrulayacağı yazılıdır. İki resmî kaynak çelişiyorsa **ikisi de** gösterilir ve çelişki açıkça etiketlenir.
> **Kimlik isimleri çevrilmez.** Alan adları, enum değerleri, endpoint yolları ve hata kodları orijinal hâliyle bırakılmıştır.

---

## 1. Özet & kapsam

Trendyol Partner API'si; ürün içeriği, stok/fiyat, sipariş paketi, iade (claim), müşteri sorusu, fatura/belge, finans ve webhook alanlarını kapsayan **REST + HTTP Basic** bir yüzeydir. Tüm yazma işlemleri (ürün/stok/fiyat) **asenkron**tur ve `batchRequestId` döner. Webhook yalnızca **sipariş paketi statüleri** içindir; ürün/fiyat/onay webhook'u **yoktur**.

Toplam yüzey: `developers.trendyol.com/llms.txt` üzerinden 190 doküman sayfası, `/reference/*` sayfalarına gömülü OpenAPI 3.0.3 tanımlarından çıkarılmış **92 benzersiz operasyon**.

### 1.1 Yetenek matrisi

| Alan | Endpoint sayısı | KobiConnect fazı | Not / kapsam dışı gerekçesi |
|---|---:|---|---|
| **Ürün yaşam döngüsü** (create, unapproved-update, content/variant/delivery-update, delete, archive, unlock, buybox) | 9 | **MVP** | Çekirdek. `buybox-information` v1.1'e ertelenebilir (fiyat rekabeti özelliği MVP'de yok). |
| **Ürün okuma / filtreleme** (getProductBase, approved, unapproved, inventory-and-price) | 4 | **MVP** | Mutabakatın (reconciliation) tek doğruluk kaynağı. |
| **Kategori / marka / attribute** (getBrands, getBrandsByName, createBrand, getCategoryTree, getCategoryAttributes, getCategoryAttributeValues) | 6 | **MVP** | `createBrand` v1.1 — moderasyon davranışı belgesiz, MVP'de marka eşleşmezse ürün açılmaz. |
| **Fiyat & stok** (updatePriceAndInventory) | 1 | **MVP** | Ürünün asıl değeri. 15 dakikalık dedup penceresi mimariyi belirler (bkz. §3). |
| **Asenkron sonuç** (getBatchRequestResult, getUpdateAudits) | 2 | **MVP** | Opsiyonel değil; yazma işleminin sonucu **yalnızca** buradan öğrenilir. |
| **Sipariş / paket okuma** (v2/orders, orders/stream, legacy orders) | 3 | **MVP** | `orders/stream` senkronizasyonun omurgası, `v2/orders` hedefli sorgu. |
| **Sipariş statü geçişleri** (updatePackageStatus, unsupplied, box-info, cargo-providers, warehouse, extended-agreed-delivery-date, delivered-by-service, labor-costs) | 8 | **MVP**: status + unsupplied. **v1.1**: diğerleri | Kutu/desi, depo, ek termin, işçilik bedeli MVP iş akışında yok. |
| **Paket bölme** (split, multi-split, quantity-split, split-packages) | 4 | **v1.1** | Kısmi tedarik senaryosu MVP'de yok; bölünmüş paketleri **okuyabilmek** yine de MVP (UnPacked + `originPackageIds`). |
| **Alternatif teslimat & manuel teslim/iade** | 6 | **v1.1** | Satıcı kendi kargosunu kullanan senaryo; MVP'de Trendyol kargosu varsayılır. |
| **İade / claim** (getClaims, createClaim, approve, issue, issue-reasons, item-audits) | 6 | **v1.1** | Okuma (getClaims) MVP'ye alınabilir — panelde iade görünürlüğü için. Onay/ret akışı 5 req/dk limitiyle kuyruk gerektirir. |
| **Soru & cevap** (getQuestionFilter, getQuestion, answerQuestion) | 3 | **v1.1** | 3 iş günü SLA'sı ve moderasyon döngüsü ayrı bir ürün yüzeyi ister. |
| **Webhook** (create, list, update, delete, activate, deactivate) | 6 | **MVP** | Gecikme için; doğruluk için polling korunur (§10). |
| **Fatura & belge** (invoice-link, delete, file upload, warranty) | 4 | **v1.1** | e-Fatura sağlayıcı entegrasyonu ayrı bir iş; MVP'de satıcı panelden besliyor. |
| **Ortak etiket / kargo etiketi** (common-label create/get, with-product-labels) | 3 | **v1.1** | Yalnız TEX ve Aras + Trendyol ödemeli gönderilerde geçerli; dar kapsam. |
| **Ortak servisler** (getSuppliersAddresses, getCargoProviders, ülke/şehir/ilçe/mahalle) | ~12 | **MVP**: adresler + kargo firmaları. **v1.1**: coğrafi lookup | Adres servisi 1 req/saat — agresif cache zorunlu. Coğrafi lookup yalnız adres normalizasyonu isteyince gerekir. |
| **Finans / settlement** (settlements, otherfinancials, cargo-invoice, tex compensation) | 4 | **Kapsam dışı (v2)** | KobiConnect MVP'si muhasebe modülü içermiyor; 15 günlük pencere + haftalık ödeme emri modeli ayrı bir mutabakat altyapısı ister. |
| **Video servisi** | 2 | **Kapsam dışı** | İçerik zenginleştirme; tek ürün-kartı-başına-tek-video kuralı ve onay süreci MVP değerine katkı sağlamıyor. |
| **Mikro ihracat / `ecgw` (AutoFT)** | 13 | **Kapsam dışı** | Ayrı bir API yüzeyi, ayrı statü sözlüğü (`new/pending/completed/cancelled`) ve yalnızca stage sunucusu belgeli (prod URL'leri ⚠️ doğrulanmadı). |
| **Stage yardımcıları** (createTestOrder, updateTestOrderStatus, updateTestClaimToWaitingInAction, stage soru üretme) | 4 | **MVP (yalnız dev/test)** | Prod'da yok; entegrasyon testlerinin tek yolu. |
| **Stage IP allow-list self servis** (ip-whitelists GET/POST/DELETE) | 3 | **MVP (yalnız dev/test)** | Stage'e erişimin ön koşulu. |
| **Müşteri yorumları / değerlendirme** | 0 | **Kapsam dışı** | **API yok.** 190 sayfada yorum/değerlendirme servisi bulunmuyor; Trendyol yalnızca *soru* yüzeyi açıyor. |

### 1.2 Var olmayan, sık sorulan şeyler

| Beklenen | Gerçek |
|---|---|
| `updateBoxQuantity` | Gerçek ad **`updateBoxInfo`**, gövde `{deci, boxQuantity}`, yalnız UPS & CEVA |
| `getShipmentPackageCancelReasons` | Endpoint yok. `reasonId` statik tablo (§5.6) |
| `updateTrackingNumber` | Belgeli endpoint yok. Limit tablosunda "Kargo Takip Kodu Bildirme" satırı var, karşılığı olan sayfa yok — **⚠️ doğrulanmadı**. Satıcı kargosu için `processAlternativeDelivery` kullanılır |
| Webhook event tipleri (`ORDER_CREATED`, `PRODUCT_PRICE_CHANGED` …) | Yok. Yalnız 13 sipariş paketi **statüsü** |
| Webhook imzası / HMAC | Yok. Yön ters: Trendyol **size** kimlik doğrular (§10) |
| Komisyon endpoint'i | Yok. `commission` sipariş satırında ve onaylı ürün varyantında; `commissionRate`/`commissionAmount` settlement kaydında |
| KDV oranı lookup | Yok. `vatRate` ürün alanı (`0, 1, 10, 20`) |
| Para birimi lookup | Yok. `currencyCode`/`currencyType` payload alanı |
| Idempotency-Key | Yok. Hiçbir endpoint'te istek düzeyi dedup token'ı yok (§3, §9) |
| Rate-limit yanıt başlıkları | Belgesiz — **⚠️ doğrulanmadı** |

---

## 2. Kimlik doğrulama & ortamlar

### 2.1 Base URL'ler

| Ortam | Base URL | Not |
|---|---|---|
| **Production** | `https://apigw.trendyol.com/integration` | IP allow-list **gerekmez** |
| **Stage / Test** | `https://stageapigw.trendyol.com/integration` | IP allow-list **zorunlu** |
| Stage satıcı paneli | `https://stagepartner.trendyol.com/account/login` | |
| Prod satıcı paneli | `https://partner.trendyol.com/account/info?tab=integrationInformation` | API bilgileri burada |
| **Emekli (kullanmayın)** | `https://api.trendyol.com/sapigw/` | Şubat 2025'te taşındı, **26 Mayıs 2025'te kapatıldı**. Popüler topluluk SDK'larının çoğu hâlâ bu host'u hard-code ediyor (§13). |

Tüm OpenAPI `servers` blokları yalnız bu iki host'u listeliyor. Hiçbir güncel sayfada `sapigw` geçmiyor.

### 2.2 Kimlik doğrulama

```
Authorization: Basic base64(apiKey:apiSecret)
User-Agent: {sellerId} - SelfIntegration
```

- **Şema:** HTTP Basic. Kullanıcı adı = **API KEY**, parola = **API SECRET KEY**. OAuth yok, token değişimi yok, yenileme yok, belgelenmiş süre sonu yok, **scope kısıtı yok** — tek kimlik çifti satıcının tüm API yüzeyini açar.
- **Hatalı yetkilendirme:** `401` + gövde `{"exception": "ClientApiAuthenticationException"}`. Dikkat: bu, diğer hataların `{"errors":[…]}` zarfından **farklı bir şekildir**; parser her ikisini de tolere etmeli.

### 2.3 `User-Agent` — eksikse 403

> "Trendyol Partner API'ye yapılacak tüm isteklerde, Auth ve **User-Agent** bilgileri Header'da bulunmalıdır. **User-Agent bilgisi olmayan istekler, 403 hatası alarak engellenecektir.**" — *2. Authorization*

| Senaryo | Format | Örnek |
|---|---|---|
| Entegratör firma üzerinden | `{SatıcıId} - {Entegrasyon Firması İsmi}` | `1234 - TrendyolSoft` |
| Kendi yazılımı (KobiConnect müşterisi kendi hesabıyla) | `{SatıcıId} - SelfIntegration` | `4321 - SelfIntegration` |

Entegratör adı **alfanumerik, maksimum 30 karakter**. KobiConnect entegratör olarak listelenene kadar `SelfIntegration` kullanılmalı; hesap bazında override edilebilir bir kolon tutun.

### 2.4 `sellerId` / `supplierId` adlandırması

Aynı sayıdır. Güncel isimlendirme **`sellerId`** ve tüm path parametreleri bu adı taşır (`integer(int64)`). `supplierId` şu üç yerde hayatta:

1. **Query parametresi olarak:** `getShipmentPackages`, `getShipmentPackagesStream`, `getSettlements`, `getOtherFinancials`, `filterApprovedProducts`, `filterUnapprovedProducts`, `filterApprovedProductsInventoryAndPrice`, `getQuestionFilter` (Q&A kılavuz sayfasında **zorunlu** deniyor; OpenAPI'de böyle bir query parametresi yok — çelişki, path'teki `{sellerId}` kastediliyor).
2. **Yanıt alanı olarak:** sipariş/paket ve ürün payload'larında `supplierId`.
3. **Panel arayüzünde:** "Entegrasyon Bilgileri" ekranı `supplierId` etiketiyle gösterir.

### 2.5 İhtilaflı başlıklar

| Başlık | Durum |
|---|---|
| `X-Supplier-Id: {sellerId}` | **⚠️ doğrulanmadı.** Yalnızca Trendyol'un kendi ajan eklentisinde (`github.com/Trendyol/trendyol-integration-developer-tool`) geçiyor; TR *2. Authorization* sayfasında **yok**. Eklenti `INVALID_SUPPLIER_ID_FORMAT` doğrulama kodunu da tanımlıyor. **Doğrulama:** stage'de bu başlık olmadan bir istek atıp 400/403 alınıp alınmadığına bakın. Göndermek zararsız görünüyor. |
| `storefrontCode` / `storeFrontCode` | Kısmen belgeli, **büyük/küçük harf tutarsız**: `createBrand` sayfası `storeFrontCode` (büyük F, **zorunlu**), `getCategoryTree` / `getCategoryAttributes` / `getCategoryAttributeValues` / `getUpdateAudits` sayfaları `storefrontcode` (tamamı küçük, opsiyonel, varsayılan `TR`) yazıyor. Uluslararası *Product Create v2* sayfası "You need to send `storeFrontCode` as Header Parameter" diyor. TR pazaryerinde ürün/sipariş servisleri için **belgesiz** — ⚠️ doğrulanmadı. **Her endpoint'te sayfanın yazdığı yazımı birebir gönderin.** |
| `Accept-Language` | `tr, en, ro, ar, el`. TR storefront **her zaman Türkçe** döner, başlık ne olursa olsun. Diğer storefront'larda varsayılan `en`. `getCategoryTree`, `getCategoryAttributes`, `getCategoryAttributeValues`, `getUpdateAudits` için belgeli. |

### 2.6 Satıcı API anahtarını nasıl alır

Satıcı Paneli → sağ üstte **Mağaza Adı** → **Hesap Bilgilerim** → **Entegrasyon Bilgileri**
(`https://partner.trendyol.com/account/info?tab=integrationInformation`)

- Yalnızca **master user (admin rolü)** görebilir.
- Üç değer verir: `supplierId` (= sellerId), `API KEY`, `API SECRET KEY`.
- **Stage kimlik bilgileri prod'dan tamamen farklıdır** ve stage panelinden alınır.
- **Rotasyon API'si yoktur.** Rotasyon manuel bir panel işlemidir → sıcak değiştirilebilir secret tasarımı yapın ve rotasyon sırasında kısa 401 fırtınalarını bekleyin.
- Trendyol açıkça uyarıyor: API anahtarları GitHub/GitLab gibi açık platformlarda paylaşılmamalı.

### 2.7 Kayıt / sertifikasyon gerekiyor mu?

**Hayır — geliştirici dokümantasyonu itibarıyla.** (Yokluktan çıkarım; **⚠️ yorum**)

- Uygulama kaydı, `client_id`, inceleme/sertifikasyon süreci, sandbox anahtar sağlama akışı — hiçbiri `developers.trendyol.com`'da yok. Onaylı her satıcı anahtar üretip **doğrudan production'a** istek atabilir; prod IP allow-list gerektirmez.
- `User-Agent` entegratör adı, API düzeyindeki tek "entegrasyon firması" mekanizmasıdır: **kendi beyan edilen** bir atıf dizgisidir, doğrulanan bir kimlik değil. Trendyol'un entegratör id'si verdiği ya da doğruladığı bir yer yok.
- Satıcı panelindeki *Satış Operasyon → "TERCİH EDİLEN ENTEGRASYON MODELİ"* listesi ticari/partner görünürlüğüdür; API kapısı olduğuna dair **hiçbir Trendyol belgesi yok** — ⚠️ doğrulanmadı (kaynak: satıcı blogları).
- Ön koşul yalnızca **onaylı Trendyol satıcısı olmak**.
- Trendyol kod desteği vermez: *"Kod desteği vermekte misiniz? **Hayır**"* (SSS).

### 2.8 Stage erişimi (bu **kapılı**)

1. **IP allow-list zorunlu.** Uygulama sunucularınızın çıkış IP'leri Trendyol'a bildirilmeli. Talep: satıcı paneli bildirimi veya **0850 258 58 00**.
2. **Stage'de aldığınız `503`, IP yetkilendirmesi olmamasındandır** — kesinti değil.
3. Doküman metni kafa karıştırıcı: *"Statik IP'ler için yetkilendirme sağlanamamaktadır"* yazıyor, ama aynı paragraf sabit çıkış IP'si bildirmenizi istiyor. **⚠️ doğrulanmadı** — pratikte sabit egress IP gerekir; teyit için Trendyol destek talebi açın.
4. Stage hesabı: ortak test hesabı veya Seller Center'dan özel test hesabı (e-posta, telefon, TCKN/VKN ile).

**Self-servis IP allow-list API'si** (stage):

| Metod | Yol |
|---|---|
| GET | `/integration/product/sellers/{sellerId}/ip-whitelists` |
| POST | `/integration/product/sellers/{sellerId}/ip-whitelists` |
| DELETE | `/integration/product/sellers/{sellerId}/ip-whitelists/{ip}` |

Kurallar: yalnız IPv4 (IPv6 reddedilir), maksimum **7 IP**, günde maksimum **7 create isteği**, her kayda sistem `expiresAt` atar, limit aşımı → **429**.

---

## 3. Mimariyi belirleyen kısıtlar

Mimarın ilk okuyacağı bölüm. Bunların her biri şema veya iş kuyruğu tasarımını değiştirir.

**K1 — Her mutasyon asenkron ve kalem düzeyinde kayıplı.**
`createProducts`, `unapproved-bulk-update`, `content-bulk-update`, `variant-bulk-update`, `delivery-info-bulk-update`, `updatePriceAndInventory`, `archiveProducts`, `deleteProducts`, `unlockProducts` — hepsi yalnızca `{"batchRequestId": "..."}` döner. **HTTP 200 "kabul edildi" demektir, "uygulandı" değil.** SSS bunu birebir yazıyor: *"Response içerisinde batchId dönmesi yapmış olduğunuz işlemin başarılı sonuçlandığı anlamına gelmemektedir."* `status: "COMPLETED"` de "hepsi başarılı" demek değildir — `items[]` gezilmeli.
→ **Sonuç:** outbox tablosu (`desired_state → payload_hash → batchRequestId → item_result`) zorunlu.

**K2 — Batch sonucu yalnız 4 saat yaşar.**
`getBatchRequestResult` sonuçları oluşturulduktan **4 saat** sonra görüntülenemez. Kuyruğunuz 4 saatten fazla geride kalırsa `failureReasons` **kalıcı olarak** kaybolur. Süre dolduktan sonraki davranış (404 mü, boş mu, 200-ama-item'sız mı) **⚠️ doğrulanmadı** — stage'de bir batch'i 4 saat bekletip sorgulayarak ölçün.

**K3 — `updatePriceAndInventory` üzerinde 15 dakikalık dedup penceresi (anti-idempotency).**
> "Stok-fiyat güncelleme işlemlerinde request body içerisinde **değişiklik yapmadan aynı isteği tekrar atmanız halinde**, sizlere hata mesajı dönecektir. Hata mesajı olarak **'15 dakika boyunca aynı isteği tekrarlı olarak atamazsınız!'** göreceksiniz."

Trendyol'un kendi eklentisi bunu "hard idempotency window" diye adlandırıp semantiğini netleştiriyor: *herhangi bir alan değerinin 1 birim bile değişmesi pencereyi sıfırlar; yalnızca birebir aynı istekler engellenir.* **Bu idempotency değil, onun tersidir: meşru şekilde başarısız olmuş bir isteğin retry'ı cezalandırılır**, çünkü retry bayt bayt aynıdır.
→ **Sonuç:** barkod başına `{last_sent_at, quantity, salePrice, listPrice}` tutun; göndermeden önce 15 dk içinde ve değerleri aynıysa kalemi **istekten düşürün**. Retry, aynı gövdeyi tekrar oynatmak değil, **arzu edilen durumu yeniden hesaplamak** olmalı.

**K4 — İki eksenli rate limiting.**
(a) **Global sert tavan:** aynı endpoint'e **10 saniyede maksimum 50 istek** — 51.'si `429 too.many.requests`. Yani burst tavanı gerçekte **5 req/sn/endpoint**.
(b) **Servis grubu / endpoint kotaları:** dakika bazında, satıcının **listeleme limiti kademesine** göre (50k/75k/150k/500k/limitsiz). 14 Eylül 2026'dan itibaren ürün servislerinde limit **endpoint başına değil, servis grubu başına** uygulanır (§7).
→ **Sonuç:** limiter anahtarı `(sellerId, serviceGroup)` **artı** altında `(sellerId, endpoint)` 50/10sn koruması. Çok kiracılı: bir gürültülü tenant diğerlerini aç bırakmamalı. `getBatchRequestResult` polling'i ürün okumalarıyla **aynı Read bütçesini** yer — polling'e açıkça bütçe ayırın.

**K5 — Webhook'lar imzasız, sırasız, en-az-bir-kez ve kendi kendini kapatan.**
İmza/HMAC yok. Sıralama garantisi yok. Başarısız istek **başarılı olana kadar her 5 dakikada bir** tekrarlanır → kopyalar kaçınılmaz. **13 hata** sonrası webhook otomatik olarak pasife alınır. Satıcı başına **maksimum 15 webhook** (pasifler dâhil). Trendyol'un kendisi *"getshipmentpackage servisini kullanarak periyodik olarak datalarınızı eşitlemenizi öneririz"* diyor.
→ **Sonuç:** webhook = gecikme, `orders/stream` polling = doğruluk. İkisi **aynı upsert yoluna** yazmalı.

**K6 — Onaylı ve onaysız ürün iki ayrı veri modelidir.**
Farklı listeleme endpoint'leri, farklı `dateQueryType` enum'ları, farklı sayfa boyutu tavanları (onaysız `size≤1000`, onaylı `size≤100`), farklı güncelleme endpoint'leri (onaysız: tek endpoint barkod anahtarlı; onaylı: **üç** endpoint — content `contentId` anahtarlı, variant ve delivery `barcode` anahtarlı), farklı değişmezlik kuralları.
→ **Sonuç:** güncelleme yolunu seçmeden önce onay durumunu bilmek zorundasınız; `approval_state` ve `contentId` listing tablosunda **kalıcı** olmalı.

**K7 — Onaylı ürün içerik güncellemesinde ikinci bir asenkron aşama var.**
`batchRequestType: ApprovedProductContentUpdate` için `SUCCESS`, yalnızca isteğin **kalite kontrole girdiğini** söyler. Gerçek sonuç `getUpdateAudits` (`{contentId}` anahtarlı) altında, alan bazında (`TITLE|DESCRIPTION|MEDIA|ATTRIBUTE`) `SUCCESS|FAIL|RUNNING` olarak çıkar.
→ **Sonuç:** onaylı ürünler için **iki ayrı sonuç izleme hattı** gerekir, bir tane değil.

**K8 — Create ve update attribute payload'ları farklı (ve dokümanlar çelişiyor).**
Bkz. §9.6 — bu dokümanın en tehlikeli maddesi. Create ve update serializer'larını **ilk günden ayrı** tutun.

**K9 — Ürün yalnızca yaprak (leaf) kategoriye açılır.**
> "createProduct yapmak için **en alt seviyedeki** kategori ID bilgisi kullanılmalıdır. Seçtiğiniz kategorinin alt kategorileri var ise bu kategori bilgisi ile ürün aktarımı yapamazsınız."

`subCategories == []` olan düğüm yapraktır. `is_leaf` türetilmiş bir kolon olarak saklanmalı ve publish öncesi assert edilmeli.

**K10 — Referans veri sık değişir, limitleri düşüktür.**
Kategori ağacı/attribute'ları **haftalık** yenileyin (Trendyol'un tavsiyesi). Marka ve kategori servisleri 50 req/dk; `getSuppliersAddresses` **1 req/saat**. Cache opsiyonel değil.

**K11 — Barkod sunucu tarafında normalize edilir.**
> "Barkodunuzun ortasında **boşluk varsa birleştirilerek içeri alınır**. Stok-fiyat güncellemelerinizi de **içeri alınan barkoda göre** yapmanız gerekmektedir."

`"ABC 123"` → `"ABC123"`. Dedup anahtarınız normalize edilmemiş barkodsa hem ürünü bulamazsınız hem de stok güncellemeleriniz sessizce hiçbir şeyi hedeflemez.

---

## 4. Endpoint referansı

### 4.0 Tüm endpoint'ler için ortak kurallar

- Tüm yollar `https://apigw.trendyol.com/integration` (prod) veya `https://stageapigw.trendyol.com/integration` (stage) ön ekiyle kullanılır. Aşağıda **ön ek dâhil tam yol** verilmiştir.
- `sellerId` her zaman `integer(int64)` path parametresidir.
- Tüm tarih alanları — istisnasız — **epoch MİLİSANİYE** (`int64`) değildir; istisnalar §4.2.9 (`getUpdateAudits`) ve §4.3.1'de (`updateRequestDate`) açıkça belirtilmiştir.
- Zaman dilimi: Trendyol `orderDate` için "Timestamp (milliseconds) ve **GMT +3**", `createdDate` için "**GMT**" diyor. Aynı payload içinde iki farklı referans iddiası var → **⚠️ doğrulanmadı**, epoch değerlerini UTC kabul edip stage'de bilinen bir siparişle kalibre edin.
- Hata zarfı: `{"errors":[{"key","message","errorCode"}]}` — tek istisna 401 (`{"exception":"ClientApiAuthenticationException"}`).

---

### 4.1 Referans veri (marka, kategori, attribute)

#### 4.1.1 `getBrands` — Marka Listesi
`GET /integration/product/brands`

| Param | Tip | Zorunlu | Varsayılan | Anlam |
|---|---|---|---|---|
| `page` | integer | – (belgesiz) | belgesiz | Sayfa numarası. 0/1 tabanlı olduğu **belgesiz** ⚠️ |
| `size` | integer | – | belgesiz | Yanıttaki marka sayısı |

**Yanıt:** `{"brands":[{"id":10,"name":"TrendyolMilla"}]}` — sarmalayıcı `brands` anahtarı var, **sayfalama metadata'sı yok**.

**Tuzaklar:**
- *"Bir sayfada **minimum 1000** adet brand bilgisi alınabilmektedir."* — daha küçük `size` göndermeniz onurlandırılmayabilir.
- `totalPages` dönmediği için **boş `brands` dizisi gelene kadar** sayfalayın.
- Katalog çok büyük (100k+) → gecelik tam senkron endpoint'i, istek anı lookup'ı değil.
- Luxe: yanıta `LUXE` (boolean) alanı eklendi (31.07.2026 changelog) — markanın lüks kanal için uygun olup olmadığı.
- Limit: 50 req/dk (14 Eyl 2026'dan sonra Product Read grubu).

#### 4.1.2 `getBrandsByName` — İsme Göre Marka Filtreleme
`GET /integration/product/brands/by-name?name={name}`

| Param | Tip | Zorunlu | Anlam |
|---|---|---|---|
| `name` | string | **Evet** | Aranacak marka adı |

**Yanıt:** **çıplak JSON dizisi** `[{"id":40,"name":"TRENDYOLMİLLA"}]` — `getBrands`'ten **farklı şekil**, aynı deserializer'ı körlemesine kullanmayın.

**Tuzaklar:**
- **BÜYÜK/küçük harf duyarlı.** `"Nike" ≠ "nike"`. Türkçe noktalı/noktasız İ/ı önemli → invariant lowercase değil, **Türkçe locale casefolding** kullanın (`TRENDYOLMİLLA` ↔ `trendyolmilla`).
- Tam eşleşme mi, içerir mi — **belgesiz** ⚠️. Boş sonuç davranışı (boş dizi mi 404 mü) belgesiz ⚠️.
- Sayfalama yok.

#### 4.1.3 `createBrand` — Marka Yaratma
`POST /integration/product/sellers/{sellerId}/brands`

**Header:** `storeFrontCode` (**zorunlu**, örn. `TR`) — bu sayfada büyük F ile yazılmış.
**Content-Type: `multipart/form-data` — ZORUNLU.** JSON göndermek başarısız olur.

| Alan | Tip | Zorunlu | Kısıt |
|---|---|---|---|
| `name` | text | **Evet** | – |
| `images` | binary[] | **Evet** | min 1, max 3 dosya |

İzin verilen MIME tipleri, dosya boyutu, piksel boyutu: **belgesiz** ⚠️.

**Yanıt:** `201` → `{"brandId": <int64>}`. Senkron; batch yok.

**Tuzaklar:**
- **Önce `getBrandsByName` çağırın.** Var olan bir markanın kopyasını yaratmak ana hata modudur.
- Idempotency **belgesiz** ⚠️ — aynı adı iki kez göndermenin sonucu (409 mu, ikinci brandId mi, sessiz dedup mu) yazılı değil. Kendi `unique(marketplace_account_id, storefront_code, requested_name)` kısıtınızı koyun.
- Dönen `brandId`'nin **hemen kullanılabilir mi yoksa moderasyonda mı** olduğu **belgesiz** ⚠️ — createProducts'ın yeni brandId ile reddedilmesini handle edin.
- Marka **storefront kapsamlıdır**; birden çok ülkede satıyorsanız her storefront için ayrı yaratın.

#### 4.1.4 `getCategoryTree` — Kategori Listesi
`GET /integration/product/product-categories`

| Param | Konum | Tip | Varsayılan | Anlam |
|---|---|---|---|---|
| `name` | query | string | – | Ad filtresi; **her seviyede** eşleşen kategorileri döndürür (kısmi orman) |
| `storefrontcode` | header | string | `TR` | Ülke kodu |
| `Accept-Language` | header | string | non-TR için `en` | `tr, en, ro, ar, el` |

**Yanıt:** çıplak, özyinelemeli dizi:
```json
[{"id":1162,"name":"Atkı & Bere & Eldiven","parentId":368,
  "subCategories":[{"id":382,"name":"Atkı","parentId":1162,"subCategories":[]}]}]
```

**Tuzaklar:** sayfalama yok, tüm ağaç tek yanıtta (büyük payload, stream parse edin) · *"Kategori ağacı belirli aralıklarla güncellenmektedir, **haftalık** olarak güncel listeyi almanız önerilir."* · **Yalnız yaprak** kategoride ürün açılır · İsimler locale'e göre değişir, **id'ler sabittir** — asla isim üzerinden anahtarlamayın.

#### 4.1.5 `getCategoryAttributes` — Kategori Özellik Listesi v2
`GET /integration/product/categories/{categoryId}/attributes`

| Param | Konum | Tip | Anlam |
|---|---|---|---|
| `categoryId` | path | integer | **Yaprak** kategori id |
| `required` | query | boolean | `true` → yalnız zorunlu, `false` → yalnız opsiyonel, **gönderilmezse tümü**. Üç durumlu — `false` ≠ atlanmış |
| `storefrontcode`, `Accept-Language` | header | string | §2.5 |

**Yanıt:**
```json
{ "id": 14609, "name": "Muay Thai Kaskı", "displayName": "Muay Thai Kaskı",
  "categoryAttributes": [
    { "allowCustom": false,
      "attribute": {"id": 293, "name": "Beden"},
      "categoryId": 14609, "required": true,
      "varianter": true, "slicer": false,
      "allowMultipleAttributeValues": false } ] }
```

| Bayrak | Anlamı (resmî) | createProducts üzerindeki kısıtı |
|---|---|---|
| `required` | Zorunlu | `attributes[]` içinde **değerle** bulunmalı |
| `allowCustom` | `true` → id yerine **serbest metin** gönderilir | `false` ise **yalnızca** `getCategoryAttributeValues`'tan gelen id'ler |
| `allowMultipleAttributeValues` | `true` → birden fazla değer alabilir | `false` + çoklu id → hata |
| `slicer` | `true` → trendyol.com'da **ayrı ürün kartı** açar | Kategoride **birden fazla** slicer olabilir; onay sonrası **değiştirilemez** |
| `varianter` | Aynı kart içinde varyant (beden vb.) | Kategoride **tam olarak bir tane**; onay sonrası **değiştirilemez** |

**Tuzak:** Bu yanıt `attributeValues` **içermez** — eski sürümler içeriyordu, çok sayıda üçüncü parti örnek hâlâ öyle varsayıyor. Değerler ayrı endpoint'ten, **(kategori, attribute) çifti başına bir çağrı** ile alınır → N+1 fan-out, 50 req/dk limitiyle birlikte cache zorunlu.

**Sabit kural:** `allowCustom=false` **ve** `required=true` ⇒ o attribute için değer listesi çekilmeden ürün publish edilemez.
**`Web Color` (attributeId 295) 15 Ocak 2025'ten beri zorunludur** — eksikse ürün yaratma başarısız olur.

#### 4.1.6 `getCategoryAttributeValues` — Kategori Özellik Değerleri v2
`GET /integration/product/categories/{categoryId}/attributes/{attributeId}/values`

| Param | Konum | Tip | Maks | Anlam |
|---|---|---|---|---|
| `categoryId`, `attributeId` | path | integer | – | – |
| `page` | query | integer | **1000** | Sayfa numarası |
| `size` | query | integer | **1000** | Sayfa başına adet |

**Yanıt (tam sayfalama zarfı):**
```json
{"totalElements":1,"totalPages":1,"page":0,"size":10,
 "content":[{"attributeValueId":4872,"attributeValue":"Tek Ebat"}]}
```

**⚠️ Alan adı çelişkisi:** TR kılavuz sayfasındaki örnek **`attributeValue`** yazıyor; aynı endpoint'in yapı tanımı ve Trendyol'un kendi eklentisi **`attributeValueName`** diyor (eklenti bunu "observed inconsistency" olarak işaretliyor). **İki alanı da savunmacı şekilde parse edin** (`attributeValueName ?? attributeValue`).

`attributeValueId` sabittir, isim locale'e göre değişir → **id üzerinden** anahtarlayın.

---

### 4.2 Ürün servisleri

#### 4.2.1 `createProducts` — Ürün Yaratma V2
`POST /integration/product/sellers/{sellerId}/v2/products`

**Gövde:** `{"items":[ CreateProductItem ]}` — `maxItems: 1000`.

| Alan | Tip | Zorunlu | Maks | Kural |
|---|---|---|---|---|
| `barcode` | string | **Evet** | 40 | Özel karakter yalnız `.` `-` `_`. Türkçe karakter serbest. **Ortadaki boşluklar birleştirilir** (§9.2) |
| `title` | string | **Evet** | 100 | Ürün adı |
| `productMainId` | string | **Evet** | 40 | Model kodu; varyant gruplama anahtarı |
| `brandId` | integer | **Evet** | – | `getBrands`'ten |
| `categoryId` | integer | **Evet** | – | **Yaprak** kategori |
| `quantity` | integer | **Evet** | – | Stok |
| `stockCode` | string | **Evet** | 100 | Satıcının kendi sistemindeki unique stok kodu |
| `dimensionalWeight` | number | **çelişki** | – | OpenAPI `required` listesinde **var**, TR kılavuz tablosu "Zorunluluk: **Hayır**" diyor. ⚠️ Çelişki — **gönderin.** |
| `description` | HTML string | **Evet** | 30.000 | Uluslararası best-practice: **min 4.000 karakter**, ya da `<img>` etiketi içermeli. JS/style/iframe yasak |
| `origin` | string | Hayır → **23 Eki 2026'da Evet** | 2 | Menşei kodu (örn. `AD`). §12 |
| `listPrice` | number | **Evet** | – | PSF (üstü çizilen). **`listPrice >= salePrice`** |
| `salePrice` | number | **Evet** | – | TSF |
| `vatRate` | integer | **Evet** | – | `0, 1, 10, 20` |
| `deliveryDuration` | integer | Hayır | – | Sevkiyat süresi. Semantiği değişiyor — §12 |
| `deliveryOption` | object | Hayır | – | `{deliveryDuration, fastDeliveryType}`; `fastDeliveryType` ∈ `SAME_DAY_SHIPPING`, `FAST_DELIVERY` |
| `images` | list | **Evet** | 8 | `[{"url": "..."}]`. **https zorunlu** (geçerli SSL). Önerilen 1200×1800 @ 96 dpi |
| `lotNumber` | string / null | Hayır | 100 | Charset `A-Z a-z 0-9 , - . : /`. "Parti/Lot/SKT" bilgisi |
| `shipmentAddressId` | integer | Hayır | – | `getSuppliersAddresses`'ten |
| `returningAddressId` | integer | Hayır | – | `getSuppliersAddresses`'ten |
| `attributes` | list | **Evet** | – | Renk değeri ≤ 50 karakter. **Şekil için §9.6** |
| `cargoProviders` | string[] | Hayır | – | Barkod bazlı kargo firması kodu; `getCargoProviders`'tan |
| `specialConsumptionTax` | number | Hayır ⚠️ | – | OpenAPI şemasında **yok**; yalnız `getBatchRequestResult` echo'sunda görülüyor. ⚠️ doğrulanmadı |
| `currencyType` | string | Hayır ⚠️ | – | Aynı — yalnız batch echo'sunda |

**Yanıt:** `200` → `{"batchRequestId": "string"}` — *"İstek kuyruğa alındı."*

**Çağrı sırası (resmî):** `getBrands` → `getCategoryTree` → `getCategoryAttributes` → `getCategoryAttributeValues` → (`getSuppliersAddresses`, `getCargoProviders`) → `createProducts` → `getBatchRequestResult`.

**Tuzaklar:** Başarılı aktarım ürünü **onay sürecine** sokar; onay bekleyen/reddedilen ürün yayına çıkmaz · Varyantlama `productMainId` ile, yalnız `attributes` farklılaşır · `fastDeliveryType` tanımlamak için bugün `deliveryDuration: 1` şart (§12) · Idempotency yok — aynı barkodu tekrar POST etmenin davranışı **belgesiz** ⚠️; önce `getProductBase` ile create-vs-update kararı verin.

#### 4.2.2 `updateUnapprovedProducts` — Onaysız Ürün Güncelleme v2
`POST /integration/product/sellers/{sellerId}/products/unapproved-bulk-update`

**Gövde:** `{"items":[...]}`, maks **1000** item. Tek zorunlu alan **`barcode`**; diğer her alan opsiyonel (omission-based partial update).

Güncellenebilir alanlar: `title`, `description`, `productMainId`, `brandId`, `categoryId`, `stockCode`, `origin`, `dimensionalWeight`, `vatRate`, `deliveryOption{deliveryDuration,fastDeliveryType}`, `locationBasedDelivery` (`ENABLED|DISABLED|null`), `lotNumber`, `cargoProviders[]`, `shipmentAddressId`, `returningAddressId`, `images[]` (maks 8), `attributes[]`.
**`barcode` güncellenemez.**

**Yanıt:** `{"batchRequestId": "..."}` · `batchRequestType: ProductV2Update`.

**Tuzak:** `attributes` ve `images` koleksiyon alanlarıdır — dokunacaksanız **tam seti** gönderin (onaylı content güncellemesinde bu kural açıkça yazılı; burada aynı riski varsayın ⚠️).

#### 4.2.3 `updateContentBulk` — Onaylı Ürün Content Güncelleme v2
`POST /integration/product/sellers/{sellerId}/products/content-bulk-update`

**Anahtar `contentId`, barcode değil.** Maks 1000 item.

```json
{"items":[{"contentId":9510902,"title":"...","description":"...",
  "images":[{"url":"..."}],
  "attributes":[{"attributeId":1,"attributeValueId":1},
                {"attributeId":2,"customAttributeValue":"String"}]}]}
```

**Resmî kurallar (birebir):**
- Onaylı ürünlerde **`barcode`, `productMainId`, `brandId`, `categoryId` ve slicer/varianter olan attribute value değerleri güncellenemez.**
- **Attribute değerleri hariç partial update desteklenir.** Yalnız description güncellenecekse `contentId` + `description` yeter.
- **Attribute'lardan herhangi biri güncellenecekse ürünün altındaki TÜM attribute ve değerleri gövdede gönderilmelidir.**
- `images` dizisi **tam değiştirme**dir (maks 8).

**Yanıt:** `{"batchRequestId": "..."}` · `batchRequestType: ApprovedProductContentUpdate`.
**Kritik:** batch `SUCCESS` = "QC'ye girdi". Gerçek sonuç `getUpdateAudits` (§4.2.9).

#### 4.2.4 `updateVariantBulk` — Onaylı Ürün Varyant Güncelleme v2
`POST /integration/product/sellers/{sellerId}/products/variant-bulk-update`

**Anahtar `barcode`.** Maks 1000 item.

```json
{"items":[{"barcode":"test1","channels":["CORE","LUXE"],"stockCode":"test",
  "origin":"AD","vatRate":123,"shipmentAddressId":123,"returningAddressId":122,
  "dimensionalWeight":1.1,"lotNumber":"test",
  "cargoProviders":["kargo firması kodu"],"locationBasedDelivery":"ENABLED"}]}
```

**⚠️ ÇELİŞKİ — partial update destekleniyor mu?**
| Kaynak | İddia |
|---|---|
| TR kılavuz *Ürün Güncelleme - Onaylı Ürün v2*, Varyant sekmesi | *"Request body'deki field'lar için **partial istek yapılabilir**. İsteklerinizde barcode ve değiştirmek istediğiniz field'ı yollarsanız partial update uygulanacaktır."* |
| OpenAPI referans sayfası (`variant-bulk-update`) | *"partial update is NOT supported — every updatable field must be sent on each item"* |

**Stage'de doğrulayın.** Doğrulanana kadar **tüm alanları göndermek** güvenli taraftır (kısmi gönderim `vatRate`/`dimensionalWeight` sıfırlama riski taşır).

**`channels` kuralları** (`CORE` | `LUXE`, 31.07.2026'dan beri):
- Gönderilmezse ürün hangi kanal(lar)da yayındaysa onlar için işlem yapılır.
- Bir kanalı kapatmak için **yalnız açık kalacak kanalın** değeriyle istek atılır (örn. LUXE kapatılacaksa `["CORE"]`).
- `[]` boş dizi gönderilirse **hata döner**.
- LUXE için markanın Luxe statüsünde ve satıcının LUXE kategorisinde tanımlı olması gerekir.

`batchRequestType: ApprovedProductVariantUpdate`.

#### 4.2.5 `updateDeliveryInfoBulk` — Onaylı Ürün Teslimat Bilgisi Güncelleme v2
`POST /integration/product/sellers/{sellerId}/products/delivery-info-bulk-update`

```json
{"items":[{"barcode":"string",
  "deliveryOptions":{"deliveryDuration":0,"fastDeliveryType":"FAST_DELIVERY"}}]}
```
Maks 1000 item. `barcode` hariç tüm alanlar güncellenebilir. Alan adının burada **`deliveryOptions`** (çoğul) olduğuna dikkat — create ve unapproved-update'te **`deliveryOption`** (tekil).
`batchRequestType: ProductDeliveryOptionUpdate`. Bu batch tipinin echo'su tek storefront taşıyıcısıdır: `storeFrontCode: "TR"`, `storeFrontId: 1`.

#### 4.2.6 `updatePriceAndInventory` — Stok ve Fiyat Güncelleme
`POST /integration/inventory/sellers/{sellerId}/products/price-and-inventory`

> **Yol farkı:** servis segmenti `/integration/**inventory**/…`, diğer ürün servisleri gibi `/integration/product/…` **değil**. Ama sonucu yine `/integration/product/.../batch-requests/{id}` üzerinden sorgulanır. Sık yapılan hata.

```json
{"items":[{"barcode":"8680000000","quantity":100,
           "salePrice":112.85,"listPrice":113.85}]}
```

| Alan | Tip | Zorunlu | Kural |
|---|---|---|---|
| `barcode` | string | **Evet** | İçeri alınmış (normalize) barkod |
| `quantity` | integer | Hayır | **Satılabilir stok, mutlak değer** (delta değil). Sipariş geldikçe azalır. Ürün başına maks **20.000** |
| `salePrice` | number | Hayır | TSF |
| `listPrice` | number | Hayır | PSF, `>= salePrice` |

Maks **1000 SKU/istek**. *"Sadece stok veya fiyat bilgilerinden biri güncelleneceği zaman diğer alanın gönderilmesi zorunlu değildir."* → **yalnız değişeni gönderin**; "her zaman üç alanı da gönder" tasarımı 15 dakikalık çakışmaları maksimize eder.

**Yanıt:** `{"batchRequestId":"fa75dfd5-6ce6-4730-a09e-97563500000-1529854840"}`
**15 dakikalık dedup penceresi:** §3/K3. Yalnız onaylanmış ürünler için çalışır.
`batchRequestType: ProductInventoryUpdate` · **Bu batch tipinde üst düzey `status` alanı DÖNMEZ** (§6).

#### 4.2.7 `archiveProducts` — Ürün Arşivleme
`PUT /integration/product/sellers/{sellerId}/products/archive-state`

```json
{"items":[{"barcode":"barkod-1234","archived":true},
          {"barcode":"barkod-5678","archived":false}]}
```
Maks **1000** item. `barcode` maks **40 karakter** (barkod uzunluğunun açıkça yazıldığı tek sayfa). `archived` durum **setter**'dır, toggle değil → doğal olarak yakınsar; aynı batch'te true ve false karışık gönderilebilir.
`batchRequestType: ProductArchiveUpdate`. Echo'da ek alan: `items[].batchRequestLogId`, üst düzeyde `"notes": null`.

Arşivin stok/görünürlük üzerindeki tam etkisi **belgesiz** ⚠️ — stoku sıfırladığını varsaymayın.

#### 4.2.8 `deleteProducts` — Ürün Silme
`DELETE /integration/product/sellers/{sellerId}/products` — **gövdeli DELETE**

```json
{"items":[{"barcode":"test123"},{"barcode":"test456"}]}
```

**Silme uygunluğu (birebir):** *"Onay bekleyen ürünlerinizi ve **arşivde bir günden fazla bulunmuş**, Trendyol tarafından satışa durdurulmamış onaylı ürünlerinizi silebilirsiniz."*
→ Kanonik yaşam döngüsü: `archive(true)` → **>1 gün bekle** → `delete`. Başka her şey kalem düzeyinde başarısız olur, HTTP düzeyinde değil.

Maks item sayısı **belgesiz** ⚠️ (kardeş servisler 1000; 1000 varsayıp chunk'layın).
`batchRequestType: ProductDeletion`.
**İstemci tuzağı:** bazı HTTP kütüphaneleri DELETE'te gövdeyi düşürür. Guzzle/Laravel HTTP gönderir; kullandığınız istemciyi doğrulayın.

#### 4.2.9 `getUpdateAudits` — Ürün Bilgileri Güncelleme Sonucu Kontrol Servisi
`GET /integration/product/sellers/{sellerId}/products/{contentId}/update-audits`

| Param | Konum | Tip | Değerler |
|---|---|---|---|
| `contentId` | path | int64 | Ürün content id — **`batchRequestId` ile sorgulanmaz** |
| `page` | query | integer | Varsayılan belgesiz; örnekte `page: 1` dönüyor (1 tabanlı olabilir ⚠️) |
| `size` | query | integer | **Maks belgesiz** ⚠️; örneklerde `size: 100` |
| `status` | query | string | `SUCCESS` \| `FAIL` \| `RUNNING` |
| `storefrontcode` | header | string | Varsayılan `TR` |
| `Accept-Language` | header | string | `tr` gönderilirse yanıt Türkçe |

**Yanıt:**
```json
{"page":1,"size":100,"totalPage":1,
 "content":[{"barcode":"smoketest-397135","contentId":12600108,
   "batchRequestId":"f84e1d14-...","requestDate":"2275-08-06T14:37:09.074Z",
   "updates":[{"type":"TITLE","status":"FAIL",
     "completedDate":"2025-07-21T05:50:57.201884871Z",
     "rejectReasons":[{"type":"CHANGES_NOT_FOUND","reason":"No Changes Detected",
        "detail":"...","parameters":{}}],
     "changedTitle":"Test Ekin","existingTitle":"Test Ekin"}]}]}
```

`updates[]` bir **ayrık birleşimdir** (`type`'a göre alanlar değişir):

| `type` | Dolan alanlar |
|---|---|
| `TITLE` | `changedTitle`, `existingTitle` |
| `DESCRIPTION` | `changedDescription`, `existingDescription` |
| `MEDIA` | `changedMedias[{url,order}]`, `existingMedias[{_type,url,order}]` |
| `ATTRIBUTE` | `changedAttributes[{attributeId, attributeName, attributeValueId?, customAttributeValue?, mediaUrl?, isAllowedForUpdate}]`, `existingAttributes[{id, name, customValue?, attributeId, attributeName, slicer, varianter, allowCustom}]` |

**Gözlemlenen `rejectReasons[].type` değerleri:** `CHANGES_NOT_FOUND` ("gönderdiğiniz bilgi mevcut veriyle birebir aynı, güncelleme uygulanmadı"), `BANNED_WORD_IN_ATTRIBUTE` ("Ürünün özelliklerinde … yasaklı kelimesi bu markada kullanılmamalıdır"). **Tam katalog yayımlanmamış** ⚠️ — serbest metin olarak saklayın, kod akışını substring eşleşmesine bağlamayın.

**Tarih formatı tuzağı:** `requestDate` ve `completedDate` **string**tir, epoch ms değil. Gözlemlenen değerler ISO-8601 (`2025-07-21T05:50:57.201884871Z`, nanosaniye hassasiyeti) ama resmî bir format belirtimi **yok** ⚠️. Üstelik Trendyol'un kendi örneğinde `requestDate: "2275-08-06T..."` gibi imkânsız bir yıl var — savunmacı parse edin, parse edilemezse ham dizgiyi saklayın.

**Yanıtta `totalElements` yok**, yalnız `totalPage` (tekil, "s" yok) — diğer Trendyol liste servislerinden farklı.
Limit: 100 req/dk.

#### 4.2.10 `unlockProducts` — Ürün Kilit Kaldırma
`PUT /integration/product/sellers/{sellerId}/products/unlock` · gövde `{"items":[{"barcode":"..."}]}`
Düşük/yüksek fiyatlandırma, kritik fiyat hatası ve tedarik edememe sebebiyle satışı durdurulmuş ürünlerin kilidini açar.
`batchRequestType: ProductUnlockUpdate`. Örnek hata gövdeleri: `{"key":"invalid.barcode","message":"Barkod formatı geçersiz","errorCode":"400"}`, `{"key":"product.not.found","message":"Ürün sistemde bulunamadı","errorCode":"404"}`.

#### 4.2.11 `getBuyboxInformation`
`POST /integration/product/sellers/{sellerId}/products/buybox-information` — istek başına **maks 10 barkod**. Senkron. Product Integration **Write** grubunda sayılır (14 Eyl 2026 sonrası) — okuma gibi görünse de yazma bütçesini yer.

---

### 4.3 Ürün okuma / filtreleme

#### 4.3.1 `getProductBase` — Ürün Filtreleme Temel Bilgiler v2
`GET /integration/product/sellers/{sellerId}/product/{barcode}` · query parametresi yok.

```json
{"barcode":"smoketest-250049","approved":true,"approvedDate":1763622556000,
 "archived":false,"listingId":"a089a30ed1632032913b28099e49d948","contentId":9511264}
```

Create batch'i bittikten sonra "bu barkod yayında mı?" sorusunun **en ucuz** cevabı; kalıcı olarak saklamanız gereken iki Trendyol kimliğini (`contentId`, `listingId`) verir. Bilinmeyen barkod davranışı **belgesiz** ⚠️ (404 listelenmemiş).

#### 4.3.2 `filterUnapprovedProducts` — Onaysız Ürün v2
`GET /integration/product/sellers/{sellerId}/products/unapproved`

| Param | Tip | Maks | Anlam |
|---|---|---|---|
| `barcode` | string | – | Tekil barkod |
| `barcodes` | string | **50 barkod** | Çoklu barkod |
| `startDate` / `endDate` | int64 (epoch ms) | – | `dateQueryType`'a göre yorumlanır |
| `dateQueryType` | enum | – | `CREATED_DATE` (→ `createDateTime`) \| `LAST_MODIFIED_DATE` (→ `lastUpdateDate`) |
| `page` | integer | – | `page × size ≤ 10.000` |
| `size` | integer | **1000** | Bu gruptaki en büyük sayfa boyutu |
| `supplierId` | long | – | |
| `stockCode`, `productMainId`, `origin` | string | – | |
| `brandIds` | array | – | |
| `status` | enum | – | `rejected` \| `pendingApproval` |
| `nextPageToken` | string | – | 10.000'den fazla onaysız barkod varsa |

**Yanıt zarfı:** `{totalElements, totalPages, page, size, nextPageToken, content[]}`

`content[]` alanları: `supplierId`, `productMainId`, `status`, `createDateTime`, `lastUpdateDate`, `lastPriceChangeDate`, `lastStockChangeDate`, `brand{id,name}`, `category{id,name}`, `barcode`, `title`, `description`, `quantity`, `listPrice`, `salePrice`, `cargoProviders[]`, `vatRate`, `dimensionalWeight`, `stockCode`, `origin`, **`media[{url}]`**, `attributes[{attributeId, attributeName, attributeValueId, attributeValue}]`, `rejectReasonDetails[{rejectReason, rejectReasonDetail}]`, `locationBasedDelivery`, `lotNumber`.

**Tuzaklar:**
- Görsel dizisinin adı burada **`media`**, onaylı üründe **`images`**, create'te **`images`**. Üç isim, tek kavram.
- **Reddedilme sebebini veren tek endpoint budur.** `rejectReason` örnek değerleri: `"Sakıncalı Görsel Değişt"`, `"Zorunlu Ürün Özellik Değeri Eksik/Yanlış"`, `"Hatalı Marka Bilgisi"`, `"Satış Kurallarına Aykırı Ürün"`. **Kapalı bir enum değildir** ⚠️ — serbest metin olarak saklayın, geçmişini tutun.
- `page × size` **10.000'i aşamaz**; sonrasında `nextPageToken`.
- Maks tarih aralığı ve tarih verilmediğindeki varsayılan pencere **belgesiz** ⚠️.

#### 4.3.3 `filterApprovedProducts` — Onaylı Ürün v2
`GET /integration/product/sellers/{sellerId}/products/approved`

| Param | Tip | Maks | Anlam |
|---|---|---|---|
| `barcode` / `barcodes` | string | 50 | |
| `startDate` / `endDate` | int64 ms | – | |
| `dateQueryType` | enum | – | `VARIANT_CREATED_DATE` (→ `sellerCreatedDate`) \| `VARIANT_MODIFIED_DATE` (→ `sellerModifiedDate`) \| `CONTENT_MODIFIED_DATE` (→ `lastModifiedDate`) |
| `page` | integer | `page × size ≤ 10.000` | |
| `size` | integer | **100** | Onaysızdan 10× küçük — sık yapılan hata |
| `supplierId`, `stockCode`, `origin`, `productMainId`, `contentId` | – | – | |
| `brandIds` | array | – | |
| `status` | enum | – | **⚠️ ÇELİŞKİ:** TR kılavuz tablosu `archived, blacklisted, locked, onSale, **notOnSale**`; OpenAPI referansı `notOnSale` **olmadan** `archived, blacklisted, locked, onSale`. İkisi de resmî. Stage'de deneyin. |
| `orderByDirection` | enum | – | `ASC` \| `DESC`, `sellerCreatedDate` alanına göre |
| `nextPageToken` | string | – | >10.000 content |

**Yanıt CONTENT tabanlıdır**, varyantlar iç içedir → `totalElements`/`size` **content sayar, barkod değil**. `size=100` bir sayfa yüzlerce barkod döndürebilir.

`content[]`: `contentId`, `productMainId`, `brand{}`, `category{}`, `creationDate`, `lastModifiedDate`, `lastModifiedBy`, `title`, `description`, `images[{url}]`, `attributes[{attributeId, attributeName, attributeValue}]`, `variants[]`.

`variants[]`: `variantId`, `supplierId`, `barcode`, **`commission`**, `attributes[{attributeId, attributeName, attributeValueId, attributeValue}]`, `productUrl`, `onSale`, **`channels: ["CORE","LUXE"]`**, `deliveryOptions{deliveryDuration, isRushDelivery, fastDeliveryOptions[{deliveryOptionType, deliveryDailyCutOffHour}]}`, **`stock{quantity, lastModifiedDate}`**, `price{salePrice, listPrice, **priceSeenByCustomer**}`, `stockCode`, `origin`, `vatRate`, `sellerCreatedDate`, `sellerModifiedDate`, `locked`, `lockReason`, `lockDate`, `archived`, `archivedDate`, `docNeeded`, `hasViolation`, `blacklisted`.

**⚠️ ÇELİŞKİ — `quantity` dönüyor mu?** OpenAPI şeması `stock` altında yalnız `lastModifiedDate` tanımlıyor; TR kılavuz sayfasının örnek yanıtı **`stock.quantity`** içeriyor (ve `price.priceSeenByCustomer` ile `commission` de OpenAPI'de yok). Stage'de teyit edin; teyide kadar stok için `inventory-and-price` endpoint'ini otorite kabul edin.

**Attribute şekli içerik ve varyant düzeyinde farklıdır** — content düzeyinde `attributeValueId` yok/nullable olabilir, varyant düzeyinde düz `attributeValueId + attributeValue`. Tek mapper kullanmayın.

#### 4.3.4 `filterApprovedProductsInventoryAndPrice` — Onaylı Ürün Stok & Fiyat
`GET /integration/product/sellers/{sellerId}/products/approved/inventory-and-price`

| Param | Maks | Not |
|---|---|---|
| `barcode` | – | tekil |
| `barcodes` | **50** | Bilinen SKU setini mutabakat için en ucuz yol |
| `contentId`, `stockCode`, `productMainId` | – | |
| `status` | – | `archived, blacklisted, locked, onSale, notOnSale` (bu endpoint `notOnSale`'i kesin destekler) |
| `page` / `size` | `size ≤ 100`, `page × size ≤ 10.000` | |
| `orderByDirection` | `asc` \| `desc` | `sellerCreatedDate`'e göre — deterministik tam tarama için `asc` |
| `nextPageToken` | – | >10.000 content |

**Yanıt:**
```json
{"totalElements":1,"totalPages":1,"page":0,"size":1,
 "nextPageToken":"eyJzb3J0IjpbMTc4MDQ0NTY5MjAwMasasf",
 "content":[{"contentId":12431242141,"productMainId":"1242141241",
   "variants":[{"variantId":3953959353,"barcode":"60506560","salePrice":699.99,
     "listPrice":699.99,"quantity":50,"stockCode":"056565964",
     "stockLastModifiedDate":1780463592464}]}]}
```

**Tuzaklar:** **Tarih filtresi yok** (ne `startDate` ne `dateQueryType`) → artımlı senkron `stockLastModifiedDate` ya da onaylı ürün endpoint'inin `dateQueryType`'ı üzerinden sürülmeli · `stockLastModifiedDate` ürüne **hiç stok güncellemesi yapılmadıysa `null`** döner — epoch 0'a ya da "hiç stok yok"a çevirmeyin · İçerik/görsel/attribute dönmez, sık senkron döngüsünün doğru endpoint'i budur.

---

### 4.4 Sipariş / paket okuma

#### 4.4.1 `getShipmentPackages` — Sipariş Paketlerini Çekme
| Sürüm | Yol | Durum |
|---|---|---|
| **V2 (kullanılmalı)** | `GET /integration/order/sellers/{sellerId}/v2/orders` | 15 Ekim 2026'dan itibaren **zorunlu** |
| V1 (emekli oluyor) | `GET /integration/order/sellers/{sellerId}/orders` | 15 Ekim 2026'da kapanıyor; o tarihe kadar **günde 3 kez 10'ar dakika `426`** döner |

| Param | Tip | Varsayılan | Maks | Anlam |
|---|---|---|---|---|
| `startDate` | int64 epoch ms | – | – | "Timestamp (milliseconds) ve **GMT +3** olarak gönderilmelidir" |
| `endDate` | int64 epoch ms | – | – | Aynı |
| `page` | integer | `0` | `page × size ≤ 10.000` → güvenli aralık **page 0–49** | 0 tabanlı |
| `size` | integer | `200` | **200** | |
| `supplierId` | long | – | – | |
| `orderNumber` | string | – | – | Tek sipariş |
| `status` | enum | – | – | Tek değer; §5.1 |
| `orderByField` | enum | – | – | `PackageLastModifiedDate` \| `CreatedDate` |
| `orderByDirection` | enum | – | – | `ASC` \| `DESC` |
| `shipmentPackageIds` | int64[] | – | **50 id** | Serileştirme stili belgesiz ⚠️ (OpenAPI varsayılanı `form/explode` → tekrarlı anahtar) |

**Tarih pencereleri (kılavuz sayfası, birebir):**
- Tarih parametresi **hiç verilmezse** → **son 1 hafta**.
- `startDate`+`endDate` verilirse → **maksimum 2 hafta** aralık.
- Erişilebilir geçmiş: **maksimum 1 ay** (5 Mart 2026'dan beri; öncesi 3 aydı).

**10.000 kayıt penceresi (15 Ekim 2026):** sayaç **`shipmentPackageId` bazındadır**, `orderNumber` bazında değil; bölünmüş paketlerin her biri ayrı sayılır, satır/adet sayılmaz. `totalElements > 10000` ise: (1) `orders/stream`'e geçin, (2) tarih aralığını bölün, (3) filtreyi daraltın (`status`, `orderNumber`, `barcode`, `shipmentPackageIds`, `storeFrontCode`, `startDate/endDate`). **`totalPages` 50'den büyük görünebilir; bu erişilebilir olduğu anlamına gelmez.**

**Yanıt zarfı:** `{totalElements, totalPages, page, size, content[]}`

`content[]` (paket) alanları — kılavuz örneğinden, tam liste:
`shipmentAddress{}`, `orderNumber`, `orderCountryCode`, `packageGrossAmount`, `packageSellerDiscount`, `packageTyDiscount`, `packageTotalDiscount`, `discountDisplays[{displayName, discountAmount}]`, `taxNumber`, `invoiceAddress{}`, `customerFirstName`, `customerLastName`, `customerEmail`, `customerId`, `supplierId`, `channelId` (**1 = TR core, 25 = luxury**), `shipmentPackageId`, `cargoTrackingNumber`, `cargoTrackingLink`, `cargoSenderNumber`, `sellerDeliveryMethod` (TR'de daima null), `sellerOtpCode` (TR'de daima null), `cargoProviderName`, `lines[]`, `orderDate`, `identityNumber`, `currencyCode`, `packageHistories[{createdDate, status}]`, `shipmentPackageStatus`, `status`, `whoPays` (**1 = satıcı anlaşması; Trendyol anlaşmasıysa alan hiç gelmez**), `deliveryType`, `timeSlotId`, `estimatedDeliveryStartDate`, `estimatedDeliveryEndDate`, `packageTotalPrice`, `deliveryAddressType` (`Shipment` \| `CollectionPoint`), `agreedDeliveryDate`, `fastDelivery`, `fastDeliveryType`, `originShipmentDate`, **`lastModifiedDate`**, `commercial`, `deliveredByService`, `warehouseId`, `invoiceLink`, `invoiceNumber`, `invoiceStatus`, `invoiceRejectedReasonKeys`, `micro`, `giftBoxRequested`, `3pByTrendyol`, `etgbNo`, `etgbDate`, `containsDangerousProduct`, `cargoDeci`, `isCod`, `createdBy`, `originPackageIds`, `hsCode`, `shipmentNumber`, `is4P`.

`lines[]` alanları:
`quantity`, `salesCampaignId`, `productSize`, `stockCode`, `productName`, `contentId`, `productOrigin`, `sellerId`, `lineGrossAmount`, `lineTotalDiscount`, `lineSellerDiscount`, `lineTyDiscount`, `discountDetails[{lineItemPrice, lineItemSellerDiscount, lineItemTyDiscount}]`, `currencyCode`, `productColor`, `lineId`, `vatRate`, `barcode`, `orderLineItemStatusName`, `lineUnitPrice`, `fastDeliveryOptions[]`, `productCategoryId`, **`commission`** (oran), `businessUnit`, `cancelledBy`, `cancelReason`, `cancelReasonCode`, `defectiveClaimListingInsight`.

`shipmentAddress` / `invoiceAddress`:
`id`, `firstName`, `lastName`, `company`, `address1`, `address2`, `city`, `cityCode`, `district`, `districtId`, `countyId` (CEE), `countyName` (CEE), `shortAddress` (GULF), `stateName` (GULF), `addressLines{addressLine1, addressLine2}`, `postalCode`, `sector`, `countryCode`, `neighborhoodId`, `neighborhood`, `phone`, `latitude`, `longitude`, `fullAddress`, `fullName`, `taxOffice`*, `taxNumber`*, `eInvoiceAvailable`*
(*) `commercial=false` ise gövdede **hiç dönmez**.

**⚠️ ÇELİŞKİ — `lastModifiedDate`:** kılavuz örnek yanıtında var (`"lastModifiedDate": 1762865408581`), OpenAPI `ShipmentPackage` şemasında **yok**. Şemaya güvenip alanı atmayın; artımlı senkron için kritik.
**⚠️ ÇELİŞKİ — alan adları:** OpenAPI şeması hâlâ 6 Nisan 2026'da **kaldırılmış** eski adları listeliyor (`id`, `merchantSku`, `merchantId`, `amount`, `discount`, `tyDiscount`, `price`, `grossAmount`, `totalDiscount`, `totalTyDiscount`, `productCode`, `vatBaseAmount`, `sku`). **Kılavuz sayfasının alan adları doğrudur** (§12).
**⚠️ ÇELİŞKİ — rate limit:** sayfa gövdesi *"Bu servise 1 dakika içinde en fazla 1000 adet istek atabilirsiniz"* diyor; kademeli limit tablosu **30–100 req/dk** diyor. Tablo daha yenidir (8 Haziran 2026 duyurusu) ve otoritedir.

**İş kuralları:**
- `Awaiting` statüsündeki siparişler **yalnız stok işlemleri** içindir. Kargoya verirseniz iptal riskini Trendyol üstlenmez. İleride bu statüde veri dönmeyebilir.
- İptaller: `status=Cancelled,UnSupplied`. Bölünmüşler: `status=UnPacked`.
- **Kısmi iptal veya bölme, `orderNumber`'ı korur ama YENİ `shipmentPackageId` + yeni kargo barkodu üretir.** `createdBy` hangisi olduğunu söyler; `originPackageIds` öncekini işaret eder.
- `micro=true` → mikro ihracat. `countryCode` ile eşleştirmeyin, bağımsız alanlardır.
- `commercial=true` → kurumsal fatura; `invoiceAddress.company/taxNumber/taxOffice/eInvoiceAvailable` dolar.
- Altın, gübre veya 5000₺ üzeri siparişlerde TCKN `identityNumber` alanında gelir.
- `businessUnit = "Digital Goods"` ise müşteri telefonu `null` döner.
- Mikro ihracat / Yurt Dışı Aracılığı ve GULF siparişlerinde adres alanları **boş dönebilir**; tekrar istek atınca dolar. İlçe kontrolü yapan doğrulamaları kaldırın.
- `cargoTrackingNumber` CODE128 barkodudur ve int64'ü aşabilir → **uçtan uca string** olarak taşıyın.

#### 4.4.2 `getShipmentPackagesStream` — Sipariş Paketlerini Akış ile Çekme
`GET /integration/order/sellers/{sellerId}/orders/stream`

| Param | Tip | Varsayılan | Maks | Anlam |
|---|---|---|---|---|
| `size` | integer | **50** | **200** | Varsayılan sayfalı servisten farklı (orada 200) |
| `nextCursor` | string (opak) | – | – | **İlk istekte GÖNDERİLMEZ.** Yanıttaki değer **birebir** kullanılır — parse etmeyin, değiştirmeyin |
| `packageItemStatuses` | string | – | – | **Virgülle ayrılmış çoklu** değer. Bu **paket kalemi (satır)** statüsüdür, paket statüsü değil |
| `lastModifiedStartDate` | int64 ms (GMT+3) | – | – | Bu tarihten sonra değişenler |
| `lastModifiedEndDate` | int64 ms (GMT+3) | – | – | Bu tarihe kadar değişenler |
| `supplierId` | long | – | – | |

**Yanıt:** `{content[], size, hasMore, nextCursor}` — **`totalElements`, `totalPages`, `page` DÖNMEZ.** `content[]` şeması sayfalı servisle **birebir aynı**.

**Kritik kurallar (birebir):**
- `nextCursor` **opak**tır → parse/değiştirme yasak.
- Aynı cursor kullanılırken **filtreler değiştirilmemelidir**; değişirse **400 Bad Request** (`"cursor ile filtre uyumsuzluğu"`).
- **Sıralama sabit: `lastModifiedDate` DESC** — yapılandırılamaz.
- Yeni filtreyle çalışmak için **yeni akış başlatılmalı**.
- **Önerilen: minimum 5 saniye aralıklarla istek.**
- Veri kapsamı: **son 3 ay**. Zaman aralığı **maks 2 hafta**; tarih gönderilmezse sistem otomatik son 2 haftaya sınırlar.

**Tuzaklar:**
- `size` hem istek parametresi hem yanıt alanıdır ve yanıttaki **gerçekte dönen adettir**. `hasMore=true` ile kısa pencere yasaldır.
- Cursor ömrü **belgesiz** ⚠️ → 400 alırsanız son checkpoint'inizin `lastModifiedStartDate`'i ile akışı yeniden başlatın.
- Sınır dâhil/hariç semantiği **belgesiz** ⚠️ ("…tarihten sonra" / "…tarihe kadar") → pencereleri güvenlik payıyla örtüştürün ve `shipmentPackageId` üzerinden dedupe edin.
- Statü filtresi sayfalı servisten **anlamca farklıdır** (`status` paket düzeyi, tek değer vs `packageItemStatuses` satır düzeyi, çoklu) — tek mapper paylaşmayın.

---

### 4.5 İade / Claims

| Op | Metod | Yol |
|---|---|---|
| `getClaims` | GET | `/integration/order/sellers/{sellerId}/claims` |
| `createClaim` | POST | `/integration/order/sellers/{sellerId}/claims/create` |
| `approveClaimLineItems` | PUT | `/integration/order/sellers/{sellerId}/claims/{claimId}/items/approve` |
| `createClaimIssue` (ret) | POST | `/integration/order/sellers/{sellerId}/claims/{claimId}/issue` |
| `getClaimIssueReasons` | GET | `/integration/order/claim-issue-reasons` *(sellerId yok)* |
| `getClaimItemAudits` | GET | `/integration/order/sellers/{sellerId}/claims/items/{claimItemsId}/audit` |

#### 4.5.1 `getClaims`

| Param | Tip | Varsayılan | Maks | Anlam |
|---|---|---|---|---|
| `startDate` / `endDate` | int64 epoch ms | – | – | Hangi alana (claimDate mi lastModifiedDate mi) uygulandığı **belgesiz** ⚠️. Maks pencere **belgesiz** ⚠️ |
| `page` | integer | `0` | – | |
| `size` | integer | `50` | **200** | |
| `claimItemStatus` | enum | – | – | §5.3. Tek değer; çoklu destek **belgesiz** ⚠️ |
| `orderNumber` | string | – | – | İade paketinin sipariş numarası |
| `claimIds` | int64[] | – | – | **⚠️ TİP ÇELİŞKİSİ:** parametre `integer(int64)` tanımlı ama yanıttaki `id`/`claimId` **string UUID**. Stage'de deneyin; depoda string tutun |

**Yanıt:** `{totalElements, totalPages, page, size, content[]}`

`content[]` (claim başlığı): `id`, `claimId`, `orderNumber`, `orderDate`, `customerFirstName`, `customerLastName`, `claimDate`, `cargoTrackingNumber`, `cargoTrackingLink`, `cargoSenderNumber`, `cargoProviderName`, `orderShipmentPackageId`, `replacementOutboundpackageinfo{}`, `rejectedpackageinfo{}`, `items[]`, `lastModifiedDate`, `orderOutboundPackageId`.

`items[]` → `orderLine{id, productName, barcode, merchantSku, productColor, productSize, price, vatBaseAmount, vatRate, salesCampaignId, productCategory}` + `claimItems[]`.

`claimItems[]`: `id` (**UUID — approve/reject çağrılarının `claimLineItemId`'si**), `orderLineItemId`, `customerClaimItemReason{name, externalReasonId, code}`, `trendyolClaimItemReason{...}`, `claimItemStatus{name}`, `note`, `customerNote`, `resolved`, `autoAccepted`, `acceptedBySeller`, `acceptDetail` (`SUPPLIER` \| `DISPUTE` \| `SYSTEM`).

`rejectedpackageinfo` ek alan: **`dontShipBack`** (boolean) — `true` ise ürün satıcıya geri gönderilemez, ters lojistiği tamamen değiştirir.

**Tuzaklar:**
- **Claim başlığında statü YOKTUR.** Statü yalnız kalem düzeyindedir; tek claim aynı anda farklı statülerde kalemler taşıyabilir. Başlığa statü kolonu uydurmayın.
- `claimItemStatus` yanıtta **nesne** (`{"name":"Created"}`), query parametresinde **düz string**. Aynı cast'ı kullanmayın.
- Anahtar adları tutarsız: `replacementOutboundpackageinfo`, `rejectedpackageinfo`, iç `packageid` (hepsi küçük harfli), çevredeki camelCase'e uymaz. Birebir eşleyin.
- `cargoTrackingNumber` `integer(int64)` tanımlı ama Trendyol'un **kendi örneği** `72602420957047276000` — int64'ü **ve** JS güvenli tamsayı sınırını aşıyor. **String olarak parse edin.**
- `productSize` değerlerinde **baştan boşluk** var (`" 21"`) → ingest'te trim.
- **`autoAccepted`: 48 saat beklemede kalan iadeler sistem tarafından otomatik kabul edilir.** Poller'ınız 48 saatten yavaşsa itiraz hakkını kaybedersiniz. `requested_at + 48h` deadline'ı hesaplayıp kuyruğu ona göre sıralayın.
- `merchantSku` bu servisin yanıtında hâlâ duruyor (sipariş servisinde kaldırıldı) — pazaryeri genelinde tek tip isim varsaymayın.
- Sıralama garantisi **belgesiz** ⚠️ → dar tarih penceresi + `(claimId, claimItems[].id)` üzerinden dedupe.

#### 4.5.2 Yazma operasyonları
- `approveClaimLineItems` gövde: `{"claimLineItemIdList": ["<uuid>"], "params": {}}` · limit **5 req/dk**.
- `createClaimIssue` (ret talebi): **`multipart/form-data`** — ek dosyalar (pdf, jpeg) `file` alanıyla; `claimIssueReasonId` `getClaimIssueReasons`'tan. **Reason `1651`, claim `WaitingInAction`'a girdikten sonraki ilk 24 saatte kullanılamaz.** Limit **5 req/dk**.
- `createClaim` gövde: `{"orderNumber": "...", "claimItems":[{"barcode","quantity","reasonId","customerNote"?}]}` — iade kodu olmadan gelen paketler için.

**Akış:** müşteri iade açar → `getClaims` → `customerClaimItemReason` incelenir → **onay** (`approveClaimLineItems`) **veya red** (`getClaimIssueReasons` → `createClaimIssue`) → `getClaims` ile tekrar kontrol → onaylanan red müşteriye geri gönderilir.
**Yalnız `WaitingInAction` statüsündeki kalemler için aksiyon alınabilir.**

---

### 4.6 Müşteri soruları (Q&A)

| Op | Metod | Yol |
|---|---|---|
| `getQuestionFilter` | GET | `/integration/qna/sellers/{sellerId}/questions/filter` |
| `getQuestion` | GET | `/integration/qna/sellers/{sellerId}/questions/{id}` |
| `answerQuestion` | POST | `/integration/qna/sellers/{sellerId}/questions/{id}/answers` |

#### 4.6.1 `getQuestionFilter`

| Param | Tip | Varsayılan | Anlam |
|---|---|---|---|
| `barcode` | string | – | Tek ürünün soruları |
| `startDate` / `endDate` | int64 epoch ms | – | Örn. `1767214800000` |
| `status` | enum | – | §5.5 |
| `orderByField` | enum | **`CreatedDate`** | `CreatedDate` \| `LastModifiedDate` |
| `orderByDirection` | enum | **`DESC`** | `ASC` \| `DESC` |
| `page` | int32 | **`0`** | 0 tabanlı (kılavuzda açıkça yazılı) |
| `size` | int32 | **⚠️ ÇELİŞKİ** | OpenAPI: varsayılan `10`, maks **200**. Kılavuz: varsayılan `20`, maks **50** ("Maksimum sayfa boyutu 50'dir"). **`size ≤ 50` kullanın ve her zaman açıkça gönderin.** |
| `supplierId` | long | – | Kılavuz "zorunlu" diyor, OpenAPI'de query parametresi yok — path'teki `{sellerId}` kastediliyor ⚠️ |

**Tarih davranışı:**
- Tarih verilmezse → **son 1 hafta**.
- Tarih verilirse → **maksimum 2 hafta**. Aşarsanız **hata dönmez**, `endDate` sessizce `startDate + 2 hafta`ya çekilir. **Backfill için veri kaybı tuzağı** → kendiniz ≤14 günlük dilimlere bölün.

**Yanıt:** `{content[], page, size, totalElements, totalPages}`

`Question`: `id`, `text`, `customerId`, `userName` (maskeli; `showUserName=false` ise **boş**), `showUserName`, `status`, `public`, `productMainId` (model kodu), `productName`, `imageUrl`, `webUrl`, `creationDate`, `answeredDateMessage` (**yerelleştirilmiş insan metni — asla parse etmeyin**), `answer`, `rejectedAnswer`, `rejectedDate`, `reason`, `reportReason`, `reportedDate`.

`Answer` — **iki resmî sayfa farklı alanlar veriyor**, birleşimini parse edin: `id` (kılavuz), `text` (ikisi), `creationDate` (ikisi), `status` (yalnız OpenAPI, **değerleri belgesiz** ⚠️), `hasPrivateInfo` (kılavuz), `reason` (kılavuz, yalnız `rejectedAnswer` için).

**Önerilen polling:** `.../questions/filter?startDate={}&endDate={}&status=WAITING_FOR_ANSWER`

#### 4.6.2 `getQuestion`
`GET .../questions/{id}` — query parametresi yok. Yanıt **çıplak `Question` nesnesi**, sayfalama zarfı yok. Başka satıcının sorusu istenirse davranış **belgesiz** ⚠️ (404/403 ikisini de handle edin).

#### 4.6.3 `answerQuestion`
`POST .../questions/{id}/answers` · `Content-Type: application/json`
Gövde: `{"text": "..."}` — **tek alan**. `minLength: 10`, `maxLength: 2000`. Karakter sayılır, bayt değil → PHP'de `mb_strlen`.

**⚠️ ÇELİŞKİ — yanıt şekli:**
| Kaynak | Yanıt |
|---|---|
| OpenAPI referansı | `application/json`, şema `string` → çıplak dizgi `"Cevabınız başarıyla kaydedilmiştir."` |
| Kılavuz sayfası | `{"answerId": 0}` |

İkisini de tolere edin: nesne parse ediliyorsa `answerId` okuyun, dizgiyse `answerId = null`.

**Moderasyon asenkron ve örtüktür — bu grubun en önemli davranışı.** `200` = "değerlendirmeye gönderildi", "yayınlandı" değil. `batchRequestId` **yok**, cevabın statüsünü sorgulayan endpoint **yok**. Tek yol soruyu tekrar okumaktır.

Durum makinesi:
`WAITING_FOR_ANSWER` --POST--> (moderasyon) --> `ANSWERED` (yayında) **|** geri `WAITING_FOR_ANSWER` (reddedildi, tekrar denenebilir; eski metin `rejectedAnswer`'a düşer) **|** `UNANSWERED` (terminal, N kez yasaklı kelime sonrası kilitlenir — **N belgesiz** ⚠️).

**SLA:** **3 iş günü** içinde cevaplanmayan soru `UNANSWERED`'a geçer ve **bir daha cevaplanamaz**. `creationDate + 3 iş günü` deadline'ını hesaplayıp kuyruğu yaşa göre önceliklendirin.

**Belgelenmiş hata koşulları** (yalnız Türkçe düz metin, **makine kodu ya da HTTP statüsü yayımlanmamış** ⚠️): "Soru daha önce cevaplanmış" · "Süre limiti aşılmış" · "Yasaklı kelime limiti aşılmış" · "Cevap çok kısa" · "Cevap çok uzun" · "Cevap boş".

**Idempotent değildir**, idempotency anahtarı yoktur. Zaman aşımına uğrayan ama aslında başarılı olan bir istek **güvenle tekrarlanamaz** → göndermeden önce `(question_id, text_hash, timestamp)` kaydedin, belirsiz hatada soruyu yeniden okuyun.

**Soru raporlama API'si yoktur** — yalnız satıcı panelinden yapılır. **Yeni soru webhook'u yoktur** — polling tek yoldur.

---

### 4.7 Webhook yönetimi

| Op | Metod | Yol | Yanıt |
|---|---|---|---|
| `createWebhook` | POST | `/integration/webhook/sellers/{sellerId}/webhooks` | `200` JSON `{"id":"string"}` |
| `getWebhooks` | GET | `/integration/webhook/sellers/{sellerId}/webhooks` | `200` JSON **çıplak dizi** |
| `updateWebhook` | PUT | `/integration/webhook/sellers/{sellerId}/webhooks/{Id}` | `200` **text/plain** `"200 OK"` |
| `deleteWebhook` | DELETE | `/integration/webhook/sellers/{sellerId}/webhooks/{Id}` | `200` **text/plain** `"200 OK"` |
| `activateWebhook` | PUT | `/integration/webhook/sellers/{sellerId}/webhooks/{Id}/activate` | `200` **text/plain** `"200 OK"` |
| `deactivateWebhook` | PUT | `/integration/webhook/sellers/{sellerId}/webhooks/{Id}/deactivate` | `200` **text/plain** `"200 OK"` |

**Create/update gövdesi (aynı şema):**
```json
{"url":"https://testwebhook.com","username":"user","password":"password",
 "authenticationType":"API_KEY","apiKey":"123456",
 "subscribedStatuses":["CREATED","PICKING"]}
```

| Alan | Tip | Zorunlu | Not |
|---|---|---|---|
| `url` | string | **Evet** | `Trendyol`, `Dolap`, `Localhost` ibarelerini **içeremez** |
| `authenticationType` | enum | **Evet** | `BASIC_AUTHENTICATION` \| `API_KEY` |
| `username` / `password` | string | `BASIC_AUTHENTICATION` ise | |
| `apiKey` | string | `API_KEY` ise | Trendyol size **`x-api-key`** başlığında gönderir |
| `subscribedStatuses` | string[] | Hayır | **Boş gönderilirse TÜM statüler otomatik atanır**, ve listeye sonradan eklenen statüler de aboneliğinize otomatik eklenir |

**`getWebhooks` yanıtı:**
```json
[{"id":"5297c986-...","createdDate":1733317686667,"lastModifiedDate":1734010262454,
  "url":"https://testwebhook1.com","username":"test1",
  "authenticationType":"BASIC_AUTHENTICATION","status":"PASSIVE",
  "subscribedStatuses":["CREATED","CANCELLED","SHIPPED","DELIVERED","UNPACKED"]}]
```
Sayfalama **yok** (pratikte 15 kayıt tavanıyla sınırlı). `createdDate`/`lastModifiedDate` `int64`, dokümanda **"timestamp GMT +3"** etiketli; `lastModifiedDate` ilk güncellemeye kadar `null`.

**Tuzaklar:**
- **`password` ve `apiKey` asla geri dönmez** (write-only). Secret'ların tek doğruluk kaynağı sizsiniz.
- `updateWebhook` **tam değiştirmedir (PUT)**, patch değil → her güncellemede secret'ları yeniden göndermek zorundasınız. Update'te `subscribedStatuses` boş bırakmanın semantiği **belgesiz** ⚠️ ("boş = tümü" kuralı yalnız create için yazılı) → **her zaman açık liste gönderin**.
- Yanıt tipleri tutarsız: create JSON, diğer beşi `text/plain`. `json_decode` denemeyin.
- `deleteWebhook` ikinci kez çağrılırsa **404** → senkronizasyon mantığınızda 404'ü "zaten yok = başarı" sayın. Aynısı activate/deactivate için geçerli.
- Duplicate URL reddi **belgesiz** ⚠️ → `unique(marketplace_account_id, callback_url)` kısıtını kendiniz koyun, iki kez create etmeyin.
- Pasifken oluşan olayların kuyruklanıp reaktivasyonda tekrar oynatılıp oynatılmadığı **belgesiz** ⚠️ → kaybedildiğini varsayıp deaktivasyon penceresinden sonra `orders/stream` ile mutabakat yapın.

---

### 4.8 Listede yoktu ama zorunlu / mevcut

Bu alt bölüm, talep listesinde geçmeyen ama yukarıdakileri **çalıştırmak için gereken** ya da adaptörün eksiksiz olması için bilinmesi gereken endpoint'leri toplar.

#### 4.8.1 Zorunlu bağımlılıklar

| Op | Metod | Yol | Neden zorunlu |
|---|---|---|---|
| **`getBatchRequestResult`** | GET | `/integration/product/sellers/{sellerId}/products/batch-requests/{batchRequestId}` | Her ürün/stok/fiyat yazmasının **tek** sonuç kaynağı. §6 |
| **`getUpdateAudits`** | GET | `/integration/product/sellers/{sellerId}/products/{contentId}/update-audits` | Onaylı içerik güncellemesinin **ikinci** sonuç aşaması. §4.2.9 |
| **`getSuppliersAddresses`** | GET | `/integration/sellers/{sellerId}/addresses` | `shipmentAddressId` / `returningAddressId` buradan gelir |
| **`getCargoProviders`** | GET | `/integration/product/lookup/cargo-providers` | `cargoProviders[]` kodları buradan gelir |

**`getSuppliersAddresses` yanıtı:** `supplierAddresses[]` — her kayıtta `addressType` ∈ `Shipment`, `Invoice`, `Returning`, ayrıca `isShipmentAddress`, `isInvoiceAddress`, `isReturningAddress`, `isDefault`; üst düzeyde `defaultShipmentAddress` vb.
**Limit: 1 istek / SAAT.** Bu gruptaki en agresif limit. Satıcı başvuru süreci tamamlanmadan çağırmayın. Sonucu kalıcı olarak cache'leyin.

**`getCargoProviders` yanıtı:** `[{code, name}]`. Bilinen değerler:

| Id | `code` | Ad | Vergi No |
|---:|---|---|---|
| 38 | `SENDEOMP` | Kolay Gelsin Marketplace | 2910804196 |
| 30 | `CEVATEDARIK` | Ceva Tedarik Marketplace | 1800038254 |
| 10 | `DHLECOMMP` | DHL eCommerce Marketplace | 6080712084 |
| 19 | `PTTMP` | PTT Kargo Marketplace | 7320068060 |
| 9 | `SURATMP` | Sürat Kargo Marketplace | 7870233582 |
| 17 | `TEXMP` | Trendyol Express Marketplace | 8590921777 |
| 6 | `HOROZMP` | Horoz Kargo Marketplace | 4630097122 |
| 20 | `CEVAMP` | CEVA Marketplace | 8450298557 |
| 4 | `YKMP` | Yurtiçi Kargo Marketplace | 3130557669 |
| 7 | `ARASMP` | Aras Kargo Marketplace | 720039666 |

**⚠️ ÇELİŞKİ:** `changeCargoProvider` enum'u `YKMP, ARASMP, SURATMP, HOROZMP, DHLECOMMP, PTTMP, CEVAMP, TEXMP, **KOLAYGELSINMP**, CEVATEDARIK` listeliyor — id tablosunda aynı firma **`SENDEOMP`**. İki kod da resmî belgede geçiyor. Kod tablosunu **hard-code etmeyin**, `getCargoProviders`'tan okuyun.

#### 4.8.2 Sipariş statü geçişleri ve paket mutasyonları

| Op | Metod | Yol | Gövde |
|---|---|---|---|
| `updatePackageStatus` | PUT | `/integration/order/sellers/{sellerId}/shipment-packages/{packageId}` | `{"status":"Picking"\|"Invoiced","lines":[{"lineId","quantity"}],"params":{"invoiceNumber":"..."}}` |
| `cancelOrderPackageItem` (tedarik edememe) | PUT | `.../shipment-packages/{packageId}/items/unsupplied` | `{"lines":[{"lineId","quantity"}],"reasonId":500}` |
| `updateBoxInfo` | PUT | `.../shipment-packages/{packageId}/box-info` | `{"deci":..,"boxQuantity":..}` — **yalnız UPS & CEVA** |
| `changeCargoProvider` | PUT | `.../shipment-packages/{packageId}/cargo-providers` | `{"cargoProvider":"YKMP"}` |
| `updateWarehouse` | PUT | `.../shipment-packages/{packageId}/warehouse` | `{"warehouseId":...}` |
| `extendAgreedDeliveryDate` | PUT | `.../shipment-packages/{packageId}/extended-agreed-delivery-date` | `{"extendedDayCount": 1\|2\|3}` |
| `updateLaborCosts` | PUT | `.../shipment-packages/{packageId}/labor-costs` | `[{"orderLineId":..,"laborCostPerItem":..}]` — yalnız `Delivered` öncesine kadar, kısıtlı kategori id'leri |
| `deliveredByService` | PUT | `.../shipment-packages/{packageId}/delivered-by-service` | Yetkili servis ile teslim |

**`status` yalnızca `Picking` ve `Invoiced` kabul eder.** Sıra önemlidir: önce `Picking`, sonra `Invoiced`. `invoiceNumber` **`params` içinde** ve **yalnız `Invoiced` ile** gönderilir. `Shipped` sonrası müşteri iptal edemez.

**Tedarik edememe sonrası paket yok edilir; aynı `orderNumber` altında YENİ `shipmentPackageId` + yeni `cargoTrackingNumber` oluşur** → yeniden çekmek zorundasınız.

#### 4.8.3 Paket bölme (4 ayrı endpoint, hepsi asenkron)

| Op | Metod | Yol | Gövde |
|---|---|---|---|
| `splitShipmentPackage` | POST | `.../shipment-packages/{packageId}/split` | `{"orderLineIds":[...]}` |
| `multiSplitShipmentPackage` | POST | `.../shipment-packages/{packageId}/multi-split` | `{"splitGroups":[{"orderLineIds":[...]}]}` |
| `splitShipmentPackageByQuantity` | POST | `.../shipment-packages/{packageId}/quantity-split` | `{"quantitySplit":[{"orderLineId":..,"quantities":[..]}]}` |
| `splitMultiPackagesByQuantity` | POST | `.../shipment-packages/{packageId}/split-packages` | `{"splitPackages":[{"packageDetails":[{"orderLineId":..,"quantities":..}]}]}` |

Orijinal paket **`UnPacked`**'e geçer; yeni paketler yeni id ve yeni takip numarası alır.

#### 4.8.4 Alternatif teslimat & manuel teslim/iade

> **⚠️ YOL ÇELİŞKİSİ.** Kılavuz sayfaları ve OpenAPI referansı manuel teslim/iade yollarında uyuşmuyor. **İkisini de stage'de deneyin.**

| Op | Metod | Yol (OpenAPI referansı) | Yol (kılavuz sayfası) |
|---|---|---|---|
| `processAlternativeDelivery` | PUT | `.../shipment-packages/{packageId}/alternative-delivery` | aynı |
| `processAlternativeDeliveryDigital` | PUT | `.../shipment-packages/{packageId}/alternative-delivery-digital` | — |
| `manualDeliverByPackageId` | PUT | `.../shipment-packages/{packageId}/manual-invoice-delivery` | `.../shipment-packages/{packageId}/manual-deliver` |
| `manualDeliverByTrackingNumber` | PUT | `.../shipment-packages/manual-invoice-delivery-by-tracking-number/{ctn}` | `.../sellers/{sellerId}/manual-deliver/{ctn}` |
| `manualReturnByPackageId` | PUT | `.../shipment-packages/{packageId}/manual-return` | aynı |
| `manualReturnByTrackingNumber` | PUT | `.../shipment-packages/manual-return-by-tracking-number/{ctn}` | `.../sellers/{sellerId}/manual-return/{ctn}` |

`alternative-delivery` gövdesi: `{"isPhoneNumber": bool, "trackingInfo": "string", "params": {}, "boxQuantity"?: int, "deci"?: float}`.
`isPhoneNumber=false` → `trackingInfo` bir kargo takip **linki**; `true` → **telefon numarası**. Başarı paketi otomatik olarak `Shipped`'e taşır. Manuel teslim/iade **gövde almaz**, `200 OK` döner. Dijital varyant müşteriye otomatik SMS/e-posta gönderir; `businessUnit != "Digital Goods"` olan bir pakete dijital kod gönderilirse `digital.good.business.unit.not.valid` hatası döner.

#### 4.8.5 Fatura & belge

| Op | Metod | Yol | Gövde / not |
|---|---|---|---|
| `sendInvoiceLink` | POST | `/integration/sellers/{sellerId}/seller-invoice-links` | `{invoiceLink, shipmentPackageId, invoiceDateTime, invoiceNumber}` |
| `deleteInvoiceLink` | POST | `/integration/sellers/{sellerId}/seller-invoice-links/delete` | `{serviceSourceId (=shipmentPackageId), channelId: 1, customerId}` → `202` |
| `uploadInvoiceFile` | POST | `/integration/sellers/{sellerId}/seller-invoice-file` | multipart: `shipmentPackageId` (text) + `file` (pdf/jpeg/png, ≤10 MB) |
| Garanti belgesi | POST | `/integration/sellers/{sellerId}/warranty-documents` | multipart: `shipmentPackageId`, `lineId`, `file` (PDF) |

`invoiceNumber` formatı: **`[3 alfanumerik][4 haneli yıl 2020–2099][9 rakam]`** = 16 karakter (`FRY2024567890123` geçerli, `FRY12345` geçersiz). `invoiceDateTime` > 0 long, 10 haneli (sn) veya 13 haneli (ms). İkisi de **Mikro İhracat ve Trendyol Yurt Dışı Aracılığı için zorunlu**, diğerlerinde opsiyonel.
**409 iki sebepten döner:** (1) `shipmentPackageId`'ye ait fatura zaten beslenmiş, (2) aynı link başka bir pakete beslenmiş.
**Yasal:** fatura linkleri **10 yıl** erişilebilir kalmalıdır. Yurt Dışı Aracılığı modelinde e-Arşiv reddedilir, yalnız e-Fatura.
Trendyol E-Faturam ayrı bir üründür: `https://developers.trendyolefaturam.com`.

#### 4.8.6 Ortak etiket (kargo etiketi)

| Op | Metod | Yol |
|---|---|---|
| `createCommonLabel` | POST | `/integration/sellers/{sellerId}/common-label/{cargoTrackingNumber}` — gövde `{format:"ZPL", boxQuantity?, volumetricHeight?}` |
| `getCommonLabel` | GET | `/integration/sellers/{sellerId}/common-label/{cargoTrackingNumber}` |
| AB ürün etiketi + ortak etiket | GET | `/integration/sellers/{sellerId}/common-labels/{cargoTrackingNumber}/with-product-labels` |

Yalnız **TEX ve Aras**, Trendyol-ödemeli gönderiler. Etiket 100 mm × 130 mm, 8 dpi, kargo etiketi **daima ZPL**. **Fatura oluşmadan kargo etiketi basılamaz.** Stage fixture: sellerId `2738`, orderNumber `1238522676`, ctn `7260000167037306`.

#### 4.8.7 Finans (kapsam dışı ama referans)

| Op | Metod | Yol |
|---|---|---|
| `getSettlements` | GET | `/integration/finance/che/sellers/{sellerId}/settlements` |
| `getOtherFinancials` | GET | `/integration/finance/che/sellers/{sellerId}/otherfinancials` |
| `getCargoInvoiceItems` | GET | `/integration/finance/che/sellers/{sellerId}/cargo-invoice/{invoiceSerialNumber}/items` |
| Tazmin (yalnız TEX) | GET | `/integration/tex/compensation/sellers/{sellerId}/tickets` |

Ortak parametreler: `transactionType` **veya** `transactionTypes` (**zorunlu**; ikisi de gönderilirse `transactionTypes` kazanır), `startDate`/`endDate` (ms, **zorunlu**, **maks 15 günlük aralık**), `page`, `size` (`500` \| `1000`, varsayılan 500), `supplierId` (zorunlu), `paymentDate`, `paymentOrderId`.
Finansal kayıtlar **teslimattan sonra** oluşur; ödeme emirleri **her çarşamba** kesilir. `paymentOrderId` sipariş↔ödeme mutabakatının anahtarıdır. `affiliate` ∈ `TRENDYOLTR`, `TRENDYOLAZJV`.

#### 4.8.8 Coğrafi ve ortak lookup

| Op | Metod | Yol |
|---|---|---|
| `getCountries` | GET | `/integration/member/countries` |
| `getTürkeyCities` | GET | `/integration/member/countries/domestic/TR/cities` |
| `getTürkeyDistricts` | GET | `/integration/member/countries/domestic/TR/cities/{CityCode}/districts` |
| `getTürkeyNeighborhoods` | GET | `/integration/member/countries/domestic/TR/cities/{CityCode}/districts/{DistrictCode}/neighborhoods` |
| `getAzerbaijanCities` | GET | `/integration/member/countries/domestic/AZ/cities` |
| `getAzerbaijanDistricts` | GET | `/integration/member/countries/domestic/AZ/cities/{cityCode}/districts` |
| `getCitiesByCountry` (GULF/CEE) | GET | `/integration/member/countries/{CountryCode}/cities` |
| `getDistrictsByCity` (GULF) | GET | `/integration/member/countries/{CountryCode}/cities/{cityId}/districts` |

Mikro ihracat ülke kodları: `SA` (Suudi Arabistan), `AE`, `QA`, `KW`, `OM`, `BH`, `AZ`, `SK`, `RO`, `CZ`.

#### 4.8.9 Stage yardımcı servisleri

| Op | Metod | Yol (yalnız stage) |
|---|---|---|
| `createTestOrder` | POST | `https://stageapigw.trendyol.com/integration/test/order/orders/core` |
| `updateTestOrderStatus` | PUT | `.../integration/test/order/sellers/{sellerId}/shipment-packages/{packageId}/status` |
| `updateTestClaimToWaitingInAction` | PUT | `.../integration/test/order/sellers/{sellerId}/claims/waiting-in-action` |

`createTestOrder` gövdesi: `customer{customerFirstName, customerLastName}`, `invoiceAddress{addressText, city, district, invoiceFirstName, invoiceLastName, phone, email}`, `shippingAddress`, `lines`, `seller`; opsiyonel `microRegion` ∈ `AZ` \| `GULF`.
`updateTestOrderStatus` enum: `Shipped, AtCollectionPoint, Delivered, UnDelivered, Returned` — **statüler yalnız ileri yönde ilerler**; `Delivered` bir paket `Shipped`'e geri çekilemez.

---

## 5. Enum kataloğu

Her tabloda son kolon **önerilen KobiConnect kanonik değeri**dir. Kural: kanonik enum + **ham `external_*` string kolonu** birlikte saklanır. Bilinmeyen bir değeri asla varsayılana eşlemeyin — ham hâliyle saklayıp satırı incelemeye düşürün.

### 5.1 Sipariş paketi statüleri — sorgu filtresi kümesi

`getShipmentPackages` / `v2/orders` → `status` (tek değer):

| Trendyol | Anlam (resmî) | KobiConnect kanonik |
|---|---|---|
| `Awaiting` | Ödeme onayı bekliyor. **Yalnız stok işlemleri.** İleride bu statüde veri dönmeyebilir | `awaiting_approval` |
| `Created` | Gönderime hazır | `created` |
| `Picking` | **Satıcı iletir.** Toplama/hazırlama başladı | `picking` |
| `Invoiced` | **Satıcı iletir.** Fatura kesildi | `invoiced` |
| `Shipped` | Taşımada | `shipped` |
| `Cancelled` | İptal — **UnSupplied'ı da kapsar** | `cancelled` |
| `Delivered` | Teslim edildi — **sonrasında statü değişmez** | `delivered` |
| `UnDelivered` | Müşteriye ulaştırılamadı | `undelivered` |
| `Returned` | Satıcıya geri döndü — **sonrasında statü değişmez** | `returned` |
| `AtCollectionPoint` | PUDO noktasında, müşteri bekleniyor | `at_collection_point` |
| `UnPacked` | Paket bölünmüş | `unpacked` |
| `UnSupplied` | Tedarik edilemedi | `unsupplied` |

**⚠️ Kaynak içi çelişki (tek sayfa, iki liste):** `getShipmentPackages` kılavuzunun düzyazı satırı *"Kullanılabilen statüler: Created, Picking, Invoiced, Shipped, Cancelled, Delivered, UnDelivered, Returned, **Repack**, UnSupplied"* diyor — burada `Repack` var, `Awaiting`/`AtCollectionPoint`/`UnPacked` yok. Aynı sayfanın parametre tablosu ise `Repack`'i **içermiyor**, `AtCollectionPoint` ve `UnPacked`'i içeriyor. **`Repack` OpenAPI enum'unda, statü açıklama tablosunda ve webhook listesinde HİÇ YOK → bayat dokümantasyon, ⚠️ doğrulanmadı.** Parametre tablosunu otorite kabul edin, ama parser'ınız bilinmeyen bir statüyü düşürmesin.

### 5.2 Sipariş paketi statüleri — yanıt kümesi (filtre kümesinden FARKLI)

OpenAPI `ShipmentPackage.status` enum'u yalnız **8** değer tanımlıyor:
`Created, Picking, Invoiced, Shipped, Cancelled, Delivered, UnDelivered, Returned`

→ `Awaiting`, `AtCollectionPoint`, `UnSupplied`, `UnPacked` **yanıt enum'unda yok** ama filtre enum'unda var. **Tek paylaşılan enum tipini yanıt şemasından türetmeyin**, yoksa bu dört değeri round-trip edemezsiniz.

Ayrıca aynı nesnede **iki statü alanı** vardır:
- `status` — enum tanımlı (8 değer)
- `shipmentPackageStatus` — `string`, **enum tanımsız**; kılavuz örneğinde ikisi de `"Delivered"`. Farkları **belgesiz** ⚠️. **İkisini de ham saklayın**, birini diğerine katlamayın.
- `packageHistories[].status` — `string`, enum tanımsız ⚠️.

### 5.3 Sipariş satırı (order line item) statüleri

`lines[].orderLineItemStatusName` — `string`, **OpenAPI'de enum tanımlı değil** ⚠️. Kılavuz örneğinde `"Delivered"` geçiyor. `orders/stream`'in `packageItemStatuses` filtresi, satır düzeyi statülerin kabul edilen kümesini dolaylı olarak veriyor:

| Trendyol (`packageItemStatuses`) | KobiConnect kanonik |
|---|---|
| `Created` | `created` |
| `Picking` | `picking` |
| `Invoiced` | `invoiced` |
| `Shipped` | `shipped` |
| `Cancelled` | `cancelled` |
| `Delivered` | `delivered` |
| `UnDelivered` | `undelivered` |
| `Returned` | `returned` |
| `UnSupplied` | `unsupplied` |
| `AtCollectionPoint` | `at_collection_point` |
| `UnPacked` | `unpacked` |
| `Awaiting` | `awaiting_approval` |

Satır statüsü paket statüsünden **ayrışabilir** (kısmi iptal / kısmi tedarik edememe) → ayrı kolon.

### 5.4 Claim (iade) statüleri

`claimItemStatus` query parametresi **ve** `claimItems[].claimItemStatus.name` — 8 değer:

| Trendyol | KobiConnect kanonik | Not |
|---|---|---|
| `Created` | `created` | |
| `WaitingInAction` | `waiting_action` | **Satıcı onay/ret yalnız bu statüde yapılabilir.** 48 saatte aksiyon alınmazsa otomatik kabul |
| `WaitingFraudCheck` | `under_review` | |
| `InAnalysis` | `under_review` | |
| `Accepted` | `accepted` | |
| `Rejected` | `rejected` | |
| `Cancelled` | `cancelled` | |
| `Unresolved` | `unresolved` | |

**Claim başlığında statü yoktur** (§4.5.1). Görüntülenecek durum kalemlerden türetilmelidir.

`acceptDetail` (kalem düzeyi): `SUPPLIER`, `DISPUTE`, `SYSTEM` → kanonik `accept_source`: `supplier`, `dispute`, `system`, `unknown`.

### 5.5 Claim sebep kodları (`customerClaimItemReason` / `trendyolClaimItemReason`)

Kalem nesnesi `{name, externalReasonId, code}` şeklindedir (örn. `{"name":"Beden uymama","externalReasonId":23,"code":"UNFIT"}`). Belgelenmiş sebep listesi (TY = Trendyol kaynaklı):

| Kod | Sebep | Kod | Sebep |
|---:|---|---:|---|
| 51 | Sebep Yok (TY) | 1651 | Kalitesini beğenmedim |
| 101 | Depo Kayıp (TY) | 1701 | Kargo Teslimatı Gecikmesi (TY) |
| 151 | Çapraz Hatalı (TY) | 2000 | Cezalı Onay (TY) |
| 201 | Müşteri İade Kayıp (TY) | 2001 | Cezasız Onay (TY) |
| 251 | Modelini beğenmedim | 2002 | Teknik Servis Desteği Gerekiyor (TY) |
| 301 | Kusurlu ürün gönderildi | 2003 | Kullanılmış Ürün Sebepli Red (TY) |
| 351 | Yanlış ürün gönderildi | 2004 | Hijyenik Sebepli Red (TY) |
| 401 | Vazgeçtim | 2005 | Analiz sonucu üretimden kaynaklı sorun yok (TY) |
| 451 | Diğer | 2006 | Analiz Değişim (TY) |
| 501 | Bedeni küçük geldi | 2007 | Analiz Tamirat (TY) |
| 551 | Bedeni büyük geldi | 2008 | Üründe adet/aksesuar eksiği (TY) |
| 651 | Ürün belirtilen özelliklere sahip değil | 2009 | Firmaya ait olmayan ürün (TY) |
| 701 | Yanlış sipariş verdim | 2010 | Tekrar Sevk (TY) |
| 751 | Diğer–Fraud (TY) | 2011 | Satıcı Tarafından Aksiyona Çekilen (TY) |
| | | 2012 | İade Süresi Geçmiş (TY) |
| | | **2013** | **Ürün Müşteride (TY) — `dontShipBack: true`, paket geri gönderilemez** |
| | | 2014 | Analize Gönder (TY) |
| | | 2015 | Teslim edilemeyen gönderi (TY) |
| | | 2017 | Tazmin (TY) |
| | | 2030 | Daha iyi bir fiyat mevcut |
| | | 2042 | Beğenmedim |
| | | 2043 | Ürünümün parçası/aksesuarı eksik gönderildi |

**Ret sebepleri (`claimIssueReasonId`)** `getClaimIssueReasons` ile dinamik olarak çekilir — statik liste yayımlanmamıştır. **`1651` reason'ı claim `WaitingInAction`'a girdikten sonraki ilk 24 saatte kullanılamaz.**

Kanonik eşleme: `code`/`externalReasonId` çiftini `return_reasons(marketplace, code, external_reason_id, localized_name)` lookup tablosunda tutun. Sebepler pazaryerine özgü sözlüklerdir; kanonik enum'a katlamayın.

### 5.6 Tedarik edememe / iptal sebep kodları (`reasonId`)

`cancelOrderPackageItem` (unsupplied) gövdesindeki `reasonId`. **Endpoint yok, statik tablo:**

| `reasonId` | Sebep | KobiConnect kanonik |
|---:|---|---|
| 500 | Stok tükendi | `out_of_stock` |
| 501 | Kusurlu/Defolu/Bozuk Ürün | `defective` |
| 502 | Hatalı Fiyat | `pricing_error` |
| 504 | Entegrasyon Hatası | `integration_error` |
| 505 | Toplu Alım | `bulk_purchase` |
| 506 | Mücbir Sebep | `force_majeure` |

`503` enum'da **yoktur**. Sipariş satırındaki `cancelReasonCode` / `cancelReason` / `cancelledBy` alanları ayrı bir sözlüktür ve **belgesiz** ⚠️.

### 5.7 Soru statüleri

| Trendyol | Anlam | Cevaplanabilir? | KobiConnect kanonik |
|---|---|---|---|
| `WAITING_FOR_ANSWER` | Cevap bekliyor | **Evet — yalnız bu** | `awaiting_answer` |
| `ANSWERED` | Cevaplanmış ve yayınlanmış | Hayır | `answered` |
| `REPORTED` | Satıcı raporlamış, admin değerlendiriyor (**yalnız panelden yapılır**) | Hayır | `reported` |
| `REJECTED` | Reddedilmiş ve kapatılmış | Hayır | `rejected` |
| `UNANSWERED` | 3 iş günü içinde cevaplanmama veya yasaklı kelime limiti aşımı sebebiyle kapatılmış | Hayır (terminal) | `expired` |

**⚠️ ÇELİŞKİ:** OpenAPI enum'u yalnız ilk **dört** değeri listeliyor; `UNANSWERED` yalnız kılavuz sayfasında var ve `status` filtresi olarak kabul edildiği yazıyor. **Generated client validator'ınızın `UNANSWERED`'ı reddetmemesini sağlayın.**

`Answer.status` alanı OpenAPI'de var ama **değerleri hiçbir yerde belgelenmemiş** ⚠️.

### 5.8 Webhook abonelik statüleri (13 değer)

`subscribedStatuses` — **paket statülerinin UPPER_SNAKE_CASE varyantı**, sipariş filtresi enum'uyla aynı değil:

| Webhook | Sipariş filtresi karşılığı | KobiConnect kanonik |
|---|---|---|
| `CREATED` | `Created` | `created` |
| `PICKING` | `Picking` | `picking` |
| `INVOICED` | `Invoiced` | `invoiced` |
| `SHIPPED` | `Shipped` | `shipped` |
| `CANCELLED` | `Cancelled` | `cancelled` |
| `DELIVERED` | `Delivered` | `delivered` |
| `UNDELIVERED` | `UnDelivered` | `undelivered` |
| `RETURNED` | `Returned` | `returned` |
| `UNSUPPLIED` | `UnSupplied` | `unsupplied` |
| `AWAITING` | `Awaiting` | `awaiting_approval` |
| `UNPACKED` | `UnPacked` | `unpacked` |
| `AT_COLLECTION_POINT` | `AtCollectionPoint` | `at_collection_point` |
| **`VERIFIED`** | **karşılığı YOK** | `verified` |

**`VERIFIED` yalnızca webhook aboneliğinde vardır**, paket sorgu filtresi olarak kullanılamaz ve anlamı **belgesiz** ⚠️.
Boş `subscribedStatuses` = tümüne abone **ve gelecekte eklenecek statülere otomatik abone** → handler'ınız bilinmeyen statü değerini tolere etmek **zorundadır**.

Webhook kaydının kendi statüsü: `ACTIVE` | `PASSIVE`.
Webhook kimlik doğrulama tipi: `BASIC_AUTHENTICATION` | `API_KEY`.

### 5.9 Batch enum'ları

| Enum | Değerler |
|---|---|
| Batch statüsü | `IN_PROGRESS`, `COMPLETED` |
| Kalem statüsü | `SUCCESS`, `FAILED`, **`IN_PROGRESS`** (bazı batch tiplerinde; OpenAPI yalnız ilk ikisini tanımlıyor ⚠️) |
| `sourceType` | `API`, `WEB` (`WEB` = satıcı panelinden yapılan işlem) |
| `batchRequestType` | **OpenAPI (5):** `ProductV2OnBoarding`, `ProductV2Update`, `ProductInventoryUpdate`, `ProductArchiveUpdate`, `ProductDeletion`<br>**TR kılavuz örneklerinde ek olarak (4):** `ApprovedProductContentUpdate`, `ApprovedProductVariantUpdate`, `ProductDeliveryOptionUpdate`, `ProductUnlockUpdate` |

**OpenAPI enum'u eksiktir — bilinmeyen `batchRequestType` değerinde hard-fail etmeyin.**

| Operasyon | `batchRequestType` |
|---|---|
| `createProducts` | `ProductV2OnBoarding` |
| `unapproved-bulk-update` | `ProductV2Update` |
| `content-bulk-update` | `ApprovedProductContentUpdate` |
| `variant-bulk-update` | `ApprovedProductVariantUpdate` |
| `delivery-info-bulk-update` | `ProductDeliveryOptionUpdate` |
| `updatePriceAndInventory` | `ProductInventoryUpdate` |
| `archiveProducts` | `ProductArchiveUpdate` |
| `deleteProducts` | `ProductDeletion` |
| `unlockProducts` | `ProductUnlockUpdate` |

### 5.10 Ürün / listing durum enum'ları

| Enum | Değerler | KobiConnect kanonik |
|---|---|---|
| `filterUnapprovedProducts.status` | `rejected`, `pendingApproval` | `rejected`, `pending_approval` |
| `filterApprovedProducts.status` | `archived`, `blacklisted`, `locked`, `onSale` **(+ `notOnSale`? — §4.3.3 çelişkisi ⚠️)** | `archived`, `blacklisted`, `locked`, `on_sale`, `not_on_sale` |
| `inventory-and-price.status` | `archived`, `blacklisted`, `locked`, `onSale`, `notOnSale` | aynı |
| `dateQueryType` (onaysız) | `CREATED_DATE`, `LAST_MODIFIED_DATE` | – |
| `dateQueryType` (onaylı) | `VARIANT_CREATED_DATE`, `VARIANT_MODIFIED_DATE`, `CONTENT_MODIFIED_DATE` | – |
| `locationBasedDelivery` | `ENABLED`, `DISABLED`, `null` | boolean + nullable |
| `fastDeliveryType` (ürün tarafı) | `SAME_DAY_SHIPPING`, `FAST_DELIVERY` | `same_day`, `next_day` |
| `fastDeliveryType` (sipariş tarafı) | `TodayDelivery`, `SameDayShipping`, `FastDelivery` | **Ürün tarafından FARKLI yazım** — ayrı mapper |
| `deliveryOptionType` (onaylı filtre yanıtı) | `SAME_DAY_SHIPPING`, `FAST_DELIVERY` | – |
| `channels` | `CORE`, `LUXE` | – |
| `deliveryAddressType` | `Shipment`, `CollectionPoint` | – |
| `createdBy` (paket) | `order-creation`, `cancel`, `split`, `transfer` | `direct`, `partial_cancel`, `split`, `transfer` |
| `addressType` (tedarikçi adresi) | `Shipment`, `Invoice`, `Returning` | `shipment`, `invoice`, `returning` |
| `update-audits` → `type` | `TITLE`, `DESCRIPTION`, `MEDIA`, `ATTRIBUTE` | – |
| `update-audits` → `status` | `SUCCESS`, `FAIL`, `RUNNING` | `approved`, `rejected`, `pending` |
| `invoiceStatus` | `NotInvoiced`, `Received`, `Rejected`, `Invoiced` | `not_invoiced`, `received`, `rejected`, `invoiced` |
| `videoContentType` | `PRODUCT_PROMOTION` (varsayılan), `ASSEMBLY_AND_INSTALLATION`, `PACKAGING`, `STORE_PROMOTION`, `ADVERTISEMENT`, `PRODUCT_USAGE_AND_EXPERIENCE` | kapsam dışı |

### 5.11 `invoiceRejectedReasonKeys`

Yalnız `is4P: true` (Trendyol Yurt Dışı Aracılığı) siparişlerde ve `invoiceStatus = Rejected` iken döner:

| Anahtar | Anlam |
|---|---|
| `INVOICE_LINE_MISMATCH` | Her ürün çeşidi için miktar, birim fiyat ve KDV uyuşan bir kalem olmalı |
| `INVOICE_TOTAL_MISMATCH` | Dip toplam sipariş toplamıyla eşleşmeli |
| `INVOICE_LINE_NUMBER_MISMATCH` | Kalem sayısı ürün çeşidi sayısıyla eşleşmeli |
| `INVOICE_TYPE_MISMATCH` | Fatura tipi "satış" olmalı |
| `SENDER_VKN_MISMATCH` | Gönderen VKN sistemdekiyle aynı olmalı |
| `RECEIPENT_VKN_MISMATCH` | Alıcı VKN Trendyol VKN'si olmalı (yazım hatası Trendyol'a ait, birebir korunmuştur) |
| `INVOICE_NUMBER_MISMATCH` | Fatura numarası beslenen numarayla aynı olmalı |
| `INVOICE_DATE_MISMATCH` | Fatura tarihi sipariş tarihinden sonra olmalı |
| `INVOICE_SCENARIO_MISMATCH` | Senaryo temel veya ticari olmalı |
| `INVOICE_NOT_FOUND_IN_MAILBOX` | Fatura Trendyol gelen kutusunda yok |
| `INVOICE_NUMBER_ALREADY_EXISTS` | Aynı `invoiceNumber` başka bir pakete gönderilmiş |

### 5.12 Settlement işlem tipleri (kapsam dışı, referans)

**`getSettlements` `transactionType` (25):** `Sale, Return, Discount, DiscountCancel, Coupon, CouponCancel, ProvisionPositive, ProvisionNegative, ManualRefund, ManualRefundCancel, TyDiscount, TyDiscountCancel, TyCoupon, TyCouponCancel, SellerRevenuePositive, SellerRevenueNegative, CommissionPositive, CommissionNegative, SellerRevenuePositiveCancel, SellerRevenueNegativeCancel, CommissionPositiveCancel, CommissionNegativeCancel, DeliveryFee, DeliveryFeeCancel, PayByLink`
Eşleşme kuralı: `SellerRevenuePositive` ↔ `CommissionNegative`, `SellerRevenueNegative` ↔ `CommissionPositive` (ve `*Cancel` muadilleri).

**`getOtherFinancials` `transactionType` (9):** `Stoppage, CashAdvance, WireTransfer, IncomingTransfer, ReturnInvoice, CommissionAgreementInvoice, PaymentOrder, DeductionInvoices, FinancialItem` · ayrıca `transactionSubType=PlatformServiceFee` (`transactionType=DeductionInvoices` gerektirir).

---

## 6. Asenkron akış — `batchRequestId` yaşam döngüsü

### 6.1 Hangi endpoint'ler `batchRequestId` döner

Resmî kılavuz (`getBatchRequestResult` sayfası) altı operasyon sayıyor: **`createProducts`, `updatePriceAndInventory`, `updateProduct`, `archiveProduct`, `productDelete`, `unlockProduct`**. Buna ek olarak dört V2 güncelleme endpoint'i de batch döner (kendi sayfalarında "TOPLU İŞLEM KONTROLÜ" uyarısıyla): `unapproved-bulk-update`, `content-bulk-update`, `variant-bulk-update`, `delivery-info-bulk-update`.

Yanıt gövdesi **her zaman** yalnızca:
```json
{ "batchRequestId": "fa75dfd5-6ce6-4730-a09e-97563500000-1529854840" }
```
Gözlemlenen biçim: `{uuid}-{unix-epoch-saniye}`. **Opak string olarak saklayın, parse etmeyin.**

### 6.2 Sorgulama endpoint'i

```
GET https://apigw.trendyol.com/integration/product/sellers/{sellerId}/products/batch-requests/{batchRequestId}
GET https://stageapigw.trendyol.com/integration/product/sellers/{sellerId}/products/batch-requests/{batchRequestId}
```

> **`updatePriceAndInventory` `/integration/inventory/…`'a POST edilir ama sonucu `/integration/product/…` altından sorgulanır.** En sık yapılan yol hatası.

Query parametresi yok, sayfalama yok — 1000 kaleme kadar tüm batch tek yanıtta döner (büyük payload).

### 6.3 Yanıt şekli (yayımlanmış OpenAPI, birebir)

```json
{
  "batchRequestId": "string",
  "items": [
    { "requestItem": {}, "status": "SUCCESS|FAILED", "failureReasons": ["string"] }
  ],
  "status": "COMPLETED|IN_PROGRESS",
  "creationDate": 0,
  "lastModification": 0,
  "sourceType": "API|WEB",
  "itemCount": 0,
  "failedItemCount": 0,
  "batchRequestType": "ProductV2OnBoarding|ProductV2Update|ProductInventoryUpdate|ProductArchiveUpdate|ProductDeletion"
}
```

Bazı batch tiplerinde ek alanlar: `items[].batchRequestLogId` (örn. `ProductArchiveUpdate`), üst düzey `"notes": null`.

`creationDate` / `lastModification` **epoch milisaniye**dir. `requestItem`, gönderdiğiniz kalemin **normalize edilmiş** echo'sudur — Trendyol'un gerçekte ne parse ettiğini gösterir (örn. boşlukları temizlenmiş barkod). **`requestItem.barcode` değerini kanonik barkod olarak kabul edin.**

`ProductInventoryUpdate` echo'sunda ek bir alan var: `requestItem.updateRequestDate` — **ISO-8601 string** (`"2025-05-08T11:37:07.950+00:00"`), epoch ms değil. Aynı yanıttaki `creationDate`/`lastModification` ise epoch ms. Tek yanıtta iki tarih formatı.

### 6.4 Naif implementasyonları kıran iki resmî tuzak

**1) Stok/fiyat batch'lerinde üst düzey `status` HİÇ DÖNMEZ.**
> "Stok Fiyat güncelleme işlemleri sonrasında sorguladığınız batchId için **item bazlı status alanlarını** kontrol etmeniz gerekmektedir. **Batch status alanı tarafınıza dönmeyecektir.**"

`while (status != "COMPLETED")` döngüsü `ProductInventoryUpdate` batch'lerinde **sonsuza kadar döner**. Bu batch tipinde `items[].status` varlığı üzerinden sonlanın.

**2) `COMPLETED` ≠ hepsi başarılı.**
`itemCount` / `failedItemCount` batch düzeyindedir; **her kalem kendi `status`'unu taşır**. `status: "COMPLETED"` **ve** `failedItemCount > 0` normal bir kısmi başarıdır. Her zaman `items[]`'ı gezip `failureReasons`'ı toplayın. **Asla `failedItemCount > 0` diye tüm push'u başarısız işaretlemeyin.**

### 6.5 Saklama penceresi — 4 saat

> "Ürün Aktarma ve Ürün Güncelleme servisleri, stok ve fiyat güncellemelerinde sizlere dönen batch requesti **4 saat sonrasına kadar** görüntüleyebilirsiniz."

**Süre dolduktan sonraki davranış belgesiz** ⚠️ (404 mü, boş gövde mi, `items: []` ile 200 mü). **Doğrulama:** stage'de bir batch atıp 4 saat sonra sorgulayın.

**Pencere kapandıysa ne yapılır:**
1. Batch sonucunu **kaybedilmiş** kabul edin (`state = EXPIRED`), asla "başarılı" saymayın.
2. **Zemin doğrusuna (ground truth) gidin:** ürün oluşturma için `getProductBase` (barkod başına) veya `filterUnapprovedProducts` (`rejectReasonDetails` ile) — stok/fiyat için `filterApprovedProductsInventoryAndPrice` (`barcodes`, 50'lik gruplar) → gerçek durumu arzu edilen durumla karşılaştırın.
3. Fark varsa, gövdeyi **yeniden hesaplayarak** (aynı gövdeyi tekrar oynatmadan, §3/K3) yeniden gönderin.
4. `expires_at = submitted_at + 4h` kolonu tutun ve hâlâ `IN_PROGRESS` olan bir batch bu süreye yaklaşırken **alarm üretin** — bu, kuyruk gecikmesinin erken uyarısıdır.

### 6.6 Polling temposu

**Belgesiz** ⚠️. Trendyol'un kendi ajan eklentisi tek yarı-resmî referanstır: `maxAttempts = 10, intervalSeconds = 3` ("Poll every 3-5 seconds. Max 10 attempts.") → Trendyol'un kendi örnek implementasyonu tipik tamamlanmayı **≤30–50 saniye** varsayıyor.

**Önerilen (çıkarım):**
- Backoff: 3 s → 5 s → 10 s → 30 s → 60 s.
- ~5 dakika sonra **polling'i** bırakın ama **başarısız saymayın**; 4 saatlik pencere kapanmadan bir kez daha kontrol edin.
- Nihai doğruluk için filtre servisleriyle mutabakat yapın.
- **Bütçeleyin:** `getBatchRequestResult` Product Integration **Read** grubundadır (§7) ve ürün okumalarıyla aynı kotayı yer. Batch başına 3 saniyede bir sorgulayan naif tasarım ölçekte Read kotasını bitirir.

### 6.7 İkinci asenkron aşama (yalnız onaylı içerik)

```
createProducts / *-bulk-update  ──▶ 200 {batchRequestId}
                                        │
                                        ▼
                         getBatchRequestResult (≤4 saat)
                                        │
              ┌─────────────────────────┴─────────────────────────┐
              │ items[].status = FAILED                           │ items[].status = SUCCESS
              ▼                                                   ▼
    failureReasons[] sakla, kalemi düzelt          batchRequestType == ApprovedProductContentUpdate ?
                                                    │                         │
                                                   hayır                     evet
                                                    │                         │
                                                    ▼                         ▼
                                                 bitti          getUpdateAudits({contentId})
                                                                              │
                                                                RUNNING / SUCCESS / FAIL + rejectReasons[]
```

`ApprovedProductContentUpdate` için `SUCCESS`, yalnızca isteğin **kalite kontrole girdiğini** söyler. Gerçek verdiğe `getUpdateAudits` üzerinden, alan bazında (`TITLE|DESCRIPTION|MEDIA|ATTRIBUTE`) ulaşılır. Bu yüzden `contentId`'yi **listing satırında kalıcı** tutmak zorundasınız — `getUpdateAudits` `batchRequestId` ile sorgulanamaz (ancak yanıtta `batchRequestId` döner, böylece geriye doğru ilişkilendirebilirsiniz).

---

## 7. Rate limit tablosu

### 7.1 Global sert tavan (her şeyin üstünde)

> "Trendyol Partner API'ye yapacağınız tüm isteklerde **aynı endpointe 10 saniye içerisinde maksimum 50 request** atabilirsiniz. 51. requesti denediğiniz an sizlere **429 status code and it say too.many.requests** hatası dönecektir." — *2. Authorization*

**= 5 istek/saniye/endpoint.** Bu, aşağıdaki dakikalık kotalardan bağımsız ve genelde daha kısıtlayıcıdır. Örneğin Q&A okuma kotası 1000 req/dk olsa da bu tavan efektif olarak 300 req/dk'ya indirir.

### 7.2 Ürün servisleri — **şu anki** model (14 Eylül 2026'ya kadar, endpoint başına)

| Servis | Limit |
|---|---|
| Ürün Aktarma (`createProducts`) | 1000 req/dk |
| Ürün Güncelleme | 1000 req/dk |
| **Stok ve Fiyat Güncelleme** | **LİMİTSİZ** |
| Toplu İşlem Kontrolü (`getBatchRequestResult`) | 1000 req/dk |
| Ürün Filtreleme | 2000 req/dk |
| Ürün Filtreleme Onaylı V2 Stok ve Fiyat | 2000 req/dk |
| Ürün Silme | 100 req/dk |
| **İade ve Sevkiyat Adres Bilgileri** | **1 req/SAAT** |
| TY Marka Listesi / Marka İsme Göre Filtreleme | 50 req/dk (her biri) |
| TY Kategori Listesi / Kategori-Özellik Listesi | 50 req/dk (her biri) |
| Ürün Buybox Kontrol | 1000 req/dk |
| Ürün Bilgileri Güncelleme Sonucu Kontrol (`getUpdateAudits`) | 100 req/dk |

### 7.3 Ürün servisleri — **14 Eylül 2026'dan itibaren** (servis grubu başına, listeleme kademesine göre)

Limitler artık **endpoint başına değil, servis grubu başına** uygulanır ve satıcının **ürün listeleme limiti** kademesine bağlıdır.

| Grup | 50k | 75k | 150k | 500k | Limitsiz |
|---|---:|---:|---:|---:|---:|
| **Product Integration Read** | 1000/dk | 1250/dk | 1500/dk | 1750/dk | 2000/dk |
| **Product Integration Write** | 200/dk | 300/dk | 400/dk | 500/dk | 600/dk |
| **Inventory & Price Write** | 350/dk | 500/dk | 1000/dk | 1500/dk | 2000/dk |

**Grup üyelikleri:**
- **Read:** Ürün Filtreleme · Toplu İşlem Kontrolü (`getBatchRequestResult`) · İade ve Sevkiyat Adres Bilgileri · Marka Listesi · Kategori Listesi · Kategori Özellik Listesi · Kategori Özellik Değerleri Listesi · Ürün Bilgileri Güncelleme Sonucu Kontrol (`getUpdateAudits`)
- **Write:** Ürün Aktarma · Ürün Güncelleme · Buybox Kontrol · Marka Yaratma · Ürün Arşivleme · Ürün Kilit Kaldırma · Ürün Silme
- **Inventory & Price Write:** yalnız `updatePriceAndInventory`

Trendyol'un kendi örneği: 50k kademesinde `50 create + 100 update + 50 delete = 200` bir dakikanın Write bütçesini **tüketir**.

**Bu tablodaki tek en büyük kırıcı değişiklik:** stok/fiyat **LİMİTSİZ → 350–2000 req/dk**. Throttle'sız stok pusher'ı olan herkes 14 Eylül 2026'da 429 almaya başlar.
**İkinci sonuç:** `getBatchRequestResult` polling'i ürün okumalarıyla **aynı Read bütçesini** paylaşır — polling'e ayrı bütçe ayırmadan ölçeklenemezsiniz.

### 7.4 Sipariş servisleri (kademeli, hâlihazırda yürürlükte)

| Servis | 50k | 75k | 150k | 500k | Limitsiz |
|---|---:|---:|---:|---:|---:|
| **Sipariş Paketlerini Çekme (`getShipmentPackages`)** | **30/dk** | 40/dk | 50/dk | 100/dk | 100/dk |
| Kargo Takip Kodu Bildirme ⚠️ | 300 | 300 | 500 | LİMİTSİZ | LİMİTSİZ |
| Paket Statü Bildirimi (`updatePackageStatus`) | 300 | 300 | 500 | LİMİTSİZ | LİMİTSİZ |
| Tedarik Edememe Bildirimi | 100 | 100 | 200 | LİMİTSİZ | LİMİTSİZ |
| Split (`/split`, `/multi-split`, `/quantity-split`, `/split-packages`) | 100 | 100 | 200 | LİMİTSİZ | LİMİTSİZ |
| Desi ve Koli Bilgisi (`updateBoxInfo`) | 100 | 100 | 200 | LİMİTSİZ | LİMİTSİZ |
| Alternatif Teslimat | 300 | 300 | 500 | LİMİTSİZ | LİMİTSİZ |
| Alt. Teslimat – Teslim edildi / İade | 1000 | 1000 | 1500 | LİMİTSİZ | LİMİTSİZ |
| Yetkili Servis İle Gönderim (`deliveredByService`) | 100 | 100 | 200 | LİMİTSİZ | LİMİTSİZ |
| Paket Kargo Firması Değiştirme | 100 | 100 | 200 | LİMİTSİZ | LİMİTSİZ |
| Depo Bilgisi Güncelleme | 300 | 300 | 500 | LİMİTSİZ | LİMİTSİZ |
| Ek Tedarik Süresi Tanımlama | 100 | 100 | 200 | LİMİTSİZ | LİMİTSİZ |

⚠️ "Kargo Takip Kodu Bildirme" limit tablosunda var ama karşılığı olan endpoint sayfası **yok** (§1.2).

**En küçük kademede sipariş polling'i 30 req/dk'dır.** `size=200` ile teorik 6000 paket/dk demek, ama **paralel statü filtreleriyle polling yapamazsınız**. Webhook + `orders/stream` mimarisini zorunlu kılan sayı budur. `getShipmentPackages` sayfa gövdesindeki "1 dakikada en fazla 1000 istek" ifadesi **bayattır** — kademeli tablo otoritedir (8 Haziran 2026 duyurusu).

### 7.5 Diğer aileler

| Aile | Servis | Limit |
|---|---|---:|
| Ortak Etiket | Barkod Talebi / Barkodun Alınması | 100/dk (her biri) |
| İade | İadesi Oluşturulan Siparişleri Çekme (`getClaims`) | 1000/dk |
| İade | **İade Siparişleri Onaylama** (`approveClaimLineItems`) | **5/dk** |
| İade | **Ret Talebi Oluşturma** (`createClaimIssue`) | **5/dk** |
| İade | İade Audit Bilgilerini Çekme | 1000/dk |
| Finans | Cari Hesap Ekstresi (settlements / otherfinancials) | 100/dk |
| Finans | Kargo Faturası Detayları | 100/dk |
| Soru & Cevap | Müşteri Sorularını Çekme | 1000/dk (27 Mart 2026'dan beri) |
| Soru & Cevap | Müşteri Sorularını Cevaplama | 500/dk (27 Mart 2026'dan beri) |
| Video | Video oluşturma | 200/dk |
| Stage | IP whitelist create | 7 istek/gün, maks 7 IP |

**İade onayı 5/dk** iade yoğun satıcılar için gerçek bir operasyonel uçurumdur — mutlaka kuyruklayın.

### 7.6 Limit aşımında ne döner

- **HTTP `429`**, gövde metni `too.many.requests`.
- **Rate-limit yanıt başlıkları belgesiz** ⚠️ **doğrulanmadı.** İndirilmiş tüm dokümanlarda `Retry-After`, `X-RateLimit-*`, `backoff`, `exponential` için **sıfır eşleşme** var. **Doğrulama:** stage'de kasıtlı olarak 429 tetikleyip yanıt başlıklarını dökün.
- Sipariş endpoint'lerinin OpenAPI tanımı 429 için `{"error": "string", "message": "string"}` şeması bildiriyor — ürün endpoint'lerinin `{"errors":[…]}` zarfından **farklı**. İki şekli de tolere edin.

### 7.7 Önerilen istemci bütçesi (çıkarım — ⚠️ Trendyol tarafından belgelenmemiştir)

1. **Tepki verme, önle.** Kova hiyerarşisi:
   - Katman 1: `(sellerId, endpoint)` → **50 istek / 10 saniye** sert koruma. Her şeyin altında.
   - Katman 2: `(sellerId, serviceGroup)` → dakikalık kota, satıcının listeleme kademesine göre boyutlanır (`marketplace_accounts.listing_limit_tier`).
   - Katman 3: `(sellerId, resource)` → polling'e ayrılmış alt bütçe (örn. Read kotasının %30'u `getBatchRequestResult`'a).
2. **Kova anahtarı `sellerId` içermeli.** Limitler satıcı bazındadır; çok kiracılı kurulumda bir tenant diğerlerini aç bırakmamalı.
3. Hedef kullanım: yayımlanan limitin **%70'i**. Trendyol limitleri duyuru ile daraltıyor (bkz. §12) ve pencere hizalaması sizin saatinizle aynı olmayabilir.
4. `getSuppliersAddresses`'i **kalıcı** cache'leyin (1 req/saat). Marka 1 gün, kategori/attribute 7 gün TTL.
5. Sipariş senkronunu **tek bir `orders/stream` akışı** üzerinden yürütün (min 5 sn aralık, Trendyol'un önerisi), paralel statü sorguları açmayın.

---

## 8. Hata yönetimi

### 8.1 Hata zarfı

**Standart (yayımlanmış OpenAPI `ErrorResponse`):**
```json
{ "errors": [ { "key": "string", "message": "string", "errorCode": "string" } ] }
```
Gerçek örnekler (arşivleme / kilit kaldırma sayfalarından):
```json
{"errors":[{"key":"invalid.barcode","message":"Barkod formatı geçersiz","errorCode":"400"}]}
{"errors":[{"key":"product.not.found","message":"Ürün sistemde bulunamadı","errorCode":"404"}]}
```

> **`errorCode`, HTTP statüsünün string hâlidir**, ayrı bir uygulama kodu değil. Makine tarafından okunabilir ayırt edici **`key`**'dir (nokta ile ayrılmış).

**İki istisna — parser'ınız üçünü de tolere etmeli:**
1. `401` → `{"exception": "ClientApiAuthenticationException"}`
2. Sipariş servisleri (`v2/orders`, `orders/stream`) → `{"error": "string", "message": "string"}`

### 8.2 HTTP statü kodları (resmî tablo) + sınıflandırma

Sınıflandırma kolonu **⚠️ çıkarımdır**; dokümanda yalnız 500/502/504 için açık "tekrar deneyin" talimatı vardır.

| Kod | Trendyol'un açıklaması | Sınıf | Doğru davranış |
|---:|---|---|---|
| 200 / 201 / 202 / 204 | İstek işlendi / kabul edildi | – | Batch ise **başarı değil, kabul** |
| **400** | Geçersiz istek, zorunlu alan eksik veya Trendyol'un kabul etmediği değer | **Kalıcı** | Retry etmeyin. `errors[].key` ile sınıflandırıp kalemi karantinaya alın |
| **401** | Kimlik bilgileri yok/yanlış | **Kalıcı** | Alarm üretin. Rotasyon sırasında kısa süreli olabilir → 1–2 kez, uzun aralıkla dene, sonra hesabı `needs_reauth` işaretle |
| **403** | Sunucu reddediyor — "genellikle hatalı endpoint veya servis parametresi"; **ayrıca `User-Agent` eksikliği** | **Kalıcı** | Önce `User-Agent`'ı kontrol edin. En sık sessiz 403 sebebi budur |
| **404** | Kaynak yok / yanlış URL | **Kalıcı** | `deleteWebhook` bağlamında "zaten yok = başarı" |
| **405** | Yanlış HTTP metodu | **Kalıcı** | Kod hatası |
| **409** | Çakışma (örn. fatura zaten beslenmiş, link başka pakete beslenmiş) | **Kalıcı (iş kuralı)** | Retry etmeyin; insan/iş akışı çözümü gerekir |
| **414 / 415** | URI çok uzun / desteklenmeyen medya tipi | **Kalıcı** | `barcodes`/`shipmentPackageIds` listelerini kısaltın; Content-Type'ı düzeltin |
| **426** | Order V1 zorunlu-migrasyon uyarısı (günde 3× 10'ar dakika) | **Geçici, planlı** | V2'ye geçin. Geçişe kadar backoff ile retry |
| **429** | Limit aşıldı (`too.many.requests`) | **Retry edilebilir** | Jitter'lı üstel backoff; **yalnız ilgili grubu** kısın |
| **500 / 502** | "Trendyol sisteminde bir hata oluştu. **İsteğinizi yeniden deneyin.**" | **Retry edilebilir** | 3–5 kez, jitter ile |
| **503** | Servis kullanılamıyor. **Stage'de bu, IP'nizin allow-list'te olmadığı anlamına gelir** | Prod: retry edilebilir · **Stage: kalıcı yapılandırma hatası** | Ortama göre ayrı işleyin |
| **504** | Zaman aşımı — "Yaptığınız istek çok büyükse, bunu **birden fazla küçük isteğe bölmeyi** deneyin" | **Retry edilebilir (küçültülmüş batch ile)** | Batch boyutunu yarıya indirip tekrar deneyin |

### 8.3 Kalem düzeyi hatalar — `failureReasons`

`items[].failureReasons` `array<string>`'tir: **serbest metin, karışık Türkçe/İngilizce, sürümler arası kararlı değil.** Trendyol bu dizgilerin kataloğunu **hiç yayımlamıyor** ⚠️.

**Doğru davranış:**
- Ham hâliyle saklayın (`failure_reasons` jsonb) ve insana gösterin.
- **Substring eşleşmesi üzerine kontrol akışı kurmayın.** Sınıflandırma gerekiyorsa kuralları **konfigürasyonda** tutun, kodda değil; eşleşmeyen sebepleri loglayıp gözden geçirin.
- **4 saatlik pencere kapanmadan mutlaka persist edin** — kaybolduktan sonra geri gelmez.
- `failedItemCount > 0` + `status: COMPLETED` = kısmi başarı, alarm konusu ama batch başarısızlığı değil.
- **Asla tüm batch'i tekrar göndermeyin.** Trendyol'un kendi kod deseni: *"Failed item handling: Collect failed items separately. **Never retry entire batch.**"*

### 8.4 Doğrulama kodu kataloğu (ön kontrol için)

Aşağıdaki kodlar **Trendyol'un kendi ajan eklentisinin** (`trendyol-integration-developer-tool/skills/.../references/validation.md`) doğrulayıcısından gelir. Bunlar **istemci tarafı** kodlardır; sunucu bunları döndürmez ama her biri bir 400 veya kalem düzeyi `FAILED` karşılığıdır. **Yerel ön doğrulama olarak uygulamak bu entegrasyondaki en ucuz kazançtır.**

**Hatalar (istek gönderilmemeli):**

| Kod | Ne yakalar | KobiConnect ön kontrolü |
|---|---|---|
| `MISSING_REQUIRED_FIELD` | Zorunlu alan yok | Şema doğrulaması |
| `TYPE_MISMATCH` | Yanlış tip | DTO cast |
| `ENUM_MISMATCH` | Enum dışı değer | Enum whitelist |
| `INVALID_VAT_RATE` | `vatRate ∉ {0,1,10,20}` (TR) | Sabit liste |
| `MAX_LENGTH_EXCEEDED` | `title>100`, `barcode>40`, `stockCode>100`, `description>30000`, `lotNumber>100`, renk>50 | `mb_strlen` |
| `MAX_ITEMS_EXCEEDED` | `items > 1000`, `images > 8` | Chunk'la |
| `MAXIMUM_EXCEEDED` | Sayısal tavan aşımı | |
| `INVALID_PRICE_RELATION` | `listPrice < salePrice` — panelde "TSF PSF'den Büyük olamaz" mesajıyla görülür ⚠️ (mesaj metni üçüncü parti kaynaktan) | `assert listPrice >= salePrice` |
| `INVALID_DELIVERY_DURATION` | `deliveryDuration` / `fastDeliveryType` uyumsuz | §12 |
| `DUPLICATE_BARCODES` | Aynı batch'te tekrarlı barkod → **kalem düzeyinde başarısız, HTTP hatası değil** (sessiz kısmi başarı) | Batch kurarken dedupe |
| `DUPLICATE_CONTENT_IDS` | `content-bulk-update`'te tekrarlı `contentId` | Aynı |
| `MISSING_REQUEST_BODY` | Gövdesiz POST/PUT | |
| `MISSING_REQUIRED_PATH_PARAM` / `INVALID_PATH_PARAM_TYPE` | `sellerId` vb. | |
| `MISSING_REQUIRED_QUERY_PARAM` / `QUERY_PARAM_EXCEEDS_MAXIMUM` | `size>100` gibi | Endpoint başına tavan tablosu |
| `INVALID_SELLER_ID` | | |
| `INVALID_AUTHORIZATION_FORMAT` / `EMPTY_AUTHORIZATION_CREDENTIALS` | Basic header bozuk | |
| `INVALID_SUPPLIER_ID_FORMAT` | `X-Supplier-Id` sayısal string değil ⚠️ | §2.5 |
| `MISSING_REQUIRED_HEADER` | `User-Agent` yok → **403** | Her istekte zorla |
| `NO_MUTABLE_FIELD` | `updatePriceAndInventory`'de yalnız barkod gönderilmiş | En az bir değişen alan olsun |
| `QUANTITY_EXCEEDS_LIMIT` | `quantity > 20000` | Clamp |
| `INVALID_BARCODE` | Boş / geçersiz barkod | Regex |
| `EMPTY_ITEMS_ARRAY` | `items: []` | |
| `TOO_MANY_BARCODES` | `barcodes` > 50 | Chunk'la |

**Uyarılar (istek gider ama muhtemelen reddedilir):**
`IMAGE_URL_NOT_HTTPS` · `EMPTY_ATTRIBUTES_ARRAY` · `BARCODE_INVALID_CHARACTERS` · `ATTRIBUTE_VALUE_MISSING` · `ATTRIBUTE_VALUE_AMBIGUOUS` · **`WRONG_ATTRIBUTE_FORMAT_FOR_CREATE`** · **`WRONG_ATTRIBUTE_FORMAT_FOR_UPDATE`** · `IMMUTABLE_FIELD_WARNING` · `ATTRIBUTES_FULL_SEND_REQUIRED` · `PAGE_SIZE_PRODUCT_EXCEEDS_LIMIT` · `BLANK_STRING`

Son iki attribute uyarısı §9.6'daki create-vs-update ayrımının doğrudan kanıtıdır.

### 8.5 Backoff politikası (öneri — ⚠️ Trendyol belgelemiyor)

| Durum | Politika |
|---|---|
| `429` | Tam jitter'lı üstel backoff, base 1 sn, tavan ~60 sn. Worker'ı bloklamak yerine yeniden kuyruğa alın. **Yalnızca ilgili servis grubunu** kısın, tüm istemciyi değil |
| `500` / `502` / `503` (prod) | 3–5 deneme, jitter ile |
| `503` (stage) | **Retry etmeyin** — IP allow-list eksik, yapılandırma sorunu |
| `504` | Batch boyutunu **yarıya** indirip tekrar deneyin (Trendyol'un kendi tavsiyesi) |
| `updatePriceAndInventory` herhangi bir hata | **Aynı gövdeyi asla körlemesine tekrar göndermeyin** → 15 dakikalık blok geçici hatayı kalıcı hataya çevirir. Arzu edilen durumdan payload'ı **yeniden türetin** ve değişmemiş kalemleri düşürün |
| `426` | Order V1 brownout — V2'ye geçene kadar backoff'la retry |

---

## 9. Veri modeli tuzakları

### 9.1 Kimlik alanları — hangisi join anahtarı?

| Alan | Anlamı | Maks | Değişebilir mi? | Nerede kullanılır |
|---|---|---:|---|---|
| **`barcode`** | **Trendyol'un SKU anahtarı.** Ürün, stok/fiyat, sipariş satırı, arşiv, silme, kilit kaldırma, varyant/teslimat güncellemesi | 40 | **Asla** (ne onaylı ne onaysız üründe) | **Kanonik join anahtarı** |
| `stockCode` | Satıcının kendi sistemindeki unique stok kodu ("Tedarikçi iç sistemindeki unique stok kodu") | 100 | Evet (`variant-bulk-update`) | Bizim SKU'muza karşılık gelir; **join anahtarı DEĞİL** (değişebilir, Trendyol tekilliğini garanti etmez) |
| `productMainId` | Satıcının belirlediği **model kodu**; varyant gruplama anahtarı; panelde "Model Kodu" | 40 | Onaylı üründe **hayır** | Varyant gruplama |
| `contentId` | Trendyol'un **içerik / ürün kartı** id'si | – | Trendyol atar | `content-bulk-update` ve `getUpdateAudits` için **zorunlu**; sipariş satırında da döner |
| `listingId` | Satıcının bir content'e ait listing id'si | – | Trendyol atar | `getProductBase` döner |
| `variantId` | Varyantın Trendyol id'si | – | Trendyol atar | Onaylı filtre yanıtlarında |
| `shipmentPackageId` | Sipariş paketi id'si | – | Trendyol atar — **sipariş ömrü boyunca KARARLI DEĞİL** | Tüm paket mutasyonlarının anahtarı |
| `orderNumber` | Trendyol ana sipariş numarası | – | Kararlı | **Paketler arası mutabakat anahtarı** |
| `lineId` | Sipariş satırı id'si | – | Trendyol atar | Split / iptal / fatura çağrıları |
| `merchantSku` | **Sipariş yanıtlarında ARTIK YOK.** 2 Nis 2026 stage / **6 Nis 2026 production**'da kaldırıldı, `stockCode` ile değiştirildi | – | – | Yalnız **`getClaims`** yanıtında hâlâ mevcut (§4.5.1) |

**Kural:** ürün tarafında `barcode`, sipariş tarafında `(orderNumber, shipmentPackageId, lineId)` üçlüsü. `shipmentPackageId` kısmi iptal/bölme sonrası değişir → **`shipmentPackageId` ile dedupe edin, `orderNumber` ile mutabakat yapın.**

### 9.2 Barkod karakter kuralları ve normalizasyon

- Maksimum **40 karakter**.
- İzin verilen özel karakterler: **yalnız `.` `-` `_`**.
- **Türkçe karakterler serbesttir** (ğ, Ğ, Ş, ş, İ, Ü …).
- **Ortadaki boşluklar sunucu tarafında sessizce birleştirilir:** `"ABC 123"` → `"ABC123"`, ve stok/fiyat güncellemeleri **içeri alınmış** barkoda göre yapılmalıdır.

**Sonuç:** sınırda normalize edin (iç boşlukları temizleyin) ve **batch sonucundaki `requestItem.barcode`** değerini kanonik biçim olarak saklayın. Normalize edilmemiş barkodla anahtarlarsanız (a) yarattığınız ürünü asla eşleştiremezsiniz, (b) stok güncellemeleriniz sessizce hiçbir şeyi hedeflemez.

**Tekillik:** barkod sizin kataloğunuzda tekildir; **aynı barkod farklı satıcılarda kasten aynı Trendyol content'ine düşer** — buybox rekabeti böyle çalışır. Trendyol politikası listelenen ürünün barkodun mevcut içeriğiyle birebir uyuşmasını ister; buybox'tan kaçmak için "barkod çoklama" suistimal sayılır. ⚠️ *Politika kaynağı topluluk/satıcı dokümantasyonu, geliştirici dokümanı değil.*

Doğrulayıcı kodları: `INVALID_BARCODE` (boş/geçersiz), uyarı `BARCODE_INVALID_CHARACTERS`.

### 9.3 Onaylı vs onaysız — iki ayrı dünya

| | **Onaysız (unapproved)** | **Onaylı (approved)** |
|---|---|---|
| Listeleme | `GET /products/unapproved` · `size ≤ 1000` · `status = rejected \| pendingApproval` · `dateQueryType = CREATED_DATE \| LAST_MODIFIED_DATE` · `rejectReasonDetails[]` döner | `GET /products/approved` · **`size ≤ 100`** · `status = archived \| blacklisted \| locked \| onSale` (+`notOnSale`? ⚠️) · `dateQueryType = VARIANT_CREATED_DATE \| VARIANT_MODIFIED_DATE \| CONTENT_MODIFIED_DATE` |
| Güncelleme | **Tek** endpoint: `unapproved-bulk-update` (anahtar `barcode`) — barkod hariç neredeyse her şey | **Üç** endpoint: `content-bulk-update` (anahtar **`contentId`**: title/description/images/attributes) · `variant-bulk-update` (anahtar `barcode`: stockCode/vatRate/adresler/dimensionalWeight/lotNumber/locationBasedDelivery/origin/channels/cargoProviders) · `delivery-info-bulk-update` (anahtar `barcode`: deliveryDuration/fastDeliveryType) |
| Değiştirilemez | `barcode` | `barcode`, `productMainId`, `brandId`, `categoryId`, **slicer/varianter attribute değerleri** |
| Kısmi güncelleme | Alan atlayarak; koleksiyonlarda (attributes/images) tam set gönderin | **content:** evet, **attributes hariç** (bir attribute değişecekse **tümü** gönderilmeli) · **variant:** ⚠️ çelişki (§4.2.4) · **images:** her zaman tam değiştirme |
| İkinci onay aşaması | Yok | **Var** — `getUpdateAudits` (§6.7) |

**Onay bir durumdur, bir endpoint değil.** "Onayla" servisi yoktur. Durum `getProductBase` (`approved`, `approvedDate`), `filterApprovedProducts` ve `filterUnapprovedProducts` üzerinden okunur.

Ürün yaratma **her zaman** onay sürecine girer: *"Ürün aktarma isteğinizin başarılı olması durumunda ürünleriniz **ürün onay sürecine girer**. Onay süreci devam eden ya da reddedilen ürünler **yayına çıkmaz**."*

**Adaptör kuralı:** güncelleme endpoint'ini seçmeden önce `approval_state`'i bilmek **zorundasınız**; yanlış endpoint = sessiz kalem hatası. `contentId`'yi bilinir bilinmez persist edin.

### 9.4 Arşiv vs sil vs kilit kaldır

| | **Arşiv** (`PUT /products/archive-state`) | **Sil** (`DELETE /products`) | **Kilit kaldır** (`PUT /products/unlock`) |
|---|---|---|---|
| Etki | Katalogtan gizler, **sistemde kalır** | **Geri alınamaz** | Satışı durdurulmuş ürünün kilidini açar |
| Geri alınabilir | Evet — `archived: false`; aynı batch'te karışık true/false serbest | Hayır | – |
| Ön koşul | Ürün sistemde var olmalı | Onay bekleyen: her zaman. **Onaylı: arşivde 1 GÜNDEN FAZLA kalmış VE Trendyol tarafından satışa durdurulmamış** | Kilit sebebi: düşük/yüksek fiyat, kritik fiyat hatası, tedarik edememe |
| Batch | 1000 kalem | belgesiz ⚠️ (1000 varsayın) | belgesiz ⚠️ |
| Görünürlük | `archived` + `archivedDate` filtre yanıtlarında | – | `locked`, `lockReason`, `lockDate` |

**Kanonik yaşam döngüsü:** `archive(true)` → **>1 gün bekle** → `delete`. Başka her sıralama **kalem düzeyinde** başarısız olur, HTTP düzeyinde değil.
`archived_at` kolonu **opsiyonel değildir** — silme uygunluğunun kapısıdır. Silme işini `archived_at < now() - 1 day` assert'i ile zamanlanmış bir job olarak modelleyin, senkron bir kullanıcı aksiyonu olarak değil.
Arşivlemenin stok/görünürlük üzerindeki tam etkisi **belgesiz** ⚠️ — stoku sıfırladığını varsaymayın.

### 9.5 Fiyat, KDV ve komisyon

- **`listPrice` = PSF** ("piyasa satış fiyatı", üstü çizilen). **`salePrice` = TSF** (gerçek satış fiyatı).
- **Sert kural: `listPrice >= salePrice`.** İhlal doğrulama hatasıdır (`INVALID_PRICE_RELATION`); ERP satıcıları mesajın *"TSF PSF'den Büyük olamaz"* olarak göründüğünü, `listPrice` boş bırakıldığında da tetiklendiğini bildiriyor ⚠️ *(üçüncü parti kaynak)*.
- `vatRate` bir **integer enum**'dur: TR'de `0, 1, 10, 20`. Doğrulayıcı kodu `INVALID_VAT_RATE`. GLOBAL pazaryerleri ülkeye özgü değerler kullanır.
- **`vatRate` varyant üzerindedir** → `variant-bulk-update` ile değiştirilir; `content-bulk-update` ile **değil**, `updatePriceAndInventory` ile **değil**.
- ⚠️ **doğrulanmadı:** Fiyatların tüketiciye görünen **KDV dâhil brüt** fiyatlar olduğu ve `vatRate`'in bu fiyatın içindeki oranı beyan ettiği (Trendyol'un üzerine eklediği bir oran olmadığı) — Türk entegratörlerin ve komisyon hesaplayıcılarının evrensel varsayımı, ancak **geliştirici dokümanında açık bir ifade bulunamadı**. **Doğrulama:** stage'de bilinen bir `salePrice` + `vatRate` ile bir sipariş yaratıp `lineGrossAmount` / `vatRate` / settlement `commissionAmount` ilişkisini ölçün.
- **Komisyon:** ayrı endpoint yok. `lines[].commission` sipariş satırında **oran** olarak (örn. `13`), onaylı ürün varyantında `commission` (örn. `7.83`) olarak döner; tutar bilgisi yalnız settlement kayıtlarında (`commissionRate`, `commissionAmount`, `commissionInvoiceSerialNumber`). Komisyonun KDV hariç matrah üzerinden hesaplandığı ⚠️ *(üçüncü parti kaynak)*.
- `updatePriceAndInventory` `quantity`, `salePrice`, `listPrice` alanlarını **bağımsız** kabul eder — **yalnız değişeni gönderin** (yalnız barkod gönderirseniz `NO_MUTABLE_FIELD`). `quantity` **satılabilir stok, mutlak değer**dir; sipariş geldikçe azalır; maksimum **20.000**.
- **§3/K3 ile bileşik sonuç:** "her zaman üç alanı da gönder" tasarımı 15 dakikalık dedup çakışmalarını maksimize eder. Alan bazında diff alın.
- `price.priceSeenByCustomer` onaylı filtre yanıtında döner ve `salePrice`'tan farklı olabilir (Trendyol kampanya indirimi) — **satıcının belirlediği fiyat değildir**, geri yazmayın.

### 9.6 ⚠️ EN KRİTİK ÇELİŞKİ — attribute payload şekli (create vs update)

Trendyol'un **kendi** kaynakları attribute alan adları konusunda birbiriyle çelişiyor. Yanlış şekil, kalem düzeyinde sessiz hata üretir.

| # | Kaynak | Şekil |
|---:|---|---|
| 1 | **TR kılavuz — *Ürün Yaratma v2*, örnek JSON** (`updatedAt 2026-08-13`) | `{"attributeId": 1, "attributeValueId": 1}` / `{"attributeId": 2, "customAttributeValue": "String"}` — **tekil** |
| 2 | **TR kılavuz — *Ürün Yaratma v2*, düzyazı kuralı** (aynı sayfa!) | *"Attribute altında bulunan **`attributeValueIds`** bilgisi … birden fazla değer alabilmektedir"* — **çoğul**. **Sayfa kendi içinde tutarsız.** |
| 3 | **Yayımlanmış OpenAPI — `/reference/createproducts`, `ProductAttribute` şeması** (`updatedAt 2026-06-09`) | `attributeId` + **`attributeValueIds`** (`array<integer>`) / **`attributeValue`** (`string`) — **çoğul + `attributeValue`**. `customAttributeValue` şemada **hiç yok** |
| 4 | **TR kılavuz — *Ürün Güncelleme Onaysız v2*, örnek JSON** | `attributeValueId` / `customAttributeValue` — **tekil** |
| 5 | **TR kılavuz — *Ürün Güncelleme Onaylı v2 (Content)*, örnek JSON** | `attributeValueId` / `customAttributeValue` — **tekil** |
| 6 | **`getBatchRequestResult` echo'su** (`ProductV2OnBoarding`, `ProductV2Update`, `ApprovedProductContentUpdate` örnekleri) | `{"attributeId":0, "attributeValueId":0, "customAttributeValue":"string"}` — **tekil**. *Bu, sunucunun gerçekte ne parse ettiğini gösterir.* |
| 7 | **`getUpdateAudits` → `changedAttributes[]`** | `attributeValueId` / `customAttributeValue` (+ `mediaUrl`, `isAllowedForUpdate`) — **tekil** |
| 8 | **Trendyol ajan eklentisi** (`modules.md`, `validation.md`) | **create = `attributeValueId` + `customAttributeValue`**; **update endpoint'leri = `attributeValueIds` + `attributeValue`**; karıştırırsanız `WRONG_ATTRIBUTE_FORMAT_FOR_CREATE` / `WRONG_ATTRIBUTE_FORMAT_FOR_UPDATE` |
| 9 | **Okuma yanıtları** (`filterUnapprovedProducts`, `filterApprovedProducts` varyant düzeyi) | `{"attributeId", "attributeName", "attributeValueId", "attributeValue"}` — **tekil id + `attributeValue` isim alanı**. Okuma şekli yazma şekliyle aynı değil |
| 10 | **`unapproved-bulk-update` / `content-bulk-update` OpenAPI şemaları** | `attributeValueIds` (`array<integer>`) + `attributeValue` (`string`) — **çoğul** |

**Durum özeti:**
- **Tüm TR kılavuz örnekleri ve tüm sunucu echo'ları** (6, 7) `attributeValueId` + `customAttributeValue` (**tekil**) diyor — create **ve** update için.
- **Tüm OpenAPI şemaları** (3, 10) `attributeValueIds` + `attributeValue` (**çoğul**) diyor — create **ve** update için.
- **Eklenti** (8) üçüncü bir hikâye anlatıyor: create tekil, update çoğul.

**Bu üç hikâyenin hiçbiri diğer ikisiyle uyuşmuyor.**

**KobiConnect kararı:**
1. **Create ve update attribute serializer'larını ilk günden AYRI sınıflar olarak yazın.** Sonradan ayırmak acı verir; eklenti bu ayrımın gerçek olduğunu iddia ediyor.
2. **Stage'de doğrulayın** — createProducts ve content-bulk-update için her iki şekli de deneyip `getBatchRequestResult` echo'sunda hangisinin geri geldiğine bakın. **Echo, sunucunun neyi parse ettiğinin kanıtıdır.**
3. Doğrulanana kadar varsayılan: **TR kılavuzunun ve echo'nun tekil şekli** (`attributeValueId`, `customAttributeValue`), çünkü altı bağımsız kaynak (1, 4, 5, 6, 7, 9) bunu destekliyor ve echo sunucu davranışını gösteriyor.
4. `allowMultipleAttributeValues = true` olan attribute'lar için çoğul `attributeValueIds` gerekiyorsa, **yalnız o durumda** çoğul forma düşün ve sonucu echo ile doğrulayın.
5. **Okuma tarafını asla yazma DTO'suyla paylaşmayın** — filtre yanıtları (`attributeName`, `attributeValue`) üçüncü bir şekildir.

### 9.7 `varianter` / `slicer` / `allowCustom` / `allowMultipleAttributeValues`

| Bayrak | Kural | Onay sonrası |
|---|---|---|
| `slicer = true` | trendyol.com'da **ayrı ürün kartı (content)** açar. Tipik olarak Renk; elektronikte dahili hafıza gibi. **Bir kategoride birden fazla slicer olabilir.** Slicer değeri varyant olarak kullanılabilir | **Değer değiştirilemez** |
| `varianter = true` | **Aynı content içinde** varyant (tipik olarak Beden). **Her kategoride tam olarak BİR varianter seçilebilir; birden fazla seçime izin verilmez** | **Değer değiştirilemez** |
| `allowCustom = true` | id yerine **serbest metin** gönderilir | – |
| `allowCustom = false` | **Yalnız** `getCategoryAttributeValues`'tan gelen id'ler; serbest metin reddedilir | – |
| `allowMultipleAttributeValues = true` | Attribute birden fazla değer alabilir | – |
| `required = true` | `attributes[]` içinde **değerle** bulunmalı | – |

**Varyantlama kuralı:** varyantlar aynı `productMainId` ile gönderilir ve **yalnız `attributes` bölümü farklılaşır.** Uluslararası best-practices tam istisna listesini veriyor — şunlar dışında **her şey aynı** olmalı: `barcode`, varyantlanan attribute değerleri, `price`, `stock`, `vatRate`, `stockCode`, `shipmentAddressId`, `returningAddressId`, `dimensionalWeight`, `lotNumber`, `locationBasedDelivery`, `deliveryOptions`.

**Sonuç:** `is_varianter` / `is_slicer` bayrakları persist edilmezse varyant gruplama çöker ve ürünler tek listinge katlanır.
`allowCustom = false` **ve** `required = true` çifti, o kategori için attribute değer listesi senkronize edilmeden ürün publish edilemeyeceği anlamına gelir — publisher bu ön koşulu assert etmelidir.

**`Web Color` (attributeId 295) 15 Ocak 2025'ten beri zorunludur** — eksikse ürün yaratma başarısız olur.

### 9.8 HTML açıklama ve görsel kuralları

**`description`:**
- Maksimum **30.000** karakter.
- Uluslararası best-practices: **minimum 4.000 karakter**, ya da `<img>` etiketi içermeli.
- Basit HTML olmalı: **JS dosyası yok, style yok, iframe yok.**

**`images`:**
- **Barkod başına maksimum 8 görsel.**
- URL'ler **geçerli SSL sertifikalı `https`** olmalı (doğrulayıcı uyarısı `IMAGE_URL_NOT_HTTPS`).
- Boyut: **1200 × 1800, 96 dpi**.
- `images` dizisi güncelleme endpoint'lerinde **tam değiştirmedir** — delta göndermeyin, sıralı tam seti üretin. Mapper'ınızda `buildFullImageSet()` olsun, `imageDiff()` değil.
- Alan adı okuma tarafında değişiyor: create/onaylı filtre **`images`**, onaysız filtre **`media`**.

**Diğer alan tavanları:** `title` 100 · renk attribute değeri 50 · `lotNumber` 100 (charset `A-Z a-z 0-9 , - . : /`) · `barcode` 40 · `productMainId` 40 · `stockCode` 100 · `origin` 2.

### 9.9 Para, sayı ve tarih tipleri

- Para alanları JSON'da `number/double` gelir → **veritabanında asla float kullanmayın.** `decimal(14,4)` veya tamsayı minor unit.
- Paket düzeyinde para birimi garanti değildir; `lines[].currencyCode` ve paket düzeyinde `currencyCode` gelir (örnek: `"TRY"`). Siparişin para birimini satırlardan türetin.
- `cargoTrackingNumber` `int64` tanımlı ama **gerçek değerler int64'ü ve JS `Number.MAX_SAFE_INTEGER`'ı aşıyor** → **uçtan uca string** (DB varchar, PHP string, JS string).
- `warehouseId` `int32`, çevredeki id'lerin çoğu `int64` — yine de her yerde 64-bit kolon kullanın.
- Epoch değerleri **milisaniye**dir; §4.2.9 (`getUpdateAudits`) ve `ProductInventoryUpdate` echo'sundaki `updateRequestDate` **ISO-8601 string**tir. Tek satıcı içinde iki format.
- Zaman dilimi: `orderDate` "GMT +3", `createdDate` "GMT" olarak etiketli — **çelişki** ⚠️. UTC'ye normalize edip ham epoch'u saklayın, stage'de kalibre edin.
- `productSize` değerleri **baştan boşluklu** gelebilir (`" 21"`) → trim.

---

## 10. Webhook entegrasyonu

### 10.1 Kapsam — yalnız sipariş paketi statüleri

Trendyol'un webhook modeli **yalnızca sipariş paketi statüleri** üzerinedir. `ORDER_CREATED`, `PRODUCT_PRICE_CHANGED`, `PRODUCT_APPROVED` gibi bir olay taksonomisi **yoktur**; bu isimler dokümantasyonun hiçbir yerinde geçmez. **Ürün, fiyat, stok, onay veya iade için webhook yoktur.**

Tek abonelik ekseni `subscribedStatuses`'tır — 13 değer, §5.8.

### 10.2 Payload şekli

- Metot **`POST`**, `Content-Type: application/json`.
- Payload **tam sipariş modelidir ve `getShipmentPackages` ile birebir aynıdır** — aynı `{"totalElements","totalPages","page","size","content":[…]}` zarfı dâhil.
- *"Yapılan istekler **statü farketmeksizin full order datası** olarak iletilecektir"* → hangi statünün tetiklediğini payload'dan siz çıkarırsınız (`shipmentPackageStatus` / `status`).
- Alan listesi §4.4.1 ile aynıdır.

### 10.3 Kimlik doğrulama — imza yok, yön ters

**HMAC imzası ve doğrulama başlığı YOKTUR.** Yön terstir: **Trendyol, sizin kaydettiğiniz kimlik bilgileriyle KENDİNİ SİZE doğrular.**

| `authenticationType` | Trendyol'un gönderdiği |
|---|---|
| `BASIC_AUTHENTICATION` | Standart HTTP Basic başlığı (`username` + `password`) |
| `API_KEY` | **`x-api-key`** başlığı |

**Sonuç — KobiConnect'te ne yapıyoruz:**
1. Endpoint'i **yalnız HTTPS** üzerinde sonlandırın.
2. Her abonelik için **rastgele, yüksek entropili, tenant'a özgü** bir `apiKey` üretin (paylaşılan sabit anahtar değil) — böylece gelen istek doğrudan bir `marketplace_account`'a çözümlenir.
3. Gelen kimlik bilgisini **sabit zamanlı** karşılaştırın; eşleşmezse `401` dönün ve `webhook_deliveries.auth_ok = false` olarak loglayın.
4. URL yolu **tahmin edilemez** olsun (rastgele segment) — imza olmadığı için gizlilik ikinci savunma katmanıdır.
5. **Giden webhook'lar için yayımlanmış bir IP allow-list'i yoktur** ⚠️ **doğrulanmadı** — IP tabanlı filtreleme yapamazsınız.
6. Payload'ı **hiçbir zaman tek başına güvenilir kabul etmeyin**; kritik durum geçişlerini `orders/stream` mutabakatıyla teyit edin.

### 10.4 Yeniden deneme ve backoff

> "Webhook servislerinize yapılan herhangi bir başarısız istek olması durumunda, Trendyol, **başarılı olana kadar her 5 dakikada bir** başarısız istekleri iletecektir. (Bu gelecekte değiştirilecektir)"

- **Sabit 5 dakikalık aralık, başarılı olana kadar.** Üstel backoff **değil**, belgelenmiş bir maksimum deneme sayısı **yok**.
- **Başarı kriteri belgesiz** ⚠️ — muhtemelen 2xx, ama yazılı değil. **Doğrulama:** stage'de 200/202/204/3xx dönen bir endpoint kurup hangisinin retry'ı durdurduğunu ölçün.
- **Teslimat zaman aşımı belgesiz** ⚠️ — endpoint'inizin ne kadar hızlı yanıt vermesi gerektiğine dair SLA yok. **Hızlı 2xx dönüp işi asenkron kuyruğa alın.**
- ❌ Üçüncü parti bir kaynağın iddia ettiği *"5 deneme, üstel backoff, 3 saniyede HTTP 200 dönülmeli"* bilgisi **resmî dokümanla çelişiyor** — kullanmayın.

### 10.5 13 hatada otomatik pasife alma

> "Webhook servislerinde **13 kez hata alınması** durumunda, sistem otomatik olarak aksiyon alacak olup ilgili webhook isteğini **pasife alacaktır**."

İki e-posta gönderilir: (1) hata alınan webhook ID'si + istek atmaya devam edilecek süre, (2) sürenin dolduğu ve webhook'un pasife alındığı bildirimi.

**Nasıl tespit edilir ve nasıl kurtarılır:**
1. **E-postaya güvenmeyin.** `getWebhooks`'u periyodik (örn. 15 dakikada bir) çağıran bir **mutabakat job'u** çalıştırın ve `status` alanını izleyin.
2. `status == "PASSIVE"` ve bizim beklediğimiz `ACTIVE` ise → alarm + `webhook_subscriptions.status = 'error'`.
3. Alıcı sağlığını doğrulayın (KobiConnect'in kendi health-check'i), sonra `PUT .../webhooks/{Id}/activate` ile aktife alın.
4. **Pasif kalınan süre boyunca olayların kuyruklanıp reaktivasyonda oynatıldığı belgesiz** ⚠️ → **kayıp varsayın** ve `orders/stream` ile pasif pencereyi kapsayan bir backfill çalıştırın.
5. Alıcınız bozukken 5 dakikalık retry'ların birikmesini istemiyorsanız, planlı bakımda webhook'u **silmek yerine `deactivate` edin** (id ve kayıt korunur).

### 10.6 Satıcı başına 15 webhook tavanı

> "1 satıcı için oluşturulabilecek **maksimum webhook sayısı 15**'tir. **Pasife alınan webhooklar da bu sayıya dâhildir.**"

Tavanı aşarsanız yeni webhook yaratamazsınız; önce mevcut birini silmelisiniz.
**KobiConnect kuralı:** hesap başına **tek** bir webhook aboneliği yeterlidir (tüm statüler tek endpoint'e düşüyor, zaten full order datası geliyor). Create'ten önce `getWebhooks` ile sayıyı kontrol edin ve yerel `unique(marketplace_account_id, callback_url)` kısıtıyla çift-create'i engelleyin (Trendyol duplicate URL reddi **belgesiz** ⚠️).

**URL kısıtı:** endpoint `Trendyol`, `Dolap`, `Localhost` ibarelerini **içeremez**. Doğrulamayı kendi tarafınızda da yapın.

### 10.7 Teslimat semantiği (dokümantasyon sessiz — savunmacı plan)

| Özellik | Durum |
|---|---|
| **En-az-bir-kez** | **Yapı gereği garanti.** Sonsuz 5 dakikalık retry + dedup token yokluğu → ACK'iniz kaybolduğunda veya geciktiğinde kopya **kesindir** |
| **Sıralama** | **Garanti belgelenmemiş** ⚠️. Olay başına bağımsız retry zamanlayıcıları ile, retry edilen bir `CREATED` taze bir `SHIPPED`'den **sonra** gelebilir. **Sıralı varış varsayan bir durum makinesi yazmayın** |
| **Tam-bir-kez** | İmkânsız. Event id yok, delivery id yok, imza yok |
| **Dedup anahtarı** | Trendyol vermiyor. Kendiniz üretin |

**Önerilen dedup (⚠️ çıkarım):**
`dedupe_key = hash(orderNumber, shipmentPackageId, shipmentPackageStatus, lastModifiedDate)` üzerinde **unique** kısıt + **monoton koruma**: sakladığınızdan daha eski bir statü/zamana sahip olayı **yok sayın**.

**Dikkat:** kısmi iptal ve bölme, `orderNumber`'ı koruyarak **yeni `shipmentPackageId` ve yeni kargo barkodu** üretir; `createdBy` hangisi olduğunu (`order-creation` | `cancel` | `split` | `transfer`), `originPackageIds` da öncekini söyler. Yani **`shipmentPackageId` siparişin ömrü boyunca kararlı değildir** — onunla dedupe edin, `orderNumber` ile mutabakat yapın.

### 10.8 Neden polling doğruluğun kaynağı olarak kalıyor

Trendyol açıkça yazıyor:
> "Webhook ile veri gönderimi **her zaman olanaklı olamadığı** için, ilgili servis üzerinden, Trendyol'dan **periyodik olarak veri almak için geliştirme yapmanız tavsiye edilmektedir**. Örneğin sipariş datası için bir webhook isteği oluşturduysanız, **getshipmentpackage servisini kullanarak periyodik olarak datalarınızı eşitlemenizi öneririz**."

**Referans mimari:**
```
webhook (düşük gecikme)  ─┐
                          ├─▶ aynı idempotent upsert (anahtar: shipmentPackageId)
orders/stream (doğruluk) ─┘        + monoton statü koruması
```
- **Webhook:** anlık bildirim, kullanıcıya "yeni sipariş" göstermek için.
- **`getShipmentPackagesStream`:** mutabakat. Cursor tabanlı, **3 aylık** erişim (v2/orders'ın 1 ayına karşı), 10.000 kayıt penceresi **yok**, minimum 5 saniye aralık. Checkpoint olarak işlenen en yüksek `lastModifiedDate`'i saklayıp bir sonraki koşuda `lastModifiedStartDate` olarak, kasıtlı örtüşmeyle kullanın.
- **`v2/orders`:** yalnız hedefli sorgu (`orderNumber`, `shipmentPackageIds`) ve `CreatedDate ASC` backfill'leri.
- İki yol da **aynı tablolara** yazmalı; iki ayrı sipariş modeli oluşursa ayrışırlar.

---

## 11. KVKK / kişisel veri

Trendyol sipariş ve soru payload'ları **kimliği belirli gerçek kişilere ait veri** taşır. KobiConnect çok kiracılı bir SaaS olduğu için bu veriler **veri işleyen** sıfatıyla elimizdedir ve ayrı muamele gerektirir.

### 11.1 Kişisel veri taşıyan alanlar

| Alan | Kaynak | Hassasiyet | Yükümlülük |
|---|---|---|---|
| **`customerTckn`** | `getShipmentPackages` / `v2/orders` (OpenAPI şeması) | **Kimlik numarası — özel nitelikli sayılabilecek yüksek riskli veri** | Kolon düzeyinde şifreleme (at rest), loglarda **asla**, export'larda maskeli, en kısa saklama süresi |
| **`identityNumber`** | Sipariş payload'ı — **altın, gübre veya 5000₺ üzeri** siparişlerde TCKN buradan gelir | Aynı | Aynı |
| `customerFirstName`, `customerLastName` | Sipariş, claim | Kimlik | Şifreli sütun veya ayrı PII tablosu |
| `customerEmail` | Sipariş | İletişim | Aynı |
| `customerId` | Sipariş, soru | Takma kimlik (pseudonym) — tek başına kimlik belirlemez ama birleşimle belirler | İlişkilendirme anahtarı; log'da hash'li |
| `shipmentAddress` / `invoiceAddress` (tüm alanlar: `firstName`, `lastName`, `fullName`, `address1/2`, `fullAddress`, `phone`, `postalCode`, `neighborhood`, `latitude`, `longitude`) | Sipariş, webhook | **Tam adres + telefon + koordinat** | PII tablosunda, şifreli; `latitude`/`longitude` özellikle hassas |
| `taxNumber`, `taxOffice`, `company` | `invoiceAddress` (yalnız `commercial=true`) | Ticari kimlik | Kişisel veri sayılmayabilir ama ticari sır; aynı korumaları uygulayın |
| `userName` (Q&A) | Soru payload'ı — **Trendyol tarafından maskelenmiş** ad soyad | Maskeli de olsa kişisel veri | Şifreleyin, loglamayın; `showUserName=false` ise **boş gelir** |
| `customerNote` (claim) | İade kalemi | Serbest metin — içinde her şey olabilir | Serbest metin PII sızıntı riski; tam metin araması yaparken dikkat |
| `defectiveClaimListingInsight` | Sipariş satırı | Ürün içgörüsü, kişisel değil | – |

### 11.2 Bunun yarattığı yükümlülükler

1. **Ayrı PII tablosu.** `customerTckn`, `identityNumber`, ad, soyad, e-posta, telefon ve adresler sipariş grafiğinden ayrı bir tabloda, **kolon düzeyinde şifreli** tutulur. Geri kalan sipariş verisi böylece serbestçe sorgulanabilir ve raporlanabilir kalır.
2. **`raw_payload` bir sızıntı vektörüdür.** Trendyol payload'ını olduğu gibi saklamak (ki §9'daki alan istikrarsızlığı yüzünden pratikte gerekli) PII'yi jsonb kolonuna kopyalar. Ya `raw_payload`'ı da şifreleyin ya da saklamadan önce PII alanlarını redakte edip ayrı tabloya yazın.
3. **Log ve hata izleme redaksiyonu.** İstek/yanıt gövdelerini loglayan HTTP middleware'i ve exception reporter (Sentry vb.) `customerTckn`, `identityNumber`, `customerEmail`, `phone`, `fullAddress`, `latitude`, `longitude` alanlarını **redakte etmelidir**. Bu, sipariş senkronunda en olası tek KVKK ihlali kaynağıdır.
4. **Saklama süresi.** Sipariş PII'sini iş amacının gerektirdiği süreden uzun tutmayın. Trendyol'un kendi sipariş geçmişi API üzerinden **1 aya** düşürüldü (§12) — bizim saklama politikamızın Trendyol'unkinden uzun olması bilinçli bir karar olmalı, kaza değil. Faturalama yükümlülüğü ayrıdır (fatura linki 10 yıl erişilebilir kalmalı, ama bu **linktir**, PII kopyası değil).
5. **Silme/anonimleştirme akışı.** Tenant hesabı kapandığında veya bir veri sahibi talebi geldiğinde PII tablosunda satır bazında silme/anonimleştirme yapılabilmeli; sipariş istatistikleri `customer_id` hash'i üzerinden korunabilir.
6. **Alt işleyen şeffaflığı.** Trendyol verisi tenant adına işlenir; aydınlatma metni ve veri işleyen sözleşmesinde Trendyol'un veri kaynağı olduğu belirtilmelidir.
7. **Webhook alıcısı.** İmzasız olduğu için (§10.3) alıcı endpoint, PII taşıyan bir payload'ı kimliği doğrulanmamış bir istekten kabul etmemeli — `x-api-key` / Basic doğrulaması **başarısızsa gövdeyi loglamadan** `401` dönün.

---

## 12. Kırıcı değişiklik takvimi

Bugün: **19 Ağustos 2026**. Aşağıdaki tarihler resmî changelog ve doküman sayfalarından alınmıştır.

| Tarih | Değişiklik | Etki | Aksiyon |
|---|---|---|---|
| **Yürürlükte** — 25–26 Şub 2025 duyuru, **26 May 2025 kapanış** | `api.trendyol.com/sapigw` base URL kapatıldı → `apigw.trendyol.com/integration` | Eski host'a giden her istek ölü | Yeni host'u kullanın; `sapigw` hard-code eden topluluk SDK'larını kullanmayın (§13) |
| **Yürürlükte** — 5 Mart 2026 | Sipariş geçmişi **3 ay → maksimum 1 ay (30 gün)** | 1 aydan eski sipariş API'den çekilemez (yalnız satıcı paneli) | Sorguları ≤1 aya sınırlayın. Daha geniş geçmiş için `orders/stream` (3 ay) |
| **Yürürlükte** — 27 Mart 2026 | Q&A servislerine limit eklendi: çekme 1000/dk, cevaplama 500/dk | – | §7.5 |
| **Yürürlükte** — stage 2 Nis 2026 / **production 6 Nis 2026** | **Eski alan adları sipariş & webhook yanıtlarından KALDIRILDI** | Eski adları okuyan kod **null alır** | Yeni adlara geçin (aşağıdaki tablo) |
| **Yürürlükte** — 8 Haz 2026 | `getShipmentPackages` maksimum erişilebilir kayıt **10.000**; limitler kademeli tabloya geçti | Geniş taramalar 429 ve pencere duvarına çarpar | `orders/stream`'e geçin |
| **Yürürlükte** — 31 Tem 2026 | **Trendyol Luxe:** ürün servislerine `channels` (`CORE`/`LUXE`) desteği; `getBrands` yanıtına `LUXE` boolean; onaylı filtre `variants[].channels`; iade ve webhook servisleri etkilendi | Yalnız onaylı ürün akışları (TR storefront); onaysız create/update kapsam dışı | `channels` göndermeyin (varsayılan davranış doğrudur) — yalnız bir kanalı açıp kapatırken gönderin. `[]` göndermeyin, hata döner |
| **Yürürlükte** — **17 Ağu 2026** | **`deliveryOption` semantiği değişti:** `fastDeliveryType` bağımlılığı kaldırıldı, işlem yalnız `deliveryDuration` ile yönetiliyor. **`0` = Bugün Kargoda, `1` = En Geç Yarın Kargoda** | Eski yapı (`deliveryDuration: 1` + `fastDeliveryType`) yerini yenisine bırakıyor | ⚠️ **ÇELİŞKİ:** changelog (17.08.2026) değişikliği yürürlükte gösteriyor; ürün kılavuz sayfaları hâlâ *"Ağustos 2026 itibari ile (değişikliğin geçerli olacağı gün bilgisi güncellenecektir)"* diyor. **Stage'de hangi semantiğin aktif olduğunu ölçün.** |
| **Eylül 2026 sonu** | `deliveryDuration: 1` + `fastDeliveryType: "SAME_DAY_SHIPPING"` kayıtlı tüm ürünler **otomatik olarak `deliveryDuration: 0`'a çevrilecek** | Manuel işlem gerekmez, ama **yerel kopyanız Trendyol'la ayrışır** | Eylül sonu sonrası `filterApprovedProducts` ile `deliveryOptions`'ı yeniden senkronlayın |
| **14 Eylül 2026** | **Ürün servisleri rate limit modeli değişiyor: endpoint başına → servis grubu başına, listeleme kademesine göre.** `updatePriceAndInventory` **LİMİTSİZ → 350–2000 req/dk** | **Bu takvimdeki en yüksek riskli madde.** Throttle'sız stok pusher'ı olan herkes 429 almaya başlar | Grup bazlı token bucket'ı **bu tarihten önce** devreye alın (§7.3, §7.7) |
| **15 Eylül 2026** | **Ürün V1 servisleri kapanıyor** (`POST/PUT/GET /product/sellers/{sellerId}/products`, V1 kategori attribute'ları) | O tarihe kadar V1'e giden isteklere **günde 3 kez 15'er dakika** brownout: `"This endpoint is temporarily unavailable due to a scheduled brownout. Please refer to the current integration documentation to migrate your service to Product v2."` | KobiConnect zaten yalnız V2 kullanmalı (§4.2) |
| **15 Ekim 2026** | **Order V1 `GET /order/sellers/{sellerId}/orders` kapanıyor.** `v2/orders` zorunlu hâle geliyor. **10.000 kayıt penceresi (`maxQueryWindowResult`)** ve **1 ay geçmiş** sınırı devreye giriyor | O tarihe kadar V1'e giden isteklere **günde 3 kez 10'ar dakika `426`** dönüyor. 10.000 sınırı `shipmentPackageId` bazında; güvenli sayfa aralığı **page 0–49** (`size=200` ile) | `v2/orders`'a geçin; tarama için `orders/stream` kullanın |
| **23 Ekim 2026** | **`origin` bağımsız, zorunlu bir alan hâline geliyor.** Attribute altından menşei gönderimi opsiyonelleşiyor (ileride tamamen kaldırılacak, tarih paylaşılacak) | Hibrit dönem: bugün `origin` opsiyonel, ama menşei kategori için zorunlu bir attribute ise attribute altından gönderilmeye devam **zorunlu** | `origin`'i **şimdiden** her create/update payload'ına ekleyin. Geçmiş veriler Trendyol tarafından otomatik taşınacak |
| **10 Ağustos 2026** (International / v3.0) | Uluslararası Ürün **V1 servisleri geçersiz** — yalnız V2 | Yalnız International storefront'lar | KobiConnect MVP'si TR — etkilenmiyor |
| **15 Haziran 2026** (taslak) | Trendyol Yurt Dışı Aracılığı (`is4P: true`) yanıtlarına `invoiceNumber`, `invoiceStatus`, `invoiceRejectedReasonKeys` ekleniyor | Yalnız 4P siparişleri | §5.11 |
| Süregelen | **Barkod bazlı → content bazlı** ürün modeline geçiş (21 Tem 2026 duyurusu) | Seller Center ile entegrasyon servisleri arasındaki veri modeli hizalanıyor | `contentId`'yi birinci sınıf kimlik olarak modelleyin |

### 12.1 6 Nisan 2026'da kaldırılan alan adları (sipariş & webhook yanıtları)

| Eski ad | Yeni ad |
|---|---|
| `merchantSku` | **`stockCode`** |
| `merchantId` | `sellerId` |
| root `id` | `shipmentPackageId` |
| line `id` | `lineId` |
| line `amount` | `lineGrossAmount` |
| line `discount` | `lineSellerDiscount` |
| line `tyDiscount` | `lineTyDiscount` |
| line `lineItemDiscount` | `lineItemSellerDiscount` |
| line `price` | `lineUnitPrice` |
| root `grossAmount` | `packageGrossAmount` |
| `totalDiscount` | `packageSellerDiscount` |
| `totalTyDiscount` | `packageTyDiscount` |
| `totalPrice` | `packageTotalPrice` |
| `productCode` | `contentId` |
| `vatBaseAmount` | `vatRate` |

**Tamamen kaldırılanlar:** `sku`, `scheduledDeliveryStoreId`, `agreedDeliveryDateExtendible`, `extendedAgreedDeliveryDate`, `agreedDeliveryExtensionEndDate`.
**Eklenenler (aynı parti):** `cancelledBy`, `cancelReason`, `cancelReasonCode`, `lineTotalDiscount`, `packageTotalDiscount`.

> **⚠️ DİKKAT:** Yayımlanmış OpenAPI `ShipmentPackage` / `OrderLine` şemaları **hâlâ eski adları listeliyor** (`id`, `merchantSku`, `merchantId`, `amount`, `discount`, `tyDiscount`, `price`, `grossAmount`, `totalDiscount`, `totalTyDiscount`, `productCode`, `vatBaseAmount`, `sku`). **Kılavuz sayfasının örnek yanıtı doğrudur, OpenAPI şeması bayattır.** OpenAPI'den kod üretmeyin — ya da ürettikten sonra alan adlarını kılavuza göre düzeltin.
> `merchantSku` **`getClaims` yanıtında hâlâ mevcuttur** — kaldırma yalnız sipariş/webhook modelini kapsadı.

---

## 13. Kaynaklar

### 13.1 Resmî — Trendyol geliştirici dokümanları (TR)

Her URL'ye `.md` eklenerek ham markdown alınabilir; `/reference/*` sayfaları gömülü OpenAPI JSON içerir. Her sayfa `updatedAt` front-matter damgası taşır → **değişiklik dedektörü olarak kullanılabilir**.

| Konu | URL | `updatedAt` |
|---|---|---|
| Doküman indeksi (makine okunur) | `https://developers.trendyol.com/llms.txt` | – |
| Uluslararası indeks | `https://developers.trendyol.com/v3.0/llms.txt` | – |
| Genel bakış | `https://developers.trendyol.com/docs/getting-started` | 2026-07-13 |
| **Authorization / User-Agent / 50-per-10s** | `https://developers.trendyol.com/docs/2-authorization` | 2026-04-27 |
| **Canlı-Test ortam bilgileri / IP allow-list** | `https://developers.trendyol.com/docs/3-canlı-test-ortam-bilgileri` | 2026-07-13 |
| **Servis limitleri** | `https://developers.trendyol.com/docs/1-servis-limitleri` | – |
| **Sıkça sorulan sorular** (webhook pasife alma, batch, 409) | `https://developers.trendyol.com/docs/5-sıkça-sorulan-sorular` | 2026-04-09 |
| **Hata kodları** | `https://developers.trendyol.com/docs/hata-kodları` | 2026-05-06 |
| **Changelog** (tüm 2026 tarihleri, alan yeniden adlandırmaları) | `https://developers.trendyol.com/changelog/changelog` | – |
| Ürün V2 endpoint listesi | `https://developers.trendyol.com/docs/ürün-v2-api-endpoint` | 2026-07-20 |
| **Ürün Yaratma v2** | `https://developers.trendyol.com/docs/ürün-yaratma-v2` | 2026-08-13 |
| Ürün Yaratma Akışı (diyagram/video) | `https://developers.trendyol.com/docs/ürün-yaratma-akışı` | 2026-04-09 |
| Ürün Güncelleme — Onaysız v2 | `https://developers.trendyol.com/docs/ürün-güncelleme-onaysız-ürün-v2` | 2026-08-13 |
| **Ürün Güncelleme — Onaylı v2** (content / varyant / teslimat) | `https://developers.trendyol.com/docs/ürün-güncelleme-onaylı-ürün-v2` | 2026-08-13 |
| **Stok ve Fiyat Güncelleme** (15 dk kuralı, 1000 SKU, 20.000 stok) | `https://developers.trendyol.com/docs/stok-ve-fiyat-güncelleme-updatepriceandinventory-1` | 2026-08-07 |
| **Toplu İşlem Kontrolü** (4 saat, tüm batch tipi örnekleri) | `https://developers.trendyol.com/docs/toplu-i̇şlem-kontrolü-getbatchrequestresult-1` | 2026-08-14 |
| Ürün Silme | `https://developers.trendyol.com/docs/ürün-silme-1` | 2026-07-20 |
| Ürün Arşivleme | `https://developers.trendyol.com/docs/ürün-arşivleme-archiveproducts` | 2026-07-10 |
| Ürün Kilit Kaldırma | `https://developers.trendyol.com/docs/ürün-kilit-kaldırma-servisi-1` | 2026-02-10 |
| Kategori Özellik Listesi v2 (required/allowCustom/slicer/varianter) | `https://developers.trendyol.com/docs/kategori-özellik-listesi-v2` | 2026-05-05 |
| Kategori Özellik Değerleri v2 | `https://developers.trendyol.com/docs/kategori-özellik-değerleri-listesi-v2` | 2026-03-25 |
| Ürün Filtreleme — Temel Bilgiler v2 (`getProductBase`) | `https://developers.trendyol.com/docs/ürün-filtreleme-temel-bilgiler-v2` | 2026-01-22 |
| Ürün Filtreleme — Onaysız v2 (`rejectReasonDetails`) | `https://developers.trendyol.com/docs/ürün-filtreleme-onaysız-ürün-v2` | 2026-08-14 |
| Ürün Filtreleme — Onaylı v2 (`size ≤ 100`) | `https://developers.trendyol.com/docs/ürün-filtreleme-onaylı-ürün-v2` | 2026-08-14 |
| Ürün Filtreleme — Onaylı v2 Stok ve Fiyat | `https://developers.trendyol.com/docs/ürün-filtreleme-onaylı-ürün-v2-stok-ve-fiyat` | 2026-08-07 |
| **Ürün Bilgileri Güncelleme Sonucu Kontrol** (`update-audits`) | `https://developers.trendyol.com/docs/ürün-bilgileri-güncelleme-sonucu-kontrol-servisi` | 2026-07-20 |
| Ürün Menşei Değerleri | `https://developers.trendyol.com/docs/ürün-menşei-değerleri` | – |
| Kargo Firması Filtreleme Servisi | `https://developers.trendyol.com/docs/kargo-firması-filtreleme-servisi` | – |
| **Sipariş Paketlerini Çekme** (`size ≤ 200`, 10.000 penceresi, v2 migrasyonu) | `https://developers.trendyol.com/docs/sipariş-paketlerini-çekme-getshipmentpackages` | 2026-08-14 |
| **Sipariş Paketlerini Akış ile Çekme** (cursor) | `https://developers.trendyol.com/docs/sipariş-paketlerini-akış-ile-çekme` | 2026-08-14 |
| Test Siparişi Oluşturma | `https://developers.trendyol.com/docs/marketplace/siparis-entegrasyonu/test-siparisi-olusturma` | – |
| Fatura Linki Gönderme | `https://developers.trendyol.com/docs/fatura-linki-gönderme-sendinvoicelink` | – |
| İadesi Oluşturulan Siparişleri Çekme (`getClaims`) | `https://developers.trendyol.com/docs/i̇adesi-oluşturulan-siparişleri-çekme-getclaims` | – |
| Müşteri Sorularını Çekme | `https://developers.trendyol.com/docs/müşteri-sorularını-çekme` | 2026-08-14 |
| Müşteri Sorularını Cevaplama | `https://developers.trendyol.com/docs/müşteri-sorularını-cevaplama` | 2026-03-31 |
| **Webhook Model** (retry, 13 hata, 15 tavan, statüler, tam payload) | `https://developers.trendyol.com/docs/webhook-model` | 2026-08-14 |
| Webhook Yaratma | `https://developers.trendyol.com/docs/webhook-yaratma` | 2026-01-22 |
| Webhook Listeleme | `https://developers.trendyol.com/docs/webhook-listeleme` | 2026-01-22 |
| Webhook Güncelleme | `https://developers.trendyol.com/docs/webhook-güncelleme` | 2026-01-22 |
| Adres Bilgileri Servisleri | `https://developers.trendyol.com/docs/adres-bilgileri` | – |
| API durumu (canlı) | `https://developers.trendyol.com/docs/api-status` | – |

**OpenAPI referans sayfaları** (gömülü OpenAPI 3.0.3 JSON):
- `https://developers.trendyol.com/reference/createproducts.md` (2026-06-09)
- `https://developers.trendyol.com/reference/getbatchrequestresult.md` (2026-06-09)
- `https://developers.trendyol.com/reference/getcategoryattributes.md` (2026-06-09)
- `https://developers.trendyol.com/reference/updatepriceandinventory.md` (2026-06-09)
- `https://developers.trendyol.com/reference/getshipmentpackages.md` (2026-01-27)
- `https://developers.trendyol.com/reference/getshipmentpackagesstream.md` (2026-04-07)
- `https://developers.trendyol.com/reference/getclaims.md`
- `https://developers.trendyol.com/reference/answerquestion.md` (2026-02-16)

### 13.2 Resmî — Uluslararası (EN, v3.0)

- Product Services Best Practices (varyant kuralları, HTML 4k–30k, V1 kapanış 10 Ağu 2026): `https://developers.trendyol.com/v3.0/docs/1-product-services-best-practices` (2026-04-09)
- Product Create v2 (EN alan tablosu, `storeFrontCode` başlığı): `https://developers.trendyol.com/v3.0/docs/product-create-v2` (2026-08-13)
- Origin Value List: `https://developers.trendyol.com/v3.0/docs/origin-value-list`

### 13.3 Resmî — Trendyol GitHub / diğer

| Kaynak | Not |
|---|---|
| **`https://github.com/Trendyol/trendyol-integration-developer-tool`** | **Apache-2.0, Ağu 2026. SDK değil — ajan eklentisi + kural dosyaları.** `rules/trendyol-rules.md` (asenkron farkındalık, 4 saat), `skills/.../references/code-patterns.md` (polling temposu, dedup deseni), `.../modules.md` (endpoint başına limitler, sayfa boyutu tuzakları, yaşam döngüsü kuralları), `.../validation.md` (§8.4 hata/uyarı kataloğu). **Bulunan en iyi ikincil kaynak** — ancak dokümanla çeliştiği yerler var (§9.6, §4.2.4), o yüzden **resmî sayfaların altında** güven seviyesindedir |
| `https://apigw.trendyol.com/trendyol-developer-tools-mcp-server/sse` | Trendyol'un barındırdığı, canlı endpoint sözleşmelerini sunan MCP sunucusu. **⚠️ doğrulanmadı** — bağlanılamadı; erişim Trendyol tarafından verilen kimlik bilgisi gerektiriyor olabilir |
| `https://developers.trendyolefaturam.com` | Trendyol E-Faturam **ayrı bir üründür**, bu dokümanın kapsamı dışında |
| Destek | `entegrasyon@trendyol.com` · **0850 258 58 00** · Seller Center bildirimi |

### 13.4 Topluluk / üçüncü parti — **DÜŞÜK GÜVEN**

Bu bölümdeki hiçbir kaynak resmî değildir. Yalnızca resmî dokümanın sessiz kaldığı yerlerde ipucu olarak kullanılmıştır ve dokümanda ⚠️ ile işaretlenmiştir.

| Kaynak | Ne için kullanıldı | Değerlendirme |
|---|---|---|
| `https://www.zunapro.com/turkey/en/blog/trendyol-integration-guide-api-xml-platform` | Rate limit ve webhook iddiaları | **❌ RESMÎ DOKÜMANLA ÇELİŞİYOR.** "Sipariş 600 req/dk" (gerçek 30–100), "kategori 30 req/dk" (gerçek 50), "webhook 5 deneme üstel backoff, 3 sn içinde 200" (gerçek: sabit 5 dk, sonsuz). **Pazarlama içeriği, gözlem değil. Kullanmayın.** |
| `https://bilgibankasi.akinsoft.net/tr/home/makale/3492` | "TSF PSF'den Büyük olamaz" hata mesajı metni | Makul, doğrulanmadı |
| `https://bilgibankasi.akinsoft.net/tr/home/makale/2075` | Panelde entegrasyon firması seçimi | Ticari listeleme olduğu yorumu bizim (§2.7) |
| `https://www.dopigo.com/trendyol-api-anahtari-nasil-alinir/` | API anahtarı alma adımları | Resmî SSS ile uyumlu |
| `https://yengec.co/blog/trendyol-barkod-rehberi/` | Barkod tekilliği / buybox politikası | Politika iddiası, geliştirici dokümanında yok |
| `https://birfatura.com/trendyol-komisyonu-nedir/` | Komisyonun KDV hariç matrah üzerinden hesaplandığı | **§9.5'teki doğrulanmamış varsayımın tek kaynağı** |
| `https://medium.com/trendyol-tech/optimizing-outbound-stock-synchronization-...` | Platform çapında >100.000 stok güncellemesi/dk | Fetch'e 403 döndü, yalnız arama snippet'i. **Trendyol'un iç kapasitesi, satıcı başına kota DEĞİL** |

### 13.5 Açık kaynak SDK manzarası — **hiçbiri hazır kullanılamaz**

| Repo | Dil | ★ | Durum |
|---|---|---:|---|
| `Trendyol/trendyol-integration-developer-tool` | – | 6 | **Resmî**, Apache-2.0, Ağu 2026. SDK değil, kural seti |
| `boolxy/trendyol` | PHP | 35 | En çok yıldızlı PHP istemci. **Hâlâ `https://api.trendyol.com/sapigw/` hard-code ediyor — Mayıs 2025'te kapatılmış host.** İnce Guzzle sarmalayıcı: 429 yönetimi yok, batch polling yok, dedup yok |
| `Hasokeyk/trendyol-php` | PHP | – | Modern `apigw` host'unu kullanıyor. Yine düz sarmalayıcı — retry/rate-limit/batch mantığı yok |
| `ismail0234/trendyol-php-api`, `mustafa-m-ugur/trendyol-php-api`, `orhanmusellim/trendyol-php-api-class` | PHP | 0–7 | Çeşitli yaşlarda, bakımsız |
| `altuntasmuhammet/trendyol-api-python-sdk` | Python | 15 | "Unofficial", Haz 2026 |
| `myazarc/trendyol-api` | TS | 9 | Karışık `apigw`/`sapigw` referansları — kısmen taşınmış |
| `bberka/TrendyolClient.Sharp` | C# | 8 | Refit tabanlı, Tem 2026 |
| `vahaponur/trendyol-go` | Go | – | Hâlâ sipariş satırında `merchantSku` modelliyor (Nisan 2026 öncesi şema) |

**Mimari kararımız:** **hiçbir topluluk SDK'sı** bu entegrasyonu asıl zorlaştıran üç şeyi uygulamıyor — grup farkındalıklı rate limiting, kalem düzeyi inceleme ile batch polling, ve 15 dakikalık dedup penceresi. Eylül/Ekim 2026 kırıcı değişiklikleri göz önüne alındığında bayat bir SDK kısayol değil, yükümlülüktür.
→ **Kendi ince, tipli istemcimizi yazıyoruz; topluluk SDK'larından *kod* değil, Trendyol eklentisinden *kural* alıyoruz.**

---

## Ek A — Doğrulama listesi (stage'de kapatılacak ⚠️ maddeler)

Aşağıdakiler bu dokümanda **⚠️ doğrulanmadı** ile işaretlenmiştir. Stage entegrasyonunun ilk işi bu listeyi kapatmaktır.

| # | Belirsizlik | Doğrulama yöntemi | Öncelik |
|---:|---|---|---|
| 1 | **Attribute payload şekli** (create vs update; tekil vs çoğul) — §9.6 | Her iki şekli createProducts + content-bulk-update'e gönder, `getBatchRequestResult` echo'sunu incele | **P0** |
| 2 | `variant-bulk-update` **partial update destekliyor mu** — §4.2.4 | Yalnız `barcode` + `stockCode` gönder, sonra `filterApprovedProducts` ile `vatRate`/`dimensionalWeight` korunmuş mu bak | **P0** |
| 3 | `deliveryDuration` semantiği **bugün hangisi** (17 Ağu 2026 mı, hâlâ eski mi) — §12 | `deliveryDuration: 0` gönder, kabul ediliyor mu ve panelde "Bugün Kargoda" görünüyor mu | **P0** |
| 4 | **4 saat sonrası** `getBatchRequestResult` davranışı (404 / boş / 200) — §6.5 | Bir batch at, 4 saat sonra sorgula | **P0** |
| 5 | **429 yanıt başlıkları** (`Retry-After` var mı) — §7.6 | Kasıtlı 429 tetikle, tüm yanıt başlıklarını dök | **P0** |
| 6 | **Webhook başarı kriteri** (hangi HTTP kodu retry'ı durdurur) ve **timeout** — §10.4 | Sırayla 200/202/204/301/500 dönen alıcılar kur, retry davranışını gözle | P1 |
| 7 | `X-Supplier-Id` / `storefrontCode` başlıkları TR ürün-sipariş servislerinde **gerekli mi** — §2.5 | Başlıklarla ve başlıksız istek at, 400/403 farkına bak | P1 |
| 8 | `filterApprovedProducts.status` **`notOnSale` kabul ediyor mu** — §4.3.3 | `?status=notOnSale` ile istek at | P1 |
| 9 | `filterApprovedProducts` yanıtı **`stock.quantity` içeriyor mu** — §4.3.3 | Bilinen stoklu bir ürünü çek | P1 |
| 10 | Manuel teslim/iade **hangi yol doğru** (OpenAPI vs kılavuz) — §4.8.4 | İki yolu da dene | P1 |
| 11 | `claimIds` query parametresi **string UUID kabul ediyor mu** — §4.5.1 | Bilinen bir claim UUID'si ile filtrele | P1 |
| 12 | Fiyatların **KDV dâhil brüt** olduğu — §9.5 | Bilinen `salePrice` + `vatRate` ile sipariş yarat, `lineGrossAmount` ve settlement'ı karşılaştır | P1 |
| 13 | Epoch değerlerinin **gerçek zaman dilimi** (GMT vs GMT+3) — §4.0 | Stage'de bilinen saatte sipariş yarat, `orderDate` ve `packageHistories[].createdDate`'i karşılaştır | P1 |
| 14 | `Repack` statüsü **hâlâ var mı** — §5.1 | `?status=Repack` ile istek at (400 bekleniyor) | P2 |
| 15 | "Kargo Takip Kodu Bildirme" **endpoint'i var mı** — §1.2 | Trendyol desteğe sor | P2 |
| 16 | Pasif webhook penceresinde olaylar **kuyruklanıyor mu** — §10.5 | Webhook'u deaktive et, sipariş statüsü değiştir, aktive et, teslimat gelip gelmediğine bak | P2 |
| 17 | Q&A `size` gerçek tavanı (50 mi 200 mü) — §4.6.1 | `size=200` gönder, yanıttaki `size`'a bak | P2 |
| 18 | `answerQuestion` **yanıt şekli** (string vs `{answerId}`) — §4.6.3 | Bir soruyu cevapla, ham gövdeyi logla | P2 |
| 19 | `getBrands` / filtre endpoint'lerinde **sayfa indeksi 0 mı 1 mi** — §4.1.1, §4.2.9 | `page=0` ve `page=1` sonuçlarını karşılaştır | P2 |
| 20 | `createBrand` idempotency ve dönen `brandId`'nin **hemen kullanılabilirliği** — §4.1.3 | Aynı markayı iki kez yarat; dönen id ile hemen ürün aç | P2 |
| 21 | `deleteProducts` **maksimum item sayısı** — §4.2.8 | 1001 kalem gönder | P2 |
| 22 | `getUpdateAudits` **maksimum `size`** — §4.2.9 | Artan `size` ile dene | P2 |
| 23 | `getCategoryAttributeValues` yanıt alanı (`attributeValue` vs `attributeValueName`) — §4.1.6 | Ham yanıtı incele | P2 |
| 24 | `shipmentPackageStatus` ile `status` **farkı** — §5.2 | Farklı yaşam döngüsü aşamalarında paketleri karşılaştır | P2 |
| 25 | Stage IP allow-list ifadesi ("Statik IP'ler için yetkilendirme sağlanamamaktadır") — §2.8 | Trendyol desteğe netleştirtin | P2 |
| 26 | Giden webhook'lar için **IP aralığı** yayımlanıyor mu — §10.3 | Trendyol desteğe sor | P3 |
| 27 | `VERIFIED` webhook statüsünün **anlamı** — §5.8 | Trendyol desteğe sor | P3 |
| 28 | `cargoProvider` kodu `SENDEOMP` mu `KOLAYGELSINMP` mi — §4.8.1 | `getCargoProviders` çıktısını otorite kabul et | P3 |
| 29 | `Answer.status` **olası değerleri** — §4.6.1 | Cevap yaşam döngüsünü gözle | P3 |
| 30 | `cancelReasonCode` / `cancelledBy` **sözlüğü** — §5.6 | İptal edilmiş siparişleri topla, gözlemlenen değerleri katalogla | P3 |

---

*Bu doküman KobiConnect Trendyol adaptörünün uygulama sözleşmesidir. Trendyol dokümantasyonu `updatedAt` damgalarıyla değişiklik takibine izin verdiği için, `llms.txt` üzerinden haftalık bir diff job'ı kurmak ve bu dokümanı ona göre güncellemek önerilir.*
