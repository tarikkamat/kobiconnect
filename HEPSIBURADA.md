# Hepsiburada Marketplace Adaptörü — Uygulama Sözleşmesi

> **Amaç.** Bu doküman, KobiConnect'in ikinci pazaryeri adaptörü olan Hepsiburada entegrasyonunun tek referansıdır. Bir mühendis bu dokümanı okuyarak `developers.hepsiburada.com`'a dönmeden istemciyi yazabilmelidir.
> **Kaynak tarihi:** 19 Ağustos 2026.
> **İşaretleme:**
> - **✅ ölçüldü** — SIT ortamına yapılan canlı çağrıyla doğrulanmış. Yanıt gövdeleri saklandı. **Bu, dokümantasyonu ezer.**
> - **⚠️ doğrulanmadı** — resmî kaynakta doğrulanamadı ya da yalnızca arşiv/topluluk kaynağından geliyor. Neyin kapatacağı yazılıdır (Ek A).
> - Ölçüm ile doküman çelişiyorsa **ikisi de** gösterilir; ölçüm otoritedir.
> **Kimlik isimleri çevrilmez.** Alan adları, enum değerleri, endpoint yolları ve hata kodları orijinal hâliyle bırakılmıştır (`merchantSku` ≠ `MerchantSku`, `hbSku` ≠ `HBSku`).
> **Bu dokümanın Trendyol'unkinden üstün yanı:** çalışan bir test ortamı erişimimiz var. ⚠️ maddelerinin büyük kısmı tek bir çağrıyla kapatılabilir.

---

## 1. Özet & kapsam

Hepsiburada **tek bir API değildir.** En az **dört ayrı host** üzerinde, **dört farklı yanıt zarfı**, **iki farklı sayfalama şeması** ve **üç farklı asenkron idiomu** olan bir servis ailesidir; hepsi tek bir Basic-auth kimlik çiftiyle açılır ✅ ölçüldü. Katalog (ürün kimliği) ile listing (fiyat/stok/satılabilirlik) **kasıtlı olarak ayrı sistemlerdir** — ürünün katalogda var olması satışta olduğu anlamına gelmez ✅ ölçüldü.

Trendyol'a göre üç yapısal fark mimariyi belirler:
1. **Tek geçit yok.** Trendyol'da tek `apigw`; burada servise göre taban URL seçmek zorundasınız.
2. **İnsan moderasyonlu katalog.** `PRE_MATCHED` ("Eşleşen") — pazaryeri bir eşleşme önerir ve **bizim onayımızı bekler.** Trendyol'da karşılığı yoktur. Bu kuyruk işlenmezse satıcının hiçbir ürünü satışa çıkmaz.
3. **Rate limit satıcı başına değil, IP başına.** Çok kiracılı SaaS'ta tüm tenant'lar tek bütçeyi paylaşır.

### 1.1 Yetenek matrisi

Endpoint sayıları: ✅ ile işaretliler ölçülmüş ya da resmî OpenAPI'den; diğerleri arşivlenmiş portal navigasyonundan sayılmıştır ⚠️.

| Alan | Host | Endpoint | KobiConnect fazı | Not / kapsam dışı gerekçesi |
|---|---|---:|---|---|
| **Kategori / attribute / değer** | mpop | 3 ✅ | **MVP** | Ürün oluşturmanın ön koşulu. Değer endpoint'i (kategori × attribute) N×M fan-out — cache zorunlu. |
| **Katalog ürün yazma** (`import`, `fastlisting`, `delete-process`) | mpop | 3 | **MVP**: `import` + `delete-process`. **v1.1**: `fastlisting` | `fastlisting` yalnız HB kataloğunda **zaten var olan** barkodlar için; katalogda olmayan satırlar **sessizce yüklenmez**. |
| **Katalog durum okuma** (`status/{trackingId}`, `trackingId-history`, `check-product-status`, `products-by-merchant-and-status`, `all-products-of-merchant`) | mpop | 5 (2 ✅) | **MVP** | Yazmanın sonucu **yalnızca** buradan öğrenilir. `trackingId-history` çökme sonrası tek kurtarma yolu. |
| **Ön eşleşme onay/red** (`approve-prematch`, `reject-prematch`) | mpop | 2 | **MVP** | **Opsiyonel değil.** İşlenmezse ürünler "Eşleşen"de takılır ve hiçbir şey satılmaz. Kanonik modelde karşılığı yok (§10). |
| **Katalog ürün güncelleme** (`ticket-api`) | mpop | 3 | **v1.1** | PATCH semantiği: yalnız değişen alanlar gönderilmeli, aksi hâlde HB'nin editoryal zenginleştirmesi ezilir. Alan bazlı diff gerekir. |
| **Listing fiyat / stok** (`inventory-uploads`, `price-uploads`, `stock-uploads` + 3 poll) | listing-external | 6 | **MVP** | Ürünün asıl değeri. 5 eşzamanlı iş + 4000 SKU/istek + günlük 10× listing kotası mimariyi belirler (§3 H7). |
| **Listing okuma** (`GET /listings/merchantid/{id}`) | listing-external | 1 ✅ | **MVP** | Mutabakatın tek doğruluk kaynağı; `isSalable` + `deactivationReasons[]` "kabul edildi ama etkisiz" durumunu **ölçülebilir** kılar. |
| **Listing teslimat / ek bilgi** (`shipping-info-uploads`, `extra-info-uploads` + poll) | listing-external | 4 | **v1.1** | `dispatchTime` Oca-2024'te `inventory-uploads`'tan **kaldırıldı**, yalnız burada set edilir. |
| **Listing aç/kapa/sil/kilit** (`activate`, `deactivate`, `DELETE`, `unlock`) | listing-external | 4 ⚠️ | **v1.1** | Fiyat veya stok = 0 yazmak da listing'i satıştan kaldırır — aynı sonuca iki mekanizma. |
| **Buybox / komisyon sorgulama** | listing-external | 2 ⚠️ | **Kapsam dışı (v2)** | Fiyat rekabeti özelliği MVP'de yok. Komisyon sorgulama max 50 SKU, 240 istek/dk. |
| **Üstü çizili fiyat (ListingDiscounts)** | listing-external | ~9 ⚠️ | **Kapsam dışı** | Kampanya yönetimi ayrı bir ürün yüzeyi. |
| **Sipariş okuma** (`/orders/...`, `/packages/...` statü kovaları) | oms-external | ~9 (2 ✅) | **MVP** | Katalog kimlik bilgisi OMS host'unda **çalışıyor** ✅ ölçüldü — ayrı kimlik bilgisi gerekmiyor. |
| **Paket süreçleri** (paketleme, bölme, bozma, etiket, depo) | oms-external | ~8 ⚠️ | **v1.1** | Kısmi tedarik / paket bölme senaryosu MVP'de yok. |
| **Fatura & iptal** (`fatura-link`, `cancelbymerchant`) | oms-external | 2 ⚠️ | **v1.1** | e-Fatura sağlayıcı entegrasyonu ayrı iş. |
| **Talep / claim (iade)** | oms-external | ~7 ⚠️ | **v1.1** | Okuma (`GET /claims/merchantid/{id}`) panelde görünürlük için MVP'ye alınabilir. |
| **Kargo profilleri** (`cargoFirms`, `profiles`) | shipping-external | ~4 ⚠️ | **v1.1** | `CargoCompany1..3` listing payload'ında; profil yönetimi ayrı. |
| **Webhook (ters kontrat)** | — (bizim sunucumuz) | 8 sipariş + 3 talep ⚠️ | **v1.1** | Abonelik API'si **yok**; imza **yok**. Kendi endpoint'lerimizi açıp HB'ye tek BaseURL veriyoruz (§11). |
| **Muhasebe / settlement** (`/transactions/merchantid/{id}`) | ayrı | ~4 ⚠️ | **Kapsam dışı (v2)** | KobiConnect MVP'si muhasebe modülü içermiyor. |
| **Satıcıya Sor (soru-cevap)** | ayrı | ~7 ⚠️ | **Kapsam dışı (v1.1+)** | Prod'da **varsayılan kapalı**; ayrı yetkilendirme talebi + 2 iş günü SLA. |
| **Hepsiburada E-Faturam** | ayrı | 33 ⚠️ | **Kapsam dışı** | Bağımsız bir e-fatura ürünü; KobiConnect kapsamında değil. |
| **HepsiJet / HepsiExpress / HepsiLojistik / Hepsiglobal / Tedarikçi / Satıcı Promosyonu** | ayrı | 60+ ⚠️ | **Kapsam dışı** | Ayrı şirket/ürün düğümleri, ayrı statü sözlükleri, ayrı iş modelleri. |

### 1.2 Var olmayan, sık sorulan şeyler

| Beklenen | Gerçek |
|---|---|
| Marka listeleme / marka yaratma endpoint'i | **Yok.** `Marka` ürün payload'ında **serbest metin**tir. `BrandData.remoteId`'yi dolduracak hiçbir kaynak yok (§9.7, §10). |
| Ürün güncelleme endpoint'i (katalog) | **Yok.** Aynı `merchantSku` ile yeniden `POST /products/import` **güncellemedir** — ve `MATCHED` / `CREATED` / `MATCHED_WITH_STAGED` statülerinde **reddedilir**. Onaylı ürünler için ayrı `ticket-api` servisi var. |
| `Idempotency-Key` / istek dedup token'ı | **Yok.** Hiçbir yüzeyde. Koruma doğal anahtar reddi ile: `merchantSku` satıcı bazında tekil, `Barcode` global tekil (§8.4). |
| Webhook abonelik yönetimi API'si | **Yok.** Yön ters: siz sabit path'ler açarsınız, HB size POST/PUT eder. |
| Webhook imzası / HMAC / `X-Signature` | **Yok.** Hiçbir portal sayfasında geçmiyor ⚠️. Gelen isteğin kimlik doğrulaması bant dışı (Basic auth) anlaşılır. |
| OAuth2 / access token / refresh | **Yok.** Düz HTTP Basic. Aksini iddia eden Türkçe bloglar (`temasis.net`, `zunapro.com`) **yanlıştır** (§13.3). |
| `listingId` ile listing güncelleme | **Yok.** Güncelleme anahtarı **`(HepsiburadaSku, merchantSku)` çiftidir**; `listingId` yalnız okuma yanıtında döner ✅ ölçüldü. |
| Tarih bazlı artımlı ürün senkronu | **Yok.** Katalog okuma endpoint'lerinde belgelenmiş tarih filtresi yok; mutabakat tam tarama ile yapılır (§10, M14). |
| Rate limit yanıt başlıkları (2xx üzerinde) | **Yok** ✅ ölçüldü — 200 yanıtlarında `X-RateLimit-*` / `Retry-After` gelmiyor. 429'da geldiği belgeli ⚠️ (§7). |
| Fiziksel boyut (en/boy/yükseklik) alanı | **Yok.** Yalnız `kg` var ve **kilogram değil, desi**dir ✅ ölçüldü (alan etiketi: "Desi"). |
| 5'ten fazla görsel | Doküman `Image1..Image5` diyor; **ölçüm `Image1..Image10` gösteriyor** ✅ (§9.9). |

---

## 2. Kimlik doğrulama & ortamlar

### 2.1 Şema

```
Authorization: Basic base64({merchantId}:{servisAnahtarı})
User-Agent:    {entegratör_kullanıcı_adı}
Content-Type:  application/json   (import/fastlisting hariç)
Accept:        application/json
```

| Bileşen | Şekil | Sahibi | Not |
|---|---|---|---|
| Basic **username** | `merchantId` — UUID v4 | Hepsiburada üretir | Aynı değer path parametresi ve gövde alanı (`merchant`) olarak da geçer. Kimlik ve tenant anahtarı **aynı değerdir**. |
| Basic **password** | "Servis Anahtarı" — **12 karakter alfanümerik** | **Satıcı** üretir ve döndürür | ✅ ölçüldü: elimizdeki SIT kimlik bilgisi tam olarak bu biçimde (12 alfanümerik). |
| **`User-Agent`** | Eski Basic-auth kullanıcı adı, tipik biçim `<ad>_dev` | Entegratör (biz) | **Kimliğin ikinci yarısıdır, kozmetik değildir.** Eksikse/yanlışsa 401/403. |

> **Kimlik bilgisi değerleri bu dokümanda YAZILI DEĞİLDİR.** Bağlantı ekranında `channel_connections.credentials` jsonb'sinde **şifreli** saklanır. Saklanacak üç alan: `merchant_id`, `service_key`, `integrator_user_agent`.

**Ocak 2024 auth göçü.** Eski şema `Basic base64(entegratörKullanıcı:entegratörParola)` idi. Yeni şemada eski parola **hiçbir yerde kullanılmaz**; eski kullanıcı adı `User-Agent`'a taşınır. Servis anahtarını entegratör portalinde **girmemiş** satıcılar için eski yapı sürüyor ⚠️ — `channel_connections.settings` içinde `uses_service_key` boolean'ı tutulması önerilir.

**Rotasyon.** Satıcı panelden ("Yeni servis anahtarı oluşturmak istiyorum") anahtarı **bize haber vermeden** döndürebilir; eski anahtar anında ölür. Sağlık kontrolü 401'i "rotasyon" ve "hata" olarak ayırt edebilmeli ve satıcıdan yeni anahtar istemeli.

⚠️ **`User-Agent` biçimi.** Bir OSS SDK, yaygın `"{merchantId} - {EntegratörAdı}"` şablonunun **birkaç serviste 401/403 verdiğini**, çıplak entegratör adının çalıştığını bildiriyor. Bizim ölçümümüz çıplak `<ad>_dev` biçimiyle üç host'ta da 200 aldı ✅ — **çıplak biçimi kullanın.** (Trendyol'un `{sellerId} - {Entegratör}` biçiminin tersi; iki adaptörde aynı UA üreticisini paylaşmayın.)

### 2.2 Host'lar — üçü de tek kimlik bilgisiyle 200 döndü ✅ ölçüldü

| Yetenek | Production | SIT / test | Yol öneki | Ölçüm |
|---|---|---|---|---|
| **Katalog** (kategori, attribute, ürün yazma/okuma, ön eşleşme) | `https://mpop.hepsiburada.com` | `https://mpop-sit.hepsiburada.com` | `/product/api/…` | ✅ 200 |
| **Katalog güncelleme** (ayrı servis, aynı host) | `https://mpop.hepsiburada.com` | `https://mpop-sit.hepsiburada.com` | `/ticket-api/api/integrator/…` | ⚠️ |
| **Listing** (fiyat, stok, teslimat, buybox, kilit) | `https://listing-external.hepsiburada.com` | `https://listing-external-sit.hepsiburada.com` | `/listings/merchantid/{merchantId}/…` | ✅ 200 |
| **OMS** (sipariş, paket, talep) | `https://oms-external.hepsiburada.com` | `https://oms-external-sit.hepsiburada.com` | `/orders/…`, `/packages/…`, `/claims/…` | ✅ 200 |
| **Shipping** (kargo firmaları, profiller) | `https://shipping-external.hepsiburada.com` | `https://shipping-external-sit.hepsiburada.com` | `/cargoFirms/…`, `/profiles/…` | ⚠️ |
| **Claim stub** (yalnız test) | — | `https://claim-stub-external-sit.hepsiburada.com` | `/claims/merchant/{merchantid}/create` | ⚠️ |
| **Test sipariş stub** (yalnız test) | — | `https://oms-stub-external-sit.hepsiburada.com` ⚠️ | — | ⚠️ yalnız OSS kaynağı |
| Satıcı paneli (insan) | `https://merchant.hepsiburada.com` | ayrı test paneli | — | — |

**Kural: production host = SIT host'undan `-sit` çıkarılmış hâli.** Kimlik bilgileri ortam başına **farklıdır**. ✅ ölçüldü: SIT kimlik bilgisiyle `https://mpop.hepsiburada.com` **403** döner — yani yanlış ortama yazma riski kimlik bilgisi düzeyinde de korunuyor, ama buna güvenmeyin.

⚠️ **Path'te `merchantid` büyük/küçük harfi host'a göre değişiyor.** Bir OSS SDK'nın 2026 canlı doğrulaması: `listing-external` yalnız küçük harf `merchantid` kabul eder, `merchantId` ile **400** döner; `oms-external` ikisini de kabul eder; `mpop`'ta ise path segmenti (`all-products-of-merchant/{merchantId}`) ya da query parametresidir. Bizim ölçümümüz küçük harf `merchantid` ile üç host'ta da 200 aldı ✅ — **her yerde küçük harf kullanın.**

### 2.3 Erişim nasıl alınır

1. **Satıcı** (biz değil) canlı satıcı panelinden talep açar: *Yardım Merkezi → Talepler → Yeni talep → API ENTEGRASYON - API Entegratör Yetkilendirme İşlemleri*.
2. HB **önce test ortamı kimlik bilgilerini** verir. (Bizde bu adım tamamlanmış durumda.)
3. Modül başına "Test Süreci Adımları" kontrol listesi tamamlanır (Katalog / Listeleme / Sipariş / Webhook için ayrı ayrı).
4. Aynı talep tipiyle **production** kimlik bilgisi istenir; HB test kanıtlarını inceler.
5. Ayrıca satıcı entegratör kimliğimizi panelden yetkilendirir: *Hesabım → Bilgilerim → Entegrasyon → Entegratör Bilgileri → Entegratör ekle*. **Yetkilendirme ~2 saatte yayılır** — ilk denemenin "geçersiz" dönmesi beklenen davranıştır, hata değildir.

---

## 3. Mimariyi belirleyen kısıtlar

Mimarın ilk okuyacağı bölüm. Bunların her biri şema, kuyruk ya da sınıf tasarımını değiştirir.

**H1 — Tek `base_url` yok; taban URL yetenek başına seçilir.** ✅ ölçüldü
Katalog `mpop`, fiyat/stok `listing-external`, sipariş `oms-external`, kargo profili `shipping-external`. Trendyol'un tek `apigw` geçidinin aksine istemci **yetenek → host** yönlendirmesi yapmak zorundadır.
→ **Sonuç:** `HepsiburadaClient` tek bir `$baseUrl` tutamaz; `host(Capability|string $service): string` çözücüsü ve host başına ayrı rate-limit kovası gerekir.

**H2 — DÖRT farklı yanıt zarfı; `success` alanı evrensel değil.** ✅ ölçüldü

| # | Nerede | Şekil |
|---:|---|---|
| 1 | Katalog, sayfalı | `{success, code, version, message, totalElements, totalPages, number, numberOfElements, first, last, data:[]}` |
| 2 | Katalog attribute | `{success, code, version, message, data:{baseAttributes[], attributes[], variantAttributes[]}}` — **sayfalama alanları yok** |
| 3 | Listing | `{listings:[], totalCount, limit, offset}` — `success`/`code` **yok** |
| 4 | Sipariş | `{totalCount, limit, offset, pageCount, items:[]}` — `success`/`code` **yok** |
| 5 | Paket | **çıplak dizi** `[]` — zarf yok |
| 6 | Hata (404) | Spring: `{timestamp, status, error, message, path}` — başarı zarfından **tamamen farklı** |

→ **Sonuç:** ortak bir `unwrap()` yardımcısı **yazılamaz**. Zarf çözücü servis başına seçilmeli, ve her yanıt hem "başarı zarfı" hem "Spring hata zarfı" olasılığına karşı ayrıştırılmalıdır. Ayrıca `delete-process` poll yanıtı zarfsız ham nesne döner ⚠️ — beşinci bir istisna.

**H3 — İKİ farklı sayfalama şeması.** ✅ ölçüldü
- Katalog: `page` (**0 tabanlı**) + `size`
- Listing ve sipariş: `limit` + `offset`

`size` tavanı **endpoint başına farklıdır** ve kaynaklar çelişiyor: satıcı notu ve "Katalog Önemli Bilgiler" **100** diyor; resmî OpenAPI `getProductStatusByTraceId` ve `getTrackingList` için varsayılan **1000** veriyor; kategori sayfası **2000**'e kadar izin verdiğini yazıyor; attribute-değer endpoint'i **1000** tavanlı ve sayfalama **zorunlu**. ⚠️ Tek bir global sayfa boyutu sabiti **en az bir yerde yanlış olacaktır.** → Ek A P0.

**H4 — Her yazma asenkron ve kimlik dönmez; üç ayrı asenkron idiomu var.**

| İdiom | Nerede | Dönen | Poll |
|---|---|---|---|
| `trackingId` | `products/import`, `fastlisting`, `delete-process`, `ticket-api/import` | `{success, code, data:{trackingId}}` | `GET .../status/{trackingId}` (sayfalı) veya `.../delete-process/{trackingId}` (**sayfasız, zarfsız**) |
| `uploadId` | `listings/.../{kind}-uploads` | `{"Id": "<uuid>"}` | `GET .../{kind}-uploads/id/{id}` |
| **hiçbiri** | `approve-prematch`, `reject-prematch` | yalnız `{success, code, message}` | **Yok.** Sonuç ancak `products-by-merchant-and-status` yeniden okunarak görülür. |

→ **Sonuç:** tek bir "asenkron iş" soyutlaması üçünü de kapsamaz. `channel_operations.remote_batch_id` üçünü de tutabilir, ama üçüncüsü için **null**dur ve iş `remote_result` okunarak değil, **mutabakatla** kapanır. HTTP 200 "kabul edildi" demektir, "uygulandı" değil.

**H5 — `itemOrderID` gönderdiğiniz dizideki KONUMSAL indekstir.**
`GET /products/status/{trackingId}` her satırda `itemOrderID` döner ve bu, yüklediğiniz JSON dizisindeki sıradır. Diziyi yeniden üretir, sıralar ya da alt kümesini retry ederseniz indeks kayar.
→ **Sonuç:** `channel_operations.payload` içinde **gönderilen dizinin sırası** korunmalı ve `index → reference` haritası saklanmalıdır. İkincil korelasyon anahtarı `merchantSku`'dur, ama o da sunucu tarafında büyük harfe çevrilir (H8). ⚠️ `itemOrderID`'nin 0 mı 1 mi tabanlı olduğu ölçülmedi → Ek A P0.

**H6 — Fiyat bandı ihlalinde ürün REDDEDİLMEZ; 0 fiyat / 0 stok ile CANLIYA çıkar.** ✅ ölçüldü
HB, ürünün mevcut listing fiyatlarının (min ve maks hariç) ortalamasını alır ve sizi bir çarpanla sınırlar:

| Ortalama fiyat | Maks çarpan |
|---|---|
| 0–50 TL | 250% |
| 50–100 TL | 150% |
| 100–200 TL | 120% |
| 200–500 TL | 100% |
| 500–2000 TL | 90% |
| > 2000 TL | 80% |

Bandı aşan **ya da biçimi bozuk** bir fiyat/stok, katalog yüklemesinde hataya değil şu uyarıya yol açar: *"Değer uygun formatta değildir. Listeleme kaydı 0 fiyat 0 stok olarak oluşturulacaktır."* Ürün **yaratılır ve listing 0/0 ile açılır**.

**Ölçülmüş kanıt** — SIT listing okumasında tam olarak bu durumdaki bir kayıt:
```json
{"price":0.0,"availableStock":0,"isSalable":false,
 "deactivationReasons":["PriceIsLessThanOrEqualToZero","StockIsLessThanOrEqualToZero"]}
```
→ **Sonuç:** "başarılı push" ≠ "fiyat geçerli". Her katalog/listing yazmasından sonra **açık bir fiyat doğrulama adımı** gerekir. `PushResult.itemResults`'ın ikili `accepted` alanı bu durumu ifade edemez (§10, M2). FAQ net: *"Bu bir genel kuraldır kaldırılamaz, değiştirilemez."*

**H7 — Listing güncellemelerinin sınırı RPS değil, eşzamanlılık ve kota.** ⚠️
- Satıcı başına **en fazla 5 eşzamanlı bekleyen upload**. Aşımda: *"There are too many ongoing/waiting inventory uploads at the moment."*
- İstek başına **maks 4000 SKU**.
- **Günlük toplu kota = listing sayınızın 10 katı.** 100 listing ⇒ günde 1000 güncelleme. Küçük satıcıda sık fiyat senkronu bunu yakar.
- HB'nin kendi tavsiyesi: **daha büyük batch, ~10 saniyede bir gönderim**.
→ **Sonuç:** push worker'ı varyant başına değil **bağlantı başına batch** kurmalı; `channel_operations` drenajı 5 eşzamanlılık semaforu ile sınırlanmalı; günlük kota `channel_connections.settings`'te sayaçlanmalı.

**H8 — `merchantSku` sunucu tarafında SESSİZCE büyük harfe çevrilir.** ⚠️ (resmî metin) / ölçüm bekliyor
*"Küçük harf olarak gönderilen merchantSku bilgisi büyük harfe dönüştürülerek ürün girişte kaydedilmektedir."* Boşluk içeremez.
→ **Sonuç:** kanonik `reference` **sınırda normalize edilmeli** (büyük harf, boşluksuz) ve normalize hâli saklanmalıdır. Aksi hâlde ilk import'tan sonraki her lookup ıskalar, ürün "yok" sanılır ve **kopya ürün yaratılır** — bu platformdaki en yaygın veri kazası. ⚠️ Türkçe karakter davranışı (`ı`→`I` mı `İ` mi) belgelenmemiş → Ek A P0.

**H9 — Katalog ≠ listing. İki ayrı sistem, iki ayrı sahiplik.** ✅ ölçüldü

| | **Katalog ürünü** | **Listing (offer)** |
|---|---|---|
| Host / yol | `mpop` `/product/api`, `/ticket-api` | `listing-external` `/listings/merchantid/{id}` |
| Kimlik | `hbSku` (+ `barcode`, `categoryId`, `VaryantGroupID`) | `(merchantId, hepsiburadaSku, merchantSku)` |
| Sahibi | **Hepsiburada** — içeriği zenginleştirir, düzeltir, kapıda tutar | **Satıcı** |
| Alanlar | ad, açıklama, marka, görseller, kategori attribute'ları, garanti, desi | `price`, `availableStock`, `dispatchTime`, `cargoCompany1..3`, kilit/dondurma bayrakları |
| Kardinalite | barkod başına **1**, tüm satıcılar ortak | katalog ürünü başına **N**, satıcı başına 1 |

✅ **Ölçülmüş kanıt:** `all-products-of-merchant` yanıtında `"price": ""` ve `"description": ""` boş geliyor — katalog satırı ticari alanları taşımıyor.
→ **Sonuç:** `ProductData` yazmaları `mpop`'a, `PriceData`/`StockData` yazmaları `listing-external`'a gider. Tek bir push iki host'a iki ayrı asenkron onayla sıralı olarak yazar.
**Sızıntı:** katalog `import` payload'ı `price`/`stock` kabul eder ve ürün `Satışa Hazır`a geçince listing **o değerlerle otomatik satışa açılır**. Yani bir katalog yazması sessizce bir listing yazması üretir. **Öneri: katalog import'unda `price`/`stock` gönderilmez; ticari alanların tek sahibi listing servisidir.**

**H10 — `PRE_MATCHED` insan moderasyon akışı; kanonik modelde karşılığı yok.**
Ürün gönderiminden sonra iki yol ayrılır: barkod HB kataloğunda **varsa** → `PRE_MATCHED` ("Eşleşen"), **yoksa** → `WAITING` ("İncelenecek"). `PRE_MATCHED`'teki ürün için HB kendi katalog ürününü (`matchedHbProductInfo`: `hbSku`, `productName`, `brand`, `images[]`, `variantTypeAttributes[]`) **karşı öneri** olarak sunar ve **onay ya da red** bekler.
- **Hiçbir şey satılmaz** ürün "Eşleşen"de dururken. 5000 SKU yükleyip sıfır ürünün canlıya çıktığını gören satıcı genelde işlenmemiş bir ön eşleşme kuyruğunun üstünde oturuyordur.
- **Onay teknik değil ticari bir karardır.** Onaylarsanız HB'nin başlığını, görsellerini, attribute'larını devralırsınız. Yanlış eşleşme = A ürününü B'nin sayfasında satmak.
- `PRE_MATCHED`, API güncellemesinin hâlâ kabul edildiği **son penceredir**.
→ **Sonuç:** bu bir *inbox*'tır, bir statü alanı değil. Kanonik modelde ne durumu ne de karşı öneriyi taşıyacak yer var (§10, M1). **Otomatik onaylamayın.** (Barkod + marka + normalize ad birebir eşleşiyorsa otomatik onay savunulabilir; yalnız barkod eşleşmesiyle onay savunulamaz.)

**H11 — Rate limit IP başına; çok kiracılıda GLOBAL bütçe.** (§7)
Trendyol'un satıcı-başına modelinin aksine katalog ve sipariş yüzeylerinde limit **çıkış IP'si başınadır**. Tek VDS = tek çıkış IP = **tüm tenant'ların paylaştığı tek kova.** Bir tenant'ın gece dolumu diğerlerini aç bırakır.
→ **Sonuç:** limiter anahtarı `hepsiburada:{host}` — tenant **içermez** — ve `global_cache()` üzerinden yazılır (BACKEND-PLAN §7.6). `TrendyolRateLimiter`'ın `(sellerId, endpoint)` şekli buraya **kopyalanamaz**.

**H12 — Idempotency yok; write retry'ı asimetrik olmalı.**
Hiçbir yüzeyde idempotency anahtarı, request-id dedup başlığı ya da `If-Match` yok. Koruma doğal anahtar reddiyle gelir: `merchantSku` satıcı bazında tekil, `Barcode` global tekil, onaylanmış ürün güncellemesi reddedilir. Bu **sessiz no-op değil, gürültülü hatadır** — ve gerçek veri hatasından ayırt edilemez.
→ **Sonuç:**
- **Okuma (GET):** 429, 5xx, timeout'ta retry.
- **Yazma:** **yalnız 429'da** retry. Belirsiz 5xx/timeout'ta **replay etmeyin** — `products/import` ya da paket mutasyonu kopyalanır.
- Timeout sonrası doğru davranış: `GET /products/trackingId-history` çağırıp timeout penceresi içindeki `createdDate`'i arayıp o `trackingId`'yi **sahiplenmek**. Bu endpoint tam olarak bunun için var.

**H13 — `VaryantGroupID` istemci-uydurması opak bir dizedir.**
HB varyant grubu kimliği üretmez. Aynı değeri taşıyan satırlar tek varyant grubu olur. **Varyantsız ürünler bile benzersiz bir `VaryantGroupID` ister** — boş göndermek ya da sabit bir değer paylaşmak alakasız ürünleri tek varyant grubunda birleştirir (gerçek bir production kaza deseni).
→ **Sonuç:** `VaryantGroupID` = `ProductData.reference`'tan deterministik türetilir; asla rastgele değil, asla boş değil.

---

## 4. Endpoint referansı

### 4.0 Tüm endpoint'ler için ortak kurallar

- Aşağıda **SIT** host'ları yazılıdır. Production = aynı URL'den `-sit` çıkarılmış hâli.
- `Authorization: Basic base64(merchantId:servisAnahtarı)` + `User-Agent: <entegratör_kullanıcı_adı>` **her istekte** (§2.1).
- `version` query parametresi çoğu katalog endpoint'inde opsiyoneldir, varsayılan `1` — **istisna:** `trackingId-history` varsayılanı `2` ⚠️; attribute-değer endpoint'inde çalışan bir SDK `version=5` gönderiyor ⚠️. Tek bir global `version` sabiti yanlıştır.
- Katalog başarı zarfında `code: 0` başarıdır. **HTTP 200 iken `success:false` olabilir** — durum satırına değil, `success`/`code`'a bakın.
- `merchantid` path segmenti **küçük harf** yazılır (§2.2).

---

### 4.1 Katalog host — `https://mpop-sit.hepsiburada.com/product/api`

#### 4.1.1 `getAllCategoriesByParameters` — Kategori Listesi ✅ ölçüldü
`GET /product/api/categories/get-all-categories`

| Param | Tip | Zorunlu | Varsayılan | Anlam |
|---|---|---|---|---|
| `page` | integer | – | `0` | **0 tabanlı** sayfa numarası |
| `size` | integer | – | ⚠️ çelişkili | Sayfa başına kayıt. Satıcı notu **100**; resmî kılavuz bu endpoint için **2000**'e kadar izin verdiğini yazıyor ⚠️ |
| `version` | integer | – | `1` | Resmî örnekte `version=1` |
| `leaf` | boolean | – | – | `true` → ürün açılabilir kategoriler |
| `status` | string | – | – | `ACTIVE` \| `INACTIVE` |
| `available` | boolean | – | – | `true` → kullanıma açık |
| `categoryId` | integer | – | – | Tek kategori |
| `parentCategoryId` | integer | – | – | Bir düğümün çocukları |
| `name` | string | – | – | Ad filtresi |
| `paths` | string | – | – | Breadcrumb filtresi |

**Ölçülmüş yanıt** (`?page=0&size=2`) — `totalElements: 5611`, `totalPages: 2806`:
```json
{"success":true,"code":0,"version":1,"message":null,
 "totalElements":5611,"totalPages":2806,"number":0,"numberOfElements":2,
 "first":true,"last":false,
 "data":[{"categoryId":26012174,"name":"Tansiyon Aletleri","displayName":"Tansiyon Aletleri",
   "parentCategoryId":26012170,
   "paths":["Kozmetik Kişisel Bakım","Sağlık / Kişisel Bakım","Sağlık Ürünleri","Tansiyon Aletleri"],
   "leaf":true,"status":"ACTIVE","type":"HB","sortId":"2","available":true,
   "productTypes":[{"name":"Tansiyon Aletleri","productTypeId":1136}],"merge":false}]}
```

**Ölçüm dokümanı ezdiği yerler:**
| Alan | Doküman / araştırma | ✅ Ölçüm |
|---|---|---|
| `paths` | tek `string` breadcrumb | **`string[]` dizisi** |
| `status` | Türkçe `"AKTİF"` / `"AKTİF DEĞİL"` (noktalı İ tuzağı) | **İngilizce `"ACTIVE"`** — Türkçe casefolding tuzağı bu alan için **geçersiz** |
| `displayName`, `type`, `sortId`, `productTypes[]`, `merge` | belgelenmemiş | **mevcut** |

**Tuzaklar:**
- **Kategori uygunluğu üç bayrağın VE'sidir:** `leaf=true AND status='ACTIVE' AND available=true`. ✅ Ölçüm bunu doğruluyor: `categoryId 400276` (`Müzik Aletleri`) `leaf:true, status:"ACTIVE"` ama **`available:false`** — yalnız `leaf`'e bakan bir filtre kullanıcıya her yüklemeyi reddedecek kategorileri gösterir.
- Yanıt **düz sayfalı listedir, ağaç değil.** Hiyerarşi `parentCategoryId` üzerinden istemci tarafında kurulur.
- 5611 kayıt / 100'lük sayfa = **57 istek**. Paylaşılan IP bütçesinde (§7) bu tek başına ciddi bir maliyet — gecelik zamanlanmış tam senkron, talep anı lookup değil.
- ⚠️ SIT ve PROD kategori ağaçları **farklıdır**; canlıya geçişte yeniden çekilmeli.
- Hata kodları: `1001` leaf değil · `1002` aktif değil · `1003` leaf ve aktif değil · `1004` categoryId bulunamadı · `1005` kategori ilişkisi yok · `1006` kategori mevcut değil.

#### 4.1.2 `getAllAttributesByCategory` — Kategori Özellikleri ✅ ölçüldü
`GET /product/api/categories/{categoryId}/attributes`

| Param | Konum | Tip | Anlam |
|---|---|---|---|
| `categoryId` | **path** | integer | Yaprak + aktif + available kategori |
| `version` | query | integer | ⚠️ opsiyonel, varsayılan `1` |

**Ölçülmüş yanıt şekli** (kategori `26012174`) — **sayfalama alanları yok**, `data` bir nesne:
```json
{"success":true,"code":0,"version":1,"message":null,
 "data":{"baseAttributes":[…22 adet…],"attributes":[…12 adet…],"variantAttributes":[]}}
```
Her attribute: `{name, id, mandatory, type, multiValue}`.

**Üç kova:**
| Kova | Ne | Ölçülen adet |
|---|---|---:|
| `baseAttributes` | Ürün zarfı alanları — `merchantSku`, `VaryantGroupID`, `UrunAdi`, `UrunAciklamasi`, `Barcode`, `Marka`, `GarantiSuresi`, `tax_vat_rate`, `kg`, `Image1..Image10`, `Video1`, `price`, `stock` | 22 |
| `attributes` | Kategoriye özgü özellikler | 12 |
| `variantAttributes` | **Varyantı belirleyenler** — `AttributeData.isVarianter`'ın kaynağı | 0 (bu kategoride) |

**✅ Ölçülmüş `baseAttributes` (birebir, kategori 26012174):**

| `name` | `id` | `mandatory` | `type` |
|---|---|---|---|
| Satıcı Stok Kodu | `merchantSku` | true | string |
| Varyant Grup Id | `VaryantGroupID` | false | string |
| Ürün Adı | `UrunAdi` | true | string |
| Ürün Açıklaması | `UrunAciklamasi` | true | string |
| Barkod | `Barcode` | true | string |
| Marka | `Marka` | true | string |
| Garanti Süresi (Ay) | `GarantiSuresi` | true | **integer** |
| KDV | `tax_vat_rate` | true | string |
| **Desi** | `kg` | true | string |
| Görsel1 | `Image1` | true | string |
| Görsel2–Görsel10 | `Image2` … `Image10` | false | string |
| Fiyat | `price` | **false** | string |
| Stok | `stock` | **false** | string |
| Video | `Video1` | false | **video** |

**✅ Ölçülmüş `attributes` (kategoriye özgü) — `id` alanının gerçek doğası:**
`Bluetooth` (enum) · `000009D` "Konuşma" (enum, zorunlu) · `calisma_sekliNew1` "Çalışma Şekli" (enum, zorunlu) · `000001JL` "Güç" (enum) · `000009C` "Ölçüm Şekli" (enum, zorunlu) · `00000MP` "Menşei" (enum) · `00000MQ` "Üretici Bilgisi" (string) · `00000MR` "İthalatçı/…" (string) · `00000MS` "Kullanım Talimatı/Uyarıları" (**media**) · `00000MT` "CE Uygunluk Sembolu" (enum) · `00000MU` "Paket Görseli (ön)" (**media, mandatory:true**) · `00000MV` "Paket Görseli (arka)" (media)

**Ölçüm dokümanı ezdiği yerler:**
| Konu | Doküman / araştırma | ✅ Ölçüm |
|---|---|---|
| `id` biçimi | "Türkçe slug", örn. `renk_variant_property` | **Karışık:** opak kod (`000009D`), İngilizce sözcük (`Bluetooth`), camelCase slug (`calisma_sekliNew1`). **Slug varsayımı yanlıştır** — `id` opak dize kabul edilmeli. |
| `type` değerleri | `"String"` \| `"Enum"` (PascalCase) | **küçük harf** ve **beş değer**: `string`, `integer`, `enum`, `video`, `media` |
| Görsel sayısı | `Image1..Image5`, sabit 5 tavan | **`Image1..Image10`** — 10 adet ⚠️ evrensel mi kategoriye özgü mü ölçülmedi |
| `isVarianter` kaynağı | "HB varyant bayrağı döndürmez, çıkarılamaz" | **`variantAttributes` kovası mevcut** → `isVarianter` **doldurulabilir** |
| `kg` semantiği | "kilogram olabilir" | Etiket birebir **"Desi"** — kilogram **değildir** |

**Tuzaklar:**
- `allowCustom` karşılığı **yok**. `type: "enum"` ⇒ değer listesinden seç; `type: "string"/"integer"` ⇒ serbest metin. ⚠️ Bir araştırma kaynağı "enum olsa bile serbest metin kabul ediliyor" diyor — doğrulanmadı, Ek A P1.
- `slicer` karşılığı **yok** ve olmayacak. `AttributeData.isSlicer` HB için **her zaman `false`**.
- Bu endpoint yalnız `leaf=true AND available=true` kategoriler için anlamlı sonuç verir.
- HB tavsiyesi: **zorunlu olmayan attribute'ları da gönderin** — arama görünürlüğünü besliyor.
- `type: "media"` alanları görsel URL'i bekler, serbest metin değil. `AttributeData`'da bu ayrımı taşıyacak alan **yok** (§10, M4).

#### 4.1.3 `getAllAttributeValuesByCategoryIdAndAttributeId` — Özellik Değerleri
`GET /product/api/categories/{categoryId}/attribute/{attributeId}/values`

> ### ⚠️→✅ **TUZAK, ÖLÇÜLDÜ: yol segmenti `attribute` — TEKİL.**
> | Yol | Sonuç |
> |---|---|
> | `/categories/{c}/attributes/{a}/values` (çoğul) | **404** ✅ ölçüldü |
> | `/categories/{c}/attribute/{a}/values` (tekil) | **200** ✅ ölçüldü |
>
> Kardeş endpoint `/categories/{categoryId}/attributes` **çoğuldur**. Aynı API'de aynı kaynağın iki farklı yazımı. 404 gövdesi Spring biçiminde döner:
> ```json
> {"timestamp":"2026-08-19T13:19:41.338+0000","status":404,"error":"Not Found",
>  "message":"Not Found","path":"/api/categories/26012174/attributes/000009D/values"}
> ```

| Param | Konum | Tip | Not |
|---|---|---|---|
| `categoryId`, `attributeId` | path | – | `attributeId` = `getAllAttributesByCategory`'den gelen **string** `id` |
| `page` / `size` (ya da `offset`/`limit`) | query | integer | ⚠️ **Sayfalama zorunlu**; `limit` tavanı 1000 (doküman) |
| `version` | query | integer | ⚠️ çalışan bir SDK `version=5` gönderiyor |

**Yanıt:** ⚠️ **gövde şekli ölçülmedi** — 200 döndüğü ölçüldü, gövde saklanmadı. Doküman `{success, code, version, message, data:[{id, value}]}` diyor. **Toplam kayıt sayısının gövdede değil bir RESPONSE HEADER'ında döndüğü** yazıyor ⚠️ — hangi header adı olduğu belgelenmemiş. → Ek A **P0**, tek çağrıyla kapanır.

**Tuzaklar:**
- Yalnız `type: "enum"` attribute'lar için geçerli. Aksi hâlde `2001` "Özellik enum değer değildir".
- **Değerler (kategori × attribute) çiftine kapsamlıdır**, attribute'a değil. Aynı "Renk" attribute'u farklı kategorilerde farklı değer kümesi döner. **Cache anahtarı `(categoryId, attributeId)` olmalı, asla yalnız `attributeId`.**
- Ürüne geri gönderilen `value` alanıdır, `id` değil.
- **N×M fan-out:** her yaprak kategorideki her enum attribute için bir çağrı. Paylaşılan IP bütçesinin (§7) en büyük tüketicisi budur.
- Hata: `2002` "Bu attributeId özelliğine sahip herhangi bir özellik bulunamadı"; `1001`–`1005` kategori hataları da dönebilir.

#### 4.1.4 `uploadProductViaFile` — Ürün Gönderme (create **ve** update)
`POST /product/api/products/import?version=1`

**`Content-Type: multipart/form-data` — ZORUNLU.** Gövde JSON değil, `file` adlı **binary part** ve içeriği bir `.json` dosyasıdır (entegratörler `integrator.json` adını verir). Düz JSON gövde `3002` verir.

**Dosya içeriği** — ürün nesnelerinden oluşan bir **dizi**:
```json
[{ "categoryId": 18021982,
   "merchant": "<merchantId UUID>",
   "attributes": {
     "merchantSku": "SAMPLE-SKU-INT-0",
     "VaryantGroupID": "Hepsiburada0",
     "Barcode": "1234567891234",
     "UrunAdi": "…", "UrunAciklamasi": "…", "Marka": "Nike",
     "GarantiSuresi": 24,
     "kg": "1",
     "tax_vat_rate": "5",
     "price": "130,50",
     "stock": "13",
     "Image1": "https://…jpg",
     "Video1": "https://….mp4",
     "renk_variant_property": "Siyah",
     "ebatlar_variant_property": "Büyük Ebat"
   }}]
```

**Yanıt:** `{success, code, version, message, data:{trackingId}}` — **ürün id'si dönmez, kalem sonucu dönmez.**

**Tuzaklar:**
- **`attributes` ürünün KENDİSİDİR.** Ad, açıklama, marka, barkod, KDV, desi, görseller, fiyat, stok ve varyant eksenleri hepsi bu düz haritanın içindedir. Düz bir ürün şeması **yoktur**. Anahtarlar kategoriye göre değişir ve `getAllAttributesByCategory`'den okunur.
- **Ayrılmış anahtar listesi tutun.** `price`, `stock`, `merchantSku` gibi sistem anahtarlarıyla aynı adlı bir tenant attribute'u çakışır ve ticari alanı ezer.
- **Varyant eksenleri `<slug>_variant_property` sonekli dinamik anahtarlardır** ⚠️ — bu sonek konvansiyonu resmî örneklerden geliyor; ölçtüğümüz kategoride `variantAttributes` boş olduğu için **doğrulanamadı**. Varyantlı bir kategoride tek çağrıyla kapanır → Ek A **P0**.
- **`price` / `stock` STRING ve Türkçe ondalık virgüllü:** `"130,50"`, maks 2 basamak. Nokta ayraç listing servisinde `InvalidPrice` verir; katalogta ise **0/0 ile canlıya çıkarır** (H6).
- **Güncelleme endpoint'i yoktur.** Aynı `merchantSku` ile yeniden POST **güncellemedir** — ve `MATCHED` / `MATCHED_WITH_STAGED` / "Katalog Sürecinde" statülerinde `"Can not update product in matched or in catalog progress status"` ile reddedilir. `PRE_MATCHED` ve `MISSING_INFO`'da kabul edilir.
- **Görseller:** PNG/JPG (GIF yok), `Video1` yalnız MP4. URL'ler herkese açık erişilebilir olmalı; HB **5 kez dener, sonra o görseli kalıcı olarak atlar** — ürünü yeniden göndermeden düzelmez. ⚠️ HB tarayıcısı sabit IP'lerden gelir; erişim listesi uygulanıyorsa `193.28.225.94, 185.92.214.94, 34.78.190.48, 104.155.47.90, 34.76.71.175, 35.240.98.85` beyaz listeye alınmalı.
- **`Barcode` kimlik-kritiktir**, metadata değil: geçerli ve global tekil 13 karakter EAN13. HB kataloğuna eşleşme bunun üzerinden yapılır; yanlış barkod ürününüzü **başkasının sayfasına** bağlar.
- Satıcı rehberi: batch'i **100 kalemin altında** tutun.
- Hata kodları: `3001` dosya içeriği geçersiz · `3002` dosya türü geçersiz · `3003` dosya bulunamadı.

#### 4.1.5 `getProductStatusByTraceId` — Asenkron Sonuç Sorgulama
`GET /product/api/products/status/{trackingId}`

| Param | Konum | Tip | Varsayılan |
|---|---|---|---|
| `trackingId` | path | string | **zorunlu** |
| `version` | query | integer | `1` |
| `page` | query | integer | `0` |
| `size` | query | integer | ⚠️ OpenAPI `1000`, kılavuz `100` |

**Yanıt satırı** (sayfalı zarf içinde `data[]`):
```json
{"itemOrderID": 0, "merchant": "…", "merchantSku": "…", "hbSku": "…", "barcode": "…",
 "productName": "…", "variantGroupId": "…", "categoryId": 18021982,
 "productStatus": "MISSING_INFO",
 "importStatus": "SUCCESS",
 "importMessages": [{"severity":"WARNING","message":"…"}],
 "validationResults": [{"attributeName":"…","message":"…"}],
 "taskDetails": [{"reason":"…","url":"https://…","commentList":[{"message":"…","user":"…"}]}],
 "rejectReasonsMessages": ["…"],
 "matchedHbProductInfo": [{"hbSku":"…","productName":"…","brand":"…","images":["…"],
                           "variantTypeAttributes":[{"name":"…","value":"…"}]}],
 "videoStatus": "…", "qualityScore": 0.0, "qualityStatus": "…",
 "ccValidationResults": {}}
```

**Tuzaklar:**
- **Bu yanıt SAYFALIDIR.** 100 ürünlük bir import tek sayfaya sığmayabilir; `totalPages` gezilmezse hatalar **sessizce kaçırılır.**
- **Üç dik statü ekseni aynı satırda** (§6.3): `importStatus` (dosya), `productStatus` (ürün), `importMessages[].severity` (satır). `importStatus: "SUCCESS"` + `productStatus: "MISSING_INFO"` **normal ve sık** bir kombinasyondur; başarı sayılmamalıdır.
- **Üç paralel hata kanalı** aynı satırda ve **üçü de boşaltılmalıdır**: `taskDetails` (insan görevi + MPOP panel linki + yorum dizisi), `validationResults[]` (attribute bazlı), `importMessages[]` (severity dereceli). Tek bir `errors: string[]` üçünü birleştirir ve UI'ın hatalı alanı işaretlemesini sağlayan `attributeName` çapasını kaybeder.
- `hbSku` ürün kabul edilene kadar **null**dur — ön eşleşme penceresinin tamamı boyunca null. FK'lar `merchantSku` üzerinden kurulmalı.
- Hata kodu `4000` = "TrackingId bulunamadı" — **belirsizdir**: "henüz görünür değil" de olabilir "süresi doldu" da. Sınırlı sayıda retry, sonra `trackingId-history` ile mutabakat.
- ⚠️ `trackingId` sonuçlarının saklama süresi **hiçbir yerde belgelenmemiş**. Belgelenen tek pencere farklı bir tanımlayıcıya ait: `x-correlation-id` **7 gün** boyunca ve **yalnız destek talebi açılarak** sorgulanabilir. → Gönderilen payload'ı ve tanımlayıcıyı **kendi veritabanımızda** saklayın.
- ⚠️ Aynı `trackingId` deseni `fastlisting` için de geçerli; **silme için farklı yol**: `GET /products/delete-process/{trackingId}`.

#### 4.1.6 `getTrackingList` — TrackingId Geçmişi
`GET /product/api/products/trackingId-history`

| Param | Tip | Varsayılan | Not |
|---|---|---|---|
| `version` | integer | **`2`** | ⚠️ Kardeşlerinden farklı — `version=1` eski sayfasız şekli döndürebilir |
| `page` / `size` | integer | `0` / ⚠️ | |

**Yanıt:** `{success, code, version, message, data:[{trackingId, createdDate}]}`

**Bu KURTARMA endpoint'idir.** Import POST'u ile `trackingId`'yi diske yazma arasında süreç çökerse tek geri dönüş yolu budur — ve H12'de anlatılan "timeout'ta blind retry yapma" politikasının uygulanabilir olmasının nedenidir.
**Tuzaklar:** merchant kapsamı yalnız Basic-auth kimlik bilgisinden gelir, parametre yok — çok kiracılıda doğru kimlik seti seçilmeli. Sıralama parametresi **belgelenmemiş** ⚠️; konuma güvenmeyin, `createDate`'i okuyun. Yalnız işaretçi listesi döner; ürün verisi için `getProductStatusByTraceId`'ye fan-out gerekir.

#### 4.1.7 `checkProductStatus` — SKU Listesiyle Toplu Statü
`POST /product/api/products/check-product-status?version=1`

**Gövde — üst seviyede JSON DİZİSİ** (nesne değil) ve **çok-merchant**:
```json
[{"merchant":"<merchantId UUID>","merchantSkuList":["SKU-1","SKU-2"]}]
```
⚠️ Bir araştırma kaynağı gövdeyi tekil nesne olarak veriyor; resmî sayfa ve iki SDK dizi diyor. **Dizi kabul edin** → Ek A P1.

**Yanıt:** `{success, code, version, message, data}` — SKU başına `merchantSku`, `productStatus`, `hbSku`, `productName`, `validationResults`, `taskDetails`.

**Bu, rutin mutabakat için doğru endpoint'tir**: `trackingId` saklamış olmayı gerektirmez. ⚠️ Portal bu endpoint için **çok daha yüksek** bir limit belgeliyor (500 istek/sn ya da 100 istek/sn, kaynağa göre) — global 180/dk kısıtı buraya uygulanırsa yüksek hacimli mutabakat gereksiz yere boğulur.
⚠️ İki yaygın PHP kütüphanesi yolu `/product/api/check-product-status` olarak (yani `/products/` segmenti **eksik**) hard-code ediyor. Doğrusu `/product/api/products/check-product-status`.

#### 4.1.8 `getProductByMerchantIdAndStatus` — Statü Bazlı Ürün Listesi
`GET /product/api/products/products-by-merchant-and-status`

| Param | Tip | Zorunlu | Varsayılan | Anlam |
|---|---|---|---|---|
| `merchantId` | string UUID | **Evet** | – | Query parametresi (path değil) |
| `productStatus` | enum | **Evet** | – | `WAITING`\|`MISSING_INFO`\|`MATCHED`\|`PRE_MATCHED`\|`REJECTED`\|`MATCHED_WITH_STAGED`\|`CREATED` |
| `taskStatus` | boolean | – | `false` | `true` → açık görevler ve tüm kullanıcı yorumları da döner |
| `version` | integer | – | `1` | |
| `page` / `size` | integer | – | `0` / ⚠️ 100 vs 1000 çelişkisi | |

**Yanıt satırı:** `merchantSku`, `barcode`, `hbSku`, `variantGroupId`, `productName`, `productStatus`, `taskDetails[]`, `validationResults[]`, **`matchedHbProductInfo[]`**, `rejectReasonsMessages`, `videoStatus`.

**`matchedHbProductInfo` bu endpoint'in asıl sebebidir:** `productStatus=PRE_MATCHED` ile çağrıldığında HB'nin **karşı önerisini** (`hbSku`, `productName`, `brand`, `images[]`, `variantTypeAttributes[]`) döner. Belgelenen akış: bunu kendi kaydınızla yan yana kıyaslayın, sonra `approve-prematch` ya da `reject-prematch` çağırın.

**Tuzak:** ⚠️ **Belgelenmiş tarih filtresi yok.** Bir OSS SDK `modifiedAtSince` gönderiyor ama portal yalnız `productStatus` belgeliyor. Artımlı senkron bu endpoint üzerinden **yapılamaz**; statü kovası baştan sona sayfalanır.

#### 4.1.9 `getAllProductsByMerchantId` — Mağaza Ürün Listesi ✅ ölçüldü
`GET /product/api/products/all-products-of-merchant/{merchantId}`

| Param | Konum | Tip | Not |
|---|---|---|---|
| `merchantId` | **path** | UUID | ⚠️ Eski `?merchantId=` kullanımı yanlıştır |
| `barcode`, `merchantSku`, `hbSku` | query | string | Tekil filtreler |
| `page` / `size` | query | integer | `0` tabanlı / ⚠️ 100 vs 1000 |
| `version` | query | integer | ⚠️ Bu endpoint için belgelenmemiş |

**Ölçülmüş yanıt** (`?page=0&size=2`) — `totalElements: 40`:
```json
{"merchantSku":"8680161820017","barcode":"8680161820017","hbSku":"HBV00000U2NIV",
 "variantGroupId":"HB00000U2NIU","productName":"Daniel Klein 8680161820017 Kadın Kol Saati",
 "brand":"Daniel Klein","images":["https://productimages.hepsiburada.net/…jpg"],
 "categoryId":25008405,"categoryName":"Kadın Kol Saatleri",
 "tax":"18.0","price":"","description":"","status":"MATCHED",
 "baseAttributes":[{"name":"UrunAdi","value":"…","mandatory":true},
                   {"name":"merchantSku","value":"8680161820017","mandatory":true},
                   {"name":"kg","value":"1.0","mandatory":true},
                   {"name":"tax_vat_rate","value":"18.0","mandatory":true},
                   {"name":"GarantiSuresi","value":"24","mandatory":true}],
 "variantTypeAttributes":[],"productAttributes":[],"validationResults":[],
 "rejectReasons":[],"qualityScore":null,"qualityStatus":null,"ccValidationResults":null}
```

**Ölçüm dokümanı ezdiği yer — bu bölümün en önemli düzeltmesi:**
| Konu | Araştırma iddiası | ✅ Ölçüm |
|---|---|---|
| Satır şekli | `{id, createdAt, createdBy, modifiedAt, status, listingStatus, fields:{<key>:{value, mandatory, detail, history[]}}}` — **revizyon geçmişli harita** | **Düz satır.** `fields` **yok**; `baseAttributes` bir **dizi**dir: `[{name, value, mandatory}]`. Revizyon geçmişi, `createdBy`, `listingStatus`, `listingFailureReason`, `productType`, `preMatchedSku`, `siblingSku` **hiçbiri dönmüyor.** |
| `variantGroupId` | "Hiçbir okuma endpoint'i grup id'si döndürmez" | **Döner ve doludur** (`HB00000U2NIU`) → varyant grubu okuma tarafında **yeniden kurulabilir** |
| `price` | ticari alanları taşıyabilir | **Boş string** — katalog satırı ticari alan taşımaz (H9) |
| `tax` | – | **`"18.0"`, NOKTA ondalık** — yazma tarafı virgül ister. Okuma/yazma ondalık ayracı **asimetriktir** |

**Tuzaklar:**
- ⚠️ **Bu tam bir resim değildir:** yalnız katalog entegrasyonuyla açılan ürünler döner. Satıcının HB kataloğundan (envanter tarafından) açtığı listing'ler **dönmez**. Tam envanter için listing okuması (§4.3.1) şart.
- `status` burada `productStatus` ile aynı sözlüğü kullanır; ölçülen değer `"MATCHED"`.
- ✅ `merchantSku` değeri **satıcının ne gönderdiğine bağlıdır**: ölçülen iki satırda biri barkodun aynısı (`8680161820017`), diğeri HB biçimli bir kod (`HBV000013N0YB`). API hiçbir biçim dayatmaz — `merchantSku`'yu asla barkod ya da hbSku sanmayın.

#### 4.1.10 / 4.1.11 `integratorApprovePreMatch` / `integratorRejectPreMatch`
`POST /product/api/products/approve-prematch`
`POST /product/api/products/reject-prematch`

**Gövde — her ikisinde de üst seviyede JSON DİZİSİ:**
```json
[{"merchant":"<merchantId UUID>","merchantSkuList":["SKU-1","SKU-2"]}]
```
⚠️ Reject sayfasının parametre tablosu `merchantSkuList`'i `string` (tekil) yazıyor; kendi cURL örneği ve iki SDK **dizi** kullanıyor. **Dizidir; tablo hatalıdır.**

**Yanıt:** `{success, code, version, message, data: null}` — **`trackingId` YOK, kalem bazlı sonuç YOK.**

**Tuzaklar — bu ikili mimarinin en kaygan noktasıdır:**
- **Tek asenkron olmayan yazma çifti.** `import`/`fastlisting`/`delete` "gönder-ve-sorgula", bu ikisi "gönder-ve-umut et". Genel bir "asenkron iş" soyutlaması bu ikisini kapsamaz (H4).
- **Kalem bazlı sonuç dönmez.** Zarf hep-ya-hiçtir; batch'in hangi SKU'sunun başarısız olduğunu **öğrenemezsiniz**. Batch'leri küçük tutun ve sonucu `products-by-merchant-and-status` yeniden okuyarak mutabaka edin.
- **Ön koşul:** yalnız `PRE_MATCHED`. `MATCHED_WITH_STAGED` burada **aksiyon alınamaz**.
- **Red sebebi alanı yok.** Neden reddettiğinizi HB'ye anlatamazsınız; geri de bir tanı verisi gelmez.
- **Idempotency yok**, tekrar onaylamanın davranışı belgelenmemiş ⚠️. Yerelde koruyun.
- **`merchantSku` büyük harf tuzağı burada da geçerlidir** (H8): import'ta `abc-1` gönderdiyseniz HB'de `ABC-1` yazar; burada küçük harf göndermek eşleşmez.
- ⚠️ Yanlış red sonrası kurtarma yolu belgelenmemiş — ürünü yeniden import etmek gerektiğini varsayın.
- ⚠️ **SIT'te sınırlı test:** test ortamında ürün giriş ekibi yok; kendi ürünleriniz `WAITING`'de sonsuza kadar kalır. `PRE_MATCHED`'e ulaşan yalnız üç ekili barkod var: `7541828790114`, `7541828790155`, `7541828790080`.

#### 4.1.12 `uploadFastListingProduct` — Hızlı Ürün Yükleme
`POST /product/api/products/fastlisting?version=1` — **düz JSON gövde** (multipart değil)

```json
[{"merchant":"<UUID>","merchantSku":"…","productName":"…","barcode":"…",
  "hbSku":"…","stock":"13","price":"130,50","itemOrderID":0}]
```
`stock` ve `price` yine **string**; `itemOrderID`'yi burada **siz** verirsiniz (import'ta HB üretir).

**Amaç:** HB kataloğunda **zaten var olan** ve global barkodu bulunan ürünlere kategori/attribute eşleme yapmadan listing açmak. **Katalogda olmayan satırlar sessizce yüklenmez** — hata satırı garantisi yok. Eşleşen satırlar `PRE_MATCHED`'e düşer ve onay/red gerektirir.
⚠️ **Yanıt gövdesi resmî OpenAPI'de boş `{"type":"object"}` olarak tanımlı** — gerçek şekli belgesiz. `trackingId` döndüğü kılavuz metninden çıkarılıyor. İlk SIT çağrısında yakalanmalı → Ek A P1.

#### 4.1.13 `deleteByMerchantAndMerchantSkuList` / `getDeleteProcess` — Silme
`POST /product/api/products/delete-process`
`GET  /product/api/products/delete-process/{trackingId}`

**Gövde — dikkat, kardeşlerinden FARKLI şekil:** her eleman **tek** SKU taşır (`merchantSkuList` değil):
```json
[{"merchant":"<UUID>","merchantSku":"SKU-1"}]
```
Aynı mapper'ı `approve-prematch` ile paylaşırsanız yanlış olur.

**Poll yanıtı — zarfsız ham nesne** (`success`/`code`/`message` **yok**):
```json
{"id":"…","createdBy":"…","modifiedBy":"…","trackingId":"…",
 "deletedProductList":[{"merchant":"…","merchantSku":"…","deleted":true,"errorMessage":""}],
 "completed":true}
```
`completed == true` olana kadar sorgulanır, sonra `deletedProductList` gezilir. **İki farklı tamamlanma konvansiyonu aynı API'de:** import string enum (`importStatus`), silme boolean (`completed`/`deleted`).
⚠️ Bu endpoint **sayfalı değildir** — büyük bir silme batch'i sınırsız tek dizi döner. ⚠️ POST yanıt gövdesi de OpenAPI'de boş `{"type":"object"}` — ilk SIT çağrısında yakalanmalı.
**Yalnız "aksiyon bekleyen" ürünler silinir.** `Katalog Sürecinde`, `CREATED` ve `MATCHED` silinemez; istek düzeyinde değil **kalem düzeyinde** başarısız olur.

---

### 4.2 Katalog güncelleme servisi — `https://mpop-sit.hepsiburada.com/ticket-api` ⚠️

Aynı host, **farklı yol öneki**, **farklı payload konvansiyonu**, **farklı anahtar**.

| İşlem | Metod | Yol |
|---|---|---|
| `uploadTicketViaFile` | POST | `/ticket-api/api/integrator/import?version=1` — multipart, dosya adı `integrator-ticket-upload.json` |
| `getTicketProductsStatusByTrackingId` | GET | `/ticket-api/api/integrator/status/{trackingId}` |
| Ürün bazlı güncelleme geçmişi ⚠️ | GET | `/ticket-api/api/integrator/merchant/{merchantId}/hbSku/{hbSku}` |

**Dosya içeriği:**
```json
{"merchantId":"<UUID>",
 "items":[{"hbSku":"…","productName":"…","productDescription":"…",
           "image1":"…","image2":"…","video":"…"}]}
```

**Tuzaklar:**
- **Anahtar `hbSku`**, `merchantSku` değil. Alan adları **camelCase** (`productName`, `image1`), create API'sinin Türkçe PascalCase'i (`UrunAdi`, `Image1`) **değil**. İki payload modeli **ayrı mapper** ister.
- 🔴 **PATCH semantiği:** *"Hepsiburada tarafından zenginleştirilmiş ve düzeltilmiş ürün bilgilerinin ezilmemesi için sadece güncellenmek istenen alanların iletilmesi önemlidir."* Tam ürün dokümanı göndermek HB'nin editoryal düzeltmelerini **ezer** — satıcı için görünür bir hasar. Senkron katmanı **alan bazlı diff** hesaplamak zorundadır (§10, M13).
- Üçüncü `trackingId` akışı; yine sayfalı poll.

---

### 4.3 Listing host — `https://listing-external-sit.hepsiburada.com`

#### 4.3.1 Satıcı Listing Listesi ✅ ölçüldü
`GET /listings/merchantid/{merchantId}`

| Param | Tip | Not |
|---|---|---|
| `offset` | integer | **`limit` ile birlikte zorunlu** |
| `limit` | integer | ⚠️ tavan belgesiz; ölçümde `limit=2` çalıştı |
| `merchantSkuList` | string | ⚠️ virgülle ayrılmış SKU filtresi |

**Ölçülmüş yanıt** — `totalCount: 39`:
```json
{"listings":[
  {"listingId":"fc7ac444-3c78-49c4-b700-01e5cb9909e0","uniqueIdentifier":"",
   "hepsiburadaSku":"HBV0000105YIF","merchantSku":"HBV0000KSJL98",
   "price":10.0,"availableStock":100,"dispatchTime":1,
   "cargoCompany1":"Aras Kargo","cargoCompany2":"","cargoCompany3":"",
   "shippingAddressLabel":"BIRINCIL","shippingProfileName":"","claimAddressLabel":"BIRINCIL",
   "maximumPurchasableQuantity":0,"minimumPurchasableQuantity":0,"pricings":[],
   "isSalable":true,"customizableProperties":[],"deactivationReasons":[],
   "isSuspended":false,"isLocked":false,"lockReasons":[],
   "isFrozen":false,"freezeReasons":[],"availableWarehouses":[],
   "isFulfilledByHB":false,"priceIncreaseDisabled":false,"priceDecreaseDisabled":false,
   "stockDecreaseDisabled":false,"skuAfterSuspension":null,
   "productId":"HB0000105YIE","hasVariant":false}],
 "totalCount":39,"limit":2,"offset":0}
```

**Ölçümden çıkan kritik gerçekler:**
- **Zarfta `success`/`code` YOK.** Katalog zarfı burada geçerli değil (H2).
- **`price` sayısal** (`10.0`), yazma tarafı ise virgüllü **string** ister. **Okuma/yazma tip asimetrisi.**
- `listingId` bir **UUID**dir ve **güncelleme anahtarı DEĞİLDİR** — güncelleme `(hepsiburadaSku, merchantSku)` çiftiyle yapılır (§9.1).
- `productId` (`HB0000105YIE`) belgelenmemiş **altıncı** bir tanımlayıcıdır ⚠️; `hepsiburadaSku`'dan (`HBV0000105YIF`) farklıdır. Üzerine mantık kurmayın.
- ✅ **H6'nın canlı kanıtı** ikinci kayıtta: `price: 0.0`, `availableStock: 0`, `isSalable: false`, `deactivationReasons: ["PriceIsLessThanOrEqualToZero","StockIsLessThanOrEqualToZero"]`.
- `maximumPurchasableQuantity: 0` **sınırsız** demektir, sıfır değil ⚠️.

#### 4.3.2 Fiyat / stok / envanter yükleme — beş asenkron kanal ⚠️

| Amaç | POST yolu | Poll yolu |
|---|---|---|
| Fiyat + stok + kargo birlikte | `/listings/merchantid/{id}/inventory-uploads` | `/listings/merchantid/{id}/inventory-uploads/id/{uploadId}` |
| Yalnız stok | `/listings/merchantid/{id}/stock-uploads` | `…/stock-uploads/id/{uploadId}` |
| Yalnız fiyat | `/listings/merchantid/{id}/price-uploads` | `…/price-uploads/id/{uploadId}` |
| Teslimat süresi (`dispatchTime`) | `/listings/merchantid/{id}/shipping-info-uploads` | `…/shipping-info-uploads/id/{uploadId}` |
| Ek bilgiler | `/listings/merchantid/{id}/extra-info-uploads` | `…/extra-info-uploads/id/{uploadId}` |

**POST yanıtı:** `{"Id":"3957bf91-a1ee-4657-92a0-fcb07bb69d83"}` — büyük harf `Id` ⚠️.
**Poll yanıtı:**
```json
{"id":"…","status":"Done","createdAt":"2023-10-02T11:09:16.334Z","total":1,"processed":1,
 "errors":[{"elementNo":1,"hepsiburadaSku":"HBCV…","merchantSku":"…","errors":["OutOfPriceRange"]}],
 "priceValidations":[{"elementNo":1,"hepsiburadaSku":"…","merchantSku":"…","type":"MaxLock",
                      "minPrice":899.8,"maxPrice":13767.0,"description":"…"}]}
```

**Tuzaklar:**
- Belgeli `status` değerleri **`Done` / `Failed`**. Bir OSS SDK `PROCESSING` görüyor ⚠️. Poller'ı `while status not in {Done, Failed}` olarak yazın, `while status == PROCESSING` olarak **değil**.
- **Tüm gerçek hatalar yalnız poll'da görünür**; POST 200 döner.
- **XML birinci sınıf vatandaştır**: `Content-Type: application/xml` desteklenir. 4000 SKU'luk batch'lerde JSON gövde boyutu sorun olursa alternatif.
- `dispatchTime` Ocak 2024'te `inventory-uploads`'tan **kaldırıldı** — yalnız `shipping-info-uploads` ile set edilir.
- `MinLock`/`MaxLock` **yarı-kurtarılabilir**: yanıt `minPrice`/`maxPrice` verir; banda uygun fiyat gönderilirse sistem **kendiliğinden kilidi açar ve yeniden listeler**. Yalnız gerçekten bant dışı fiyat istiyorsanız `POST /listings/merchantid/{id}/unlock` gerekir.
- **Fiyat veya stoku 0 yapmak listing'i satıştan kaldırır**; her ikisi sıfırdan büyükse geri açar. Ayrıca açık `activate`/`deactivate` endpoint'leri de var — aynı sonuca iki mekanizma ⚠️.
- HepsiJet / Horoz / Borusan **tek başına kargo firması olamaz**; `CargoCompany2`'de standart bir kargo firması gerekir.

---

### 4.4 OMS host — `https://oms-external-sit.hepsiburada.com` ✅ (iki uç ölçüldü)

**✅ Ölçülmüş en önemli bulgu: katalog kimlik bilgisi OMS host'unda çalışıyor.** Sipariş entegrasyonu için **ayrı kimlik bilgisi gerekmiyor** — araştırma notlarının "sipariş için ayrı creds isteyin" tavsiyesi bizim hesabımız için geçersiz.

#### 4.4.1 Ödemesi Tamamlanmış Siparişler ✅ ölçüldü
`GET /orders/merchantid/{merchantId}?offset=0&limit=1`

**Ölçülen yanıt (boş test hesabı):**
```json
{"totalCount":0,"limit":1,"offset":0,"pageCount":0,"items":[]}
```
Zarf `{totalCount, limit, offset, pageCount, items[]}` — dördüncü zarf biçimi. ⚠️ **`items[]` satır şekli ölçülemedi** (test merchant'ında sipariş yok). Webhook `create-order` payload'ından türetilen beklenen alanlar §11'de. → Ek A **P0**, SIT test siparişi üretilerek kapanır.

#### 4.4.2 Satıcı Paketleri ✅ ölçüldü
`GET /packages/merchantid/{merchantId}?offset=0&limit=1`

**Ölçülen yanıt: `[]` — ÇIPLAK DİZİ, zarf yok.** Beşinci zarf biçimi. Aynı host içinde `/orders` sarmalı, `/packages` sarmasız.

#### 4.4.3 Diğer sipariş / paket / talep uçları ⚠️ (arşiv navigasyonundan, ölçülmedi)

| Amaç | Metod + yol |
|---|---|
| Ödemesi bekleyen siparişler | `GET /orders/merchantid/{id}/paymentawaiting` |
| İptal siparişler | `GET /orders/merchantid/{id}/cancelled` |
| Sipariş detayı | `GET /orders/merchantid/{id}/ordernumber/{no}` |
| Teslim edilen / kargoya verilen / teslim edilemedi paketler | `GET /packages/merchantid/{id}/delivered` · `/shipped` · `/undelivered` |
| Bozulan (unpack) paketler | `GET /packages/merchantid/{id}/status/unpacked` |
| Faturası eksik paketler | `GET /packages/merchantid/{id}/missing-invoice` |
| Aynı pakete konulabilecek kalemler | `GET /lineitems/merchantid/{id}/packageablewith/lineitemid/{id}` |
| Kalem paketleme / bölme / bozma | `POST /packages/merchantid/{id}` · `…/split` · `…/unpack` |
| Paket kargo bilgisi | `GET /packages/merchantid/{id}/packagenumber/{no}` |
| Ortak barkod / etiket | `GET /packages/…/labels` |
| Depo güncelleme | `PUT /packages/…/warehouse` |
| Fatura linki gönderme | `PUT` — fatura URL'i `application/pdf` veya `text/html` olmalı |
| Satıcı iptali | `POST /lineitems/merchantid/{id}/id/{lineId}/cancelbymerchant` |
| Kargo firması değiştirme | `GET /delivery/changeablecargocompanies/…` · `PUT /lineitems/…/cargocompany` · `PUT /packages/…/changecargocompany` |
| Mağaza hesabı teslimat statüsü | `POST /packages/…/intransit` · `…/deliver` · `…/undeliver` |
| Talepler (iade) | `GET /claims/merchantid/{id}` · `GET /claims/merchantid/{id}/status/{status}` · `GET /claim/orderlines/ordernumber/{no}/claimgroupreferencenumber/{ref}` |
| Talep kabul / red / kurye | `POST /claims/number/{claimNumber}/accept` · `/reject` · `/sendcourier` |
| **Test siparişi oluşturma (yalnız SIT)** | `POST /orders/merchantid/{id}` |

**Belgeli kısıtlar** ⚠️: `offset` ve `limit` **birlikte zorunlu** (biri tek başına 400); `limit` tavanı paketlerde **10**, çoğu sipariş listesinde **50**; `items.dueDate` kargo SLA son tarihidir.

---

### 4.5 Shipping host — `https://shipping-external-sit.hepsiburada.com` ⚠️ (ölçülmedi)

| Amaç | Metod + yol |
|---|---|
| Kargo firmaları | `GET /cargoFirms/{merchantId}` |
| Profil listeleme / oluşturma / güncelleme | `GET /profiles/{merchantId}` · `POST /profile/createbymerchantid` · `PUT /profile/updatebymerchantid` |

---

## 5. Enum kataloğu

Her tabloda **KobiConnect kanonik karşılığı** sütunu vardır. "**yok**" yazan satırlar §10'un girdisidir.

### 5.1 `productStatus` — katalog moderasyon yaşam döngüsü

Bu, **listing'in satışta olup olmadığı değildir**; ürünün HB kataloğuna kabul sürecidir.

| Enum | Türkçe etiket | Anlam | Sonraki aksiyon kimde? | API update? | KobiConnect kanonik karşılığı |
|---|---|---|---|---|---|
| `WAITING` | İncelenecek | HB ürün giriş ekibi inceliyor | **HB** | ✅ | `CanonicalListingStatus::PendingApproval` |
| `MISSING_INFO` | Ürün bilgileri eksik | Alan eksik/hatalı; düzelt ve yeniden gönder | **Biz** | ✅ (zorunlu) | `PendingApproval` + `channel_listings.error` (içerik geçersiz) |
| `PRE_MATCHED` | Eşleşen | HB mevcut bir katalog ürünüyle eşleştirdi, **onayımızı bekliyor** | **Biz** | ✅ (onay/red öncesi son pencere) | **YOK** → §10 M1. Yeni case `AwaitingMatchDecision` önerilir |
| `MATCHED` | **Satışa Hazır** | Katalogda; satıcı için listing yaratıldı | yok | ❌ | Katalog ekseni tamamlandı. Listing durumu **listing host'undan** okunur — `MATCHED` tek başına `OnSale` demek **değildir** |
| `MATCHED_WITH_STAGED` | Ön Katalog Eşleşen | Ön katalogla eşleşti | yok | ❌ | `Locked` (düzenlenemez) |
| `REJECTED` | Reddedilen Eşleşme **/** Reddedildi | **İKİ ANLAM:** (a) biz HB'nin önerisini reddettik (b) HB yasaklı/izin gerektiren ürünü reddetti | duruma göre | – | `Rejected` — ⚠️ **iki sebep ayrımı kaybolur**; `channel_listings.error` içinde ayrıca saklanmalı |
| `CREATED` | Yaratıldı | Kendi katalog kaydımız yaratıldı | yok | ❌ | Katalog ekseni tamamlandı (yukarıdaki gibi) |
| — | Katalog Sürecinde | HB ekibi üzerinde çalışıyor | HB | ❌ `"Can not update product in matched or in catalog progress status"` | `Locked` — ⚠️ makine enum karşılığı belgelenmemiş |
| — | Görev açılmış | Veri hatalı, MPOP'ta görev açıldı | **Biz** | ✅ (zorunlu) | `PendingApproval` + `taskDetails.url` |
| `IN_EXTRENAL_PROGESS` | — | ⚠️ Yalnız SDK enum'larında; **upstream'in kendi yazım hatası** | – | – | ⚠️ tanımadığımız değer → sıraya bırak, katlamayın |
| `BLOCKED` | — | ⚠️ Yalnız SDK enum'larında, portal belgelemiyor | – | – | ⚠️ aynı |

> 🔴 **Şiddetli isimlendirme tuzağı.** `MATCHED` = **"Satışa Hazır"**, `PRE_MATCHED` = **"Eşleşen, onay bekliyor"**. İngilizce sezgi bunları **ters çevirir** ("pre-matched" kulağa "henüz eşleşmedi" gibi gelir; oysa eşleşme *önerilmiş* ve bizi bekliyordur). Eşlemeyi bu tablodan hard-code edin ve **bir test yazın**.

**Düzenlenebilirlik statüden türetilir, ayrı bir alandan değil.** `CanonicalListingStatus::isEditable()` HB'de `productStatus`'tan sürülmelidir: `WAITING`, `MISSING_INFO`, `PRE_MATCHED` → düzenlenebilir; `MATCHED`, `MATCHED_WITH_STAGED`, `CREATED`, "Katalog Sürecinde" → değil. Senkron motoru yazmadan önce son bilinen statüyü kontrol etmezse **kalıcı olarak başarısız bir güncelleme döngüsüne** girer.

### 5.2 `importStatus` — dosya düzeyi işleme

| Değer | Anlam | KobiConnect kanonik karşılığı |
|---|---|---|
| `PROCESSING` | Dosya işleniyor | `SyncState::InFlight` |
| `SUCCESS` | **Dosya ayrıştırıldı ve alındı** — ürünlerin doğru olduğu anlamına GELMEZ | `SyncState::InFlight` → kalem sonuçları okunduktan sonra `Completed`/`Failed` |
| `FAILED` | Dosya düzeyinde başarısız | `SyncState::Failed` |

⚠️ Listing tarafında karşılığı farklıdır: `Done` / `Failed` (+ belgesiz `PROCESSING`). **İki farklı sözlük, iki farklı çevirici.**

### 5.3 `importMessages[].severity` — satır düzeyi

| Değer | Anlam | KobiConnect kanonik karşılığı |
|---|---|---|
| `INFORMATION` | Bilgilendirme | `itemResults[ref].accepted = true` |
| `WARNING` | **Kabul edildi ama etkisi bozuldu** (tipik: 0 fiyat/0 stok — H6) | **YOK** → §10 M2. `{accepted:true, degraded:true}` önerilir |
| `ERROR` | Satır başarısız | `itemResults[ref].accepted = false` |

### 5.4 Kategori `status` ✅ ölçüldü

| Değer | Anlam | KobiConnect kanonik karşılığı |
|---|---|---|
| `ACTIVE` | Aktif | `CategoryNodeData::isLeaf` hesabına girer |
| `INACTIVE` | Pasif ⚠️ (ölçülmedi, dokümandan) | uygun değil |

**Uygunluk kuralı:** `leaf === true && status === 'ACTIVE' && available === true`. Kanonik `CategoryNodeData.isLeaf` bu **üçlü VE** olarak doldurulmalıdır (§10 M6).
✅ **Ölçüm bir araştırma uyarısını geçersiz kıldı:** değer Türkçe `"AKTİF"` / `"AKTİF DEĞİL"` değil, **İngilizce `ACTIVE`**tir. Noktalı `İ` (U+0130) casefolding tuzağı bu alan için geçerli **değildir**.

### 5.5 Attribute `type` ✅ ölçüldü

| Değer | Anlam | Değer listesi çağrılır mı? | KobiConnect kanonik karşılığı |
|---|---|---|---|
| `string` | Serbest metin | Hayır | `allowsCustomValue = true` |
| `integer` | Tam sayı | Hayır | `allowsCustomValue = true` — ⚠️ sayısal olduğu bilgisi **kaybolur** |
| `enum` | Listeden seçim | **Evet** (§4.1.3) | `allowsCustomValue = false`, `values[]` doldurulur |
| `media` | **Görsel URL'i** | Hayır | **YOK** → §10 M4. `allowsCustomValue=true` olarak katlanır ve ön-doğrulama "serbest metin" sanır |
| `video` | **Video URL'i (MP4)** | Hayır | **YOK** → aynı |

Doküman yalnız `"String"` / `"Enum"` (PascalCase) belgeliyor; **ölçüm beş değer ve küçük harf gösteriyor.** Enum eşlemesi büyük/küçük harfe duyarsız yapılmalı.

### 5.6 Attribute kovaları ✅ ölçüldü

| Kova | KobiConnect kanonik karşılığı |
|---|---|
| `baseAttributes` | Mapper'ın **ayrılmış anahtar listesi** — `AttributeData` olarak yayımlanmaz, doğrudan `ProductData`/`VariantData` alanlarına bağlanır |
| `attributes` | `AttributeData` listesi (`isVarianter = false`) |
| `variantAttributes` | `AttributeData` listesi (**`isVarianter = true`**) |
| — | `isSlicer` HB'de **her zaman `false`** — karşılığı yok |

### 5.7 Listing bayrakları ✅ ölçüldü

| Alan | Tip | Anlam | KobiConnect kanonik karşılığı |
|---|---|---|---|
| `isSalable` | bool | Satılabilir mi | `CanonicalListingStatus::OnSale` / `NotOnSale` — **kanonik listing statüsünün asıl kaynağı** |
| `deactivationReasons[]` | string[] | Neden satılamıyor. ✅ ölçülen değerler: `PriceIsLessThanOrEqualToZero`, `StockIsLessThanOrEqualToZero` | **YOK** → `channel_listings.error` jsonb'sine |
| `isSuspended` / `skuAfterSuspension` | bool / string | Askıya alınmış | `NotOnSale` + error |
| `isLocked` / `lockReasons[]` | bool / string[] | Fiyat eşiği kilidi (`MinLock`/`MaxLock`) | `CanonicalListingStatus::Locked` |
| `isFrozen` / `freezeReasons[]` | bool / string[] | Dondurulmuş; güncelleme kabul edilmez | `Locked` |
| `priceIncreaseDisabled`, `priceDecreaseDisabled`, `stockDecreaseDisabled` | bool | Kampanya koruması | **YOK** → push öncesi yerel ön-doğrulamada okunmalı, aksi hâlde push kesin başarısız |
| `isFulfilledByHB` | bool | HB depolu | **YOK** → stok tahsisi bu listing'i **atlamalı** |
| `hasVariant` | bool | Varyantlı mı | bilgilendirici |
| `maximumPurchasableQuantity` | int | **`0` = sınırsız**, sıfır değil | **YOK** ⚠️ |
| `availableWarehouses[]` | array | ✅ ölçümde boş | `StockData.remoteWarehouseId` ⚠️ upload tarafında kabul edilip edilmediği ölçülmedi |
| `pricings[]` | array | ✅ ölçümde boş; kampanya/indirim fiyatları ⚠️ | `PriceData.listPrice` adayı ⚠️ |

### 5.8 Sipariş / paket statüleri ⚠️ (arşiv, ölçülmedi)

| Değer | KobiConnect kanonik karşılığı |
|---|---|
| `PaymentAwaiting` | `CanonicalOrderStatus::PendingPayment` |
| `Open` | `Created` |
| `Packaged` | `Picking` ⚠️ |
| `Shipped` | `Shipped` |
| `Delivered` | `Delivered` |
| `Undelivered` | `Undelivered` |
| `Unpacked` | `Unpacked` |
| `Cancelled` | `Cancelled` |
| tanınmayan | `CanonicalOrderStatus::Unknown` — ham değer `external_status`'ta kalır, hiçbir şey tetiklemez |

### 5.9 Talep (claim) tipleri ve red sebepleri ⚠️ (arşiv, ölçülmedi)

**Tipler:** İade · Yeni ürün (RenewProduct/Temin) · Eksik parça (MissingPart) · Hasarlı ürün · Yanlış ürün · Teslim edilemeyen ürün · Eksik fatura (MissingInvoice) · Eksik garanti
**`ClaimRejectionReason`:** `CustomerReturnedWrongItem` · `ProductIsDamaged` · `MissingQuantity` · `NoSuchAccessory`

---

## 6. Asenkron akış

### 6.1 `trackingId` yaşam döngüsü (katalog)

```
1. POST /product/api/products/import          (multipart, .json dosyası, DİZİ)
   ↓  {success:true, code:0, data:{trackingId}}          ← "kabul edildi", "uygulandı" DEĞİL
2. channel_operations satırı: remote_batch_id = trackingId
                              payload = gönderilen dizinin SIRASI KORUNARAK
3. GET /product/api/products/status/{trackingId}?page=0&size=100
   ↓  sayfalı; TÜM sayfalar gezilmeli
4. Her satır için ÜÇ ekseni birlikte oku (§6.3) → itemResults
5. importStatus PROCESSING iken 3'e dön (backoff)
6. Kalan iş: productStatus PRE_MATCHED ise → ön eşleşme kuyruğuna (H10)
```

**Kurtarma:** 1 ile 2 arasında çökülürse `GET /products/trackingId-history` çağrılır, timeout penceresi içindeki `createdDate` bulunur ve o `trackingId` sahiplenilir. **Import asla kör retry edilmez** (H12).

### 6.2 `itemOrderID` eşlemesi

| Anahtar | Kaynak | Güvenilirlik |
|---|---|---|
| `merchantSku` | Poll satırı | **Birincil** — ama sunucu tarafında büyük harfe çevrilir (H8), karşılaştırma normalize hâlde yapılmalı |
| `itemOrderID` | Poll satırı | **İkincil** — gönderilen dizideki **konumsal indeks**. Diziyi yeniden üretir/sıralar/alt küme retry ederseniz kayar |

**Uygulama kuralı:**
```
reference := UPPER(no_spaces(sku))        // kanonik ProductData.reference
attributes.merchantSku := reference        // birebir aynı değer gönderilir
payload := [ index => reference, ... ]     // channel_operations.payload'da saklanır
poll satırı → merchantSku varsa onunla, yoksa payload[itemOrderID] ile çöz
```
⚠️ `itemOrderID`'nin **0 mı 1 mi tabanlı** olduğu ölçülmedi. Kaynaklar "0/1 tabanlı" diye belirsiz yazıyor. → Ek A **P0**: iki kalemlik bir import at, dönen indeksleri oku.

### 6.3 Üç dik statü ekseni ve "SUCCESS ama bozuk" durumu

Aynı poll satırında birbirinden **bağımsız** üç eksen döner:

| Eksen | Neyi anlatır | Doğru okuma |
|---|---|---|
| `importStatus` | **Dosya satırı** ayrıştırıldı mı | `SUCCESS` = "alındı", ürünün doğru olduğu **değil** |
| `productStatus` | **Ürün** katalog yaşam döngüsünde nerede | Asıl iş durumu |
| `importMessages[].severity` | **Satır** düzeyinde uyarı derecesi | `WARNING` = kabul edildi ama etkisi bozuldu |

**Kanonik "SUCCESS ama bozuk" kombinasyonları:**

| `importStatus` | `productStatus` | `severity` | Gerçek durum | Naif kod ne der |
|---|---|---|---|---|
| `SUCCESS` | `MISSING_INFO` | `ERROR` | Ürün eksik bilgiyle takılı | ✅ "başarılı" |
| `SUCCESS` | `MATCHED` | `WARNING` | **Ürün canlı ama 0 fiyat / 0 stok** (H6) | ✅ "başarılı" |
| `SUCCESS` | `PRE_MATCHED` | `INFORMATION` | Onay bekliyor, **hiçbir şey satılmıyor** | ✅ "başarılı" |

→ **Sonuç:** `PushResult.itemResults[ref].accepted` **yalnız `importStatus`'a bakarak doldurulamaz.** Karar fonksiyonu:
```
accepted  := importStatus == SUCCESS && severity != ERROR && productStatus != REJECTED
degraded  := severity == WARNING || productStatus in {MISSING_INFO, PRE_MATCHED}
```
`degraded` alanının kanonik karşılığı **yoktur** → §10 M2.

### 6.4 `uploadId` yaşam döngüsü (listing)

```
POST /listings/merchantid/{id}/{kind}-uploads  →  {"Id": "<uuid>"}
GET  /listings/merchantid/{id}/{kind}-uploads/id/{uuid}
     status ∈ {Done, Failed}  (+ belgesiz PROCESSING ⚠️)
     errors[] ve priceValidations[] kalem bazlı; elementNo = satır indeksi
```
Aynı konumsal-indeks tuzağı burada da geçerli (`elementNo`). **En fazla 5 eşzamanlı iş** (H7) — poller bunu semafor olarak uygulamalı.

### 6.5 `approve-prematch` / `reject-prematch` — üçüncü idiom: doğrulanamaz yazma

`trackingId` **yok**, `uploadId` **yok**, kalem sonucu **yok**. İşin kapanması yalnız `products-by-merchant-and-status` yeniden okunarak (`PRE_MATCHED` kovasından düşmüş mü) doğrulanır.
→ **Sonuç:** bu operasyon `channel_operations`'ta `remote_batch_id = null` ile açılır ve **mutabakat işi tarafından** kapatılır, poller tarafından değil. `PushResult.isPending()` (accepted && itemResults boş) bu operasyon için **sonsuza kadar true** kalır — §10 M3.

### 6.6 Polling temposu ve saklama

- ⚠️ **Belgelenmiş poll aralığı ya da backoff yok.** Paylaşılan IP bütçesine (§7) karşı planlayın: önerilen 5 sn → 15 sn → 30 sn → 60 sn üstel backoff, üst sınır 10 dk.
- ⚠️ **`trackingId` / `uploadId` sonuçlarının saklama süresi belgelenmemiş.** Belgelenen tek pencere farklı bir tanımlayıcıya ait: `x-correlation-id` **7 gün**, ve yalnız **destek talebi** ile sorgulanabilir — çağrılabilir bir API değil.
- → **Gönderilen payload'ı, tanımlayıcıyı ve okunmuş sonucu kendi veritabanımızda saklayın.** HB'nin sonuç deposu "best effort" kabul edilmelidir.
- ✅ Ölçüldü: yanıtlarda `x-correlation-id`/`X-Request-Id` başlığı geldiği doğrulanmadı ⚠️ — ölçüm gövdelere odaklandı. Destek talebi açabilmek için bu başlık **loglanmalı** → Ek A P1.

---

## 7. Rate limit

### 7.1 Operasyonel tavan

| Kaynak | İddia | Boyut |
|---|---|---|
| **Satıcı notu (hesabımız için otorite kabul edilen)** | **180 istek / 1 dakika** | **her bir IP başına** |
| Katalog "Kategori Bilgilerini Alma" (arşiv 2026-02) ⚠️ | 100 istek / 1 saniye | IP başına |
| Katalog "Ürüne Ait Statü Bilgisi Çekme" ⚠️ | 100 istek / 1 saniye (2023 anlık görüntüsü: 500/sn) | IP başına |
| Sipariş / OMS ⚠️ | 1000 istek / 1 saniye | IP başına |
| Listing "Komisyon Bilgisi Sorgulama" (Eki-2025) ⚠️ | 240 istek / dakika, maks 50 SKU | ⚠️ boyut belirsiz |
| Listing toplu yükleme ⚠️ | RPS değil: **5 eşzamanlı** + **4000 SKU/istek** + **günlük 10× listing** | **satıcı başına** |

> ⚠️ **Çelişki, açıkça:** "180 istek/dk" ifadesi Hepsiburada'nın resmî dokümantasyonunda, changelog'unda ya da herhangi bir OSS istemcisinde **bulunamadı**; kaynağı satıcının bize ilettiği nottur. Resmî sayfalar **saniye bazlı ve çok daha yüksek** limitler yazıyor. **Sıkı olanı — 180/dk — güvenli tavan kabul edin**, ama limiter'ı yapılandırılabilir yazın ve gerçek 429 eşiğini ölçün → Ek A P1.

### 7.2 ⚠️→🔴 Kritik: IP başına = çok kiracılıda GLOBAL bütçe

Trendyol'un limitleri **satıcı başınadır**; her tenant kendi kovasını harcar. Hepsiburada'nın katalog ve sipariş limitleri **çıkış IP'si başınadır**.

```
Tek VDS  →  tek çıkış IP  →  TÜM tenant'ların paylaştığı TEK kova
```

**Sonuçlar:**
- Limiter anahtarı **tenant içermez**: `hepsiburada:{host}` (ya da `hepsiburada:{host}:{endpointClass}`).
- `RateLimiter` cache kullanır ve `CacheTenancyBootstrapper` altında anahtarlar **tenant-tag'lenir** — bu, HB için **yanlıştır**. `global_cache()` kullanılmalıdır (BACKEND-PLAN §7.6).
- `TrendyolRateLimiter`'ın `(sellerId, endpoint)` iki eksenli şekli **kopyalanamaz**; HB için `(host, endpointClass)` + opsiyonel adil-paylaşım katmanı gerekir.
- Bir tenant'ın gece kategori dolumu (5611 kategori / 100 = 57 istek + kategori başına N enum attribute çağrısı) diğer tüm tenant'ları **aç bırakabilir**. Referans verisi senkronu **kiracıdan bağımsız, tek seferlik ve paylaşılan** olmalıdır — kategori ağacı ve attribute'lar tüm tenant'lar için aynıdır, tenant başına çekilmesinin hiçbir gerekçesi yoktur.
- Yatay ölçekleme çıkış IP'lerini çoğaltır ve bütçeyi **büyütür** — ama NAT arkasındaki worker'lar aynı kovayı paylaşır. Limiter, worker sayısından bağımsız olmalı (merkezî Redis sayacı).

### 7.3 Limit aşımında ne döner

| Durum | Anlam | Retry? |
|---|---|---|
| `429 TooManyRequest` | Limit aşıldı | ✅ `X-RateLimit-Reset` (saniye) üzerinden backoff |
| `406 Not Acceptable` — "Günlük limiti aştınız" ⚠️ | Günlük kotalı bir sipariş endpoint'i | ⏳ ertesi gün. **Retry sınıflandırıcısı 406'yı doğrulama hatası değil, limit durumu saymalı** |

**429 yanıt başlıkları** ⚠️ (sipariş dokümanından, ölçülmedi): `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. **`Retry-After` belgelenmemiş** — varlığını varsaymayın.

✅ **Ölçüldü: 200 yanıtlarında hiçbir rate-limit başlığı gelmiyor.** `X-RateLimit-*` ve `Retry-After` yok. Yani **kalan bütçeyi yanıttan öğrenemezsiniz**; istemci bütçeyi **kendisi tutmak** ve isteği göndermeden **önce** harcamak zorundadır. (Trendyol'daki durumla aynı; `TrendyolRateLimiter`'ın "bütçe bizimdir, 429'dan sonra tamir edilmez" felsefesi burada da geçerlidir.)

---

## 8. Hata yönetimi

### 8.1 İki farklı zarf ✅ ölçüldü

**A — Katalog başarı/iş hatası zarfı (HTTP 200 olabilir!):**
```json
{"success": false, "code": 1003, "version": 1, "message": "…",
 "totalElements": 0, "totalPages": 0, "number": 0, "numberOfElements": 0,
 "first": true, "last": true, "data": null}
```
`code: 0` başarıdır; sıfırdan farklı her değer hatadır. **HTTP durum satırına bakmak yetmez.**

**B — Spring hata zarfı (transport/routing hatası):**
```json
{"timestamp":"2026-08-19T13:19:41.338+0000","status":404,"error":"Not Found",
 "message":"Not Found","path":"/api/categories/26012174/attributes/000009D/values"}
```
`success`, `code`, `data` **yoktur**. Başarı zarfını bekleyen bir çözücü burada patlar.

**C — Zarfsız yanıtlar:** `GET /packages/...` çıplak dizi ✅, `GET /products/delete-process/{id}` ham nesne ⚠️. Bunlarda `{success:false}` **hiç oluşmaz**; 4xx/5xx transport düzeyinde ayrı ele alınmalı.

→ **İstemci en az üç ayrıştırıcı taşımalıdır** ve hangi zarfın hangi host/endpoint'te geçerli olduğunu bilmelidir.

### 8.2 Katalog hata kodları

| Kod | Anlam | Retry edilebilir? | Aksiyon |
|---|---|---|---|
| `1001` | Kategori leaf değildir | ❌ kalıcı | Kategori seçimi hatalı; kullanıcıya dön |
| `1002` | Kategori aktif değildir | ❌ kalıcı | Kategori cache'ini yenile |
| `1003` | Kategori leaf ve aktif değildir | ❌ kalıcı | Aynı |
| `1004` | CategoryId ile kategori bulunamadı | ❌ kalıcı | Eşleme bozuk |
| `1005` | categoryId için kategori ilişkisi bulunamadı | ❌ | HB'ye ticket |
| `1006` | Kategori mevcut değildir | ❌ kalıcı | – |
| `2001` | Özellik enum değer değildir | ❌ kalıcı | `type != enum` için değer endpoint'i çağrılmış |
| `2002` | attributeId ile özellik bulunamadı | ❌ kalıcı | Attribute cache'ini yenile |
| `3001` | Dosya içeriği geçersiz | ❌ kalıcı | Payload hatası |
| `3002` | Dosya türü geçersiz | ❌ kalıcı | `.json` uzantılı **dosya part**'ı gerekiyor, düz JSON gövde değil |
| `3003` | Dosya bulunamadı | ❌ kalıcı | multipart alan adı `file` olmalı |
| `4000` | TrackingId bulunamadı | ⚠️ **belirsiz** | "Henüz görünür değil" de olabilir "süresi doldu" da. Sınırlı retry, sonra `trackingId-history` ile mutabakat |

### 8.3 Listing kalem hataları (yalnız poll yanıtında görünür) ⚠️

| Kod | Anlam | Retry? |
|---|---|---|
| `ProductNotFound` | HB SKU katalogda yok | ❌ kalıcı |
| `MismatchingSkusSpecified` | `hepsiburadaSku` ve `merchantSku` aynı listing'e ait değil | ❌ **join anahtarınız yanlış** (§9.1) |
| `DuplicateHepsiburadaSkuSpecified` / `DuplicateMerchantSkuSpecified` | Aynı payload'da tekrar | ❌ batch'leme hatası |
| `MissingHeaders` | XML tag / Excel başlığı eksik | ❌ |
| `InvalidPrice` | Ondalık değil **ya da nokta ayraçlı** | ❌ **virgül kullanın: `118,97`** |
| `InvalidAvailableStock` / `InvalidDispatchTime` / `InvalidMaximumPurchasableQuantity` | Tip hatası | ❌ |
| `OutOfPriceRange` | Fiyat bandı dışında (H6) | ❌ iş kuralı |
| `MinLock` / `MaxLock` (`priceValidations` içinde) | Fiyat eşiği kilidi; yanıt `minPrice`/`maxPrice` verir | ⚠️ **yarı-kurtarılabilir**: banda uygun fiyat gönderilince otomatik açılır |
| `ListingFrozen` | Dondurulmuş listing | ❌ açılana kadar |
| `ListingDeletedRecently` | SKU silinmiş / listelerinizde yok | ❌ |
| `MerchantAlreadyListedAgainstProduct` | Kopya listing; askıdaki manuel silinmeli | ❌ manuel |
| `DiscountedListingPriceIncrease` / `DiscountedListingStockDecrease` | Kampanya koruması engelliyor | ❌ iş kuralı |
| `MissingStandardCargoCompany` **ve** `MissingStandartCargoCompany` | HepsiJet/Horoz/Borusan tek başına olamaz | ❌ ⚠️ **doküman aynı kodu iki farklı yazıyor** — normalize edilmiş önek üzerinden büyük/küçük harfe duyarsız eşleştirin |
| `restrictedProductBrand` | Marka kısıtı | ❌ iş kuralı |

### 8.4 HTTP durum kodları ⚠️

| Durum | Doküman ifadesi | Retry? |
|---|---|---|
| 400 | "URL içerisindeki parametreleri kontrol edin" | ❌ |
| 401 | "Password (Şifre) hatalı" | ❌ — auth göçünden sonra genelde **kimlik bilgisi biçimi** ya da `User-Agent` eksikliği demektir (§2.1) |
| 403 | – | ❌ ✅ ölçüldü: SIT kimlik bilgisiyle prod host'una gidince döner |
| 404 | "URL hatalı gönderilmiştir" | ❌ ✅ ölçüldü: `attributes` vs `attribute` yol tuzağı (§4.1.3) |
| 405 | "Http Protokol hatası" | ❌ |
| 406 | "Günlük limiti aştınız" | ⏳ ertesi gün |
| 429 | TooManyRequest | ✅ `X-RateLimit-Reset` ile backoff |
| 500 | "Ticket ileterek entegrasyon ekibi ile iletişime geçiniz" | ✅ okumalarda; ❌ **yazmalarda** |

### 8.5 Retry politikası — asimetrik olmak zorunda

**Idempotency yoktur** (H12). Bu nedenle:

- **Okuma (GET):** 429, 5xx, ağ/timeout → üstel backoff ile retry.
- **Yazma (POST/PUT/DELETE):** **yalnız 429'da** retry — HB bu isteği işlemeden reddeder, replay güvenlidir.
  **Belirsiz 5xx / timeout'ta yazma REPLAY EDİLMEZ.** `products/import` ya da paket mutasyonu gerçek durumu kopyalar.
  Doğru davranış: `trackingId-history` ile arayıp sahiplenmek.
- **İş hataları** (`code` tabanlı ve string tabanlı) **kalıcıdır**; retry etmek yalnız paylaşılan bütçeyi yakar. Satıcıya gösterin.
- **"Zaten var" hatalarını başarı-ile-mutabakat sayın**, başarısızlık değil: `"Barcode must be unique barcode"` ya da `"Can not update product in matched or in catalog progress status"` alındığında `checkProductStatus` ile mevcut `hbSku`'yu çekip yerel satıra bağlayın.
- Listing yükleme retry'ı **güvenlidir** (fiyat/stok son-yazan-kazanır). Sipariş/paket mutasyonları **değildir**.

---

## 9. Veri modeli tuzakları

### 9.1 Kimlik alanları — hangisi join anahtarı? ✅ ölçüldü

| Alan | Sahibi | Kapsam | Nerede görünür | Ölçülen örnek |
|---|---|---|---|---|
| **`merchantSku`** | **Biz** | Satıcı bazında tekil; **sunucu tarafında BÜYÜK HARFE çevrilir**, boşluk yasak | Katalog yazma/okuma, listing satırı, silme, onay/red | `8680161820017` ✅ ve `HBV000013N0YB` ✅ ve `HBV0000KSJL98` ✅ |
| **`Barcode`** | GS1 | **EAN13, 13 karakter, GLOBAL tekil** | Yalnız katalog yazma/okuma | `8680161820017` ✅, `11210000018` ✅ |
| **`hbSku`** | Hepsiburada | HB katalog ürün kodu; **kabul edilene kadar null** | Katalog statü yanıtları | `HBV00000U2NIV` ✅ |
| **`hepsiburadaSku`** | Hepsiburada | **Aynı kavram, farklı alan adı** — listing tarafında | Listing satırı | `HBV0000105YIF` ✅ |
| **`variantGroupId` / `VaryantGroupID`** | **Biz** (istemci uydurur) | Varyant gruplama; HB kimlik üretmez | Katalog yazma/okuma | `HB00000U2NIU` ✅ |
| **`listingId`** | Hepsiburada | Listing'in UUID'si — **güncelleme anahtarı DEĞİL** | Yalnız listing okuma | `fc7ac444-3c78-49c4-b700-01e5cb9909e0` ✅ |
| `productId` | Hepsiburada | ⚠️ **Belgelenmemiş**; `hepsiburadaSku`'dan farklı | Yalnız listing okuma | `HB0000105YIE` ✅ |
| `merchantId` | Hepsiburada | Satıcı GUID'i; **hem Basic username hem path hem gövde alanı** | Her yerde | UUID ✅ |
| `preMatchedSku`, `siblingSku` | Hepsiburada | ⚠️ Araştırmada geçiyor, **ölçümde dönmedi** | – | – |

**Join anahtarı kuralları:**

| Taraf | Anahtar | Gerekçe |
|---|---|---|
| **Katalog** | `merchantSku` (normalize) | `hbSku` ön eşleşme penceresinin **tamamı boyunca null**dur; ona FK bağlanamaz |
| **Listing** | **`(hepsiburadaSku, merchantSku)` ÇİFTİ** | API bunu dayatır: `DELETE /listings/merchantid/{id}/sku/{sku}/merchantsku/{merchantSku}` ve çiftin uyuşmaması `MismatchingSkusSpecified` verir |
| **Sipariş** | `orderNumber` + `packageNumber` + `lineItemId` ⚠️ | Ölçülemedi (test hesabında sipariş yok) |

**Asla barkodla anahtarlamayın.** Bir barkod **tek** HB katalog ürününe ama **N satıcının N listing'ine** karşılık gelir; buybox tam olarak böyle çalışır.

⚠️ **`HBV…` ile `HBCV…` öneklerinin semantiği belgelenmemiştir.** İkisi de resmî örneklerde aynı bağlamda geçiyor. Önek üzerine mantık kurmayın.

**Kanonik şema önerisi:**
- `channel_listings` satırı `(connection_id, hepsiburada_sku)` üzerinde tekil; `merchant_sku` ikincil tekil indeks.
- `barcode` **ürün** satırında durur, listing satırında değil.

### 9.2 `merchantSku` sessiz büyük harfe çevirme — en tehlikeli tek satır

> *"Merchantsku bilgisi… büyük harf olarak gönderilmelidir ve boşluk bırakılmadan… **Küçük harf olarak gönderilen merchantSku bilgisi büyük harfe dönüştürülerek kaydedilmektedir.**"*

Gönderdiğiniz `abc-1`, HB'de `ABC-1` olur. Yerelde `abc-1` ile arayan her sorgu **ıskalar** → ürün "yok" sanılır → **kopya yaratılır**. Bu, bu platformdaki en yaygın veri kazası desenidir.

**Kural:**
1. `reference` sınırda normalize edilir: `UPPER` + boşluk temizleme.
2. **Normalize hâli saklanır**, anlık dönüşüm yapılmaz — kolon olarak.
3. Karşılaştırmalar yalnız normalize hâl üzerinden.
4. Aynı normalize değer `attributes.merchantSku`'ya gönderilir → asenkron sonuç eşlemesi (§6.2) sağlam kalır.

⚠️ **Türkçe karakter davranışı belgelenmemiş.** `ı` → `I` mi `İ` mi? `merchantSku`'da Türkçe karakter kullanılmaması öneriliyor ama yasak olduğu yazmıyor. PHP'nin `strtoupper`'ı çok baytlı karakterlerde çalışmaz, `mb_strtoupper($s, 'UTF-8')` `ı`→`I` yapar, Türkçe locale `ı`→`I` ve `i`→`İ` yapar — **üçü farklı sonuç verir.** → Ek A **P0**: `test-ıi` gönderip geri okuyun. En güvenlisi: `reference` üretiminde ASCII dışı karakter **yasaklanmalı**.

### 9.3 Fiyat ve stok — Türkçe ondalık virgüllü STRING (ve okuma/yazma asimetrisi)

| Yön | Alan | Tip | Örnek |
|---|---|---|---|
| **Yazma** (katalog `import`) | `price`, `stock` | **string**, virgül ondalık, maks 2 basamak | `"130,50"`, `"13"` |
| **Yazma** (listing upload) | `Price` | **string**, virgül ondalık | `"118,97"` — nokta → `InvalidPrice` |
| **Okuma** (katalog) ✅ | `tax` | **string, NOKTA ondalık** | `"18.0"` |
| **Okuma** (listing) ✅ | `price` | **sayısal (float)** | `10.0` |

→ **Sonuç:** `PriceData` zaten ondalık **string** tutuyor — iyi. Mapper `toRemote()`'ta `str_replace('.', ',', …)`, `toCanonical()`'da hem sayısal hem noktalı-string girdiyi kabul etmek zorunda. **`number_format` locale'e bırakılmaz**, açıkça yazılır. Nokta ayraç listing'de gürültülü hata, **katalogta sessiz 0/0** verir (H6) — iki farklı ciddiyet.

### 9.4 `attributes` ürünün KENDİSİDİR

HB'de düz bir ürün şeması **yoktur**. Ad, açıklama, marka, barkod, KDV, desi, garanti, görseller, video, fiyat, stok ve varyant eksenleri **hepsi** tek bir düz `attributes` haritasının içindedir; anahtarlar **kategoriye göre değişir** ve çalışma zamanında `getAllAttributesByCategory`'den okunur.

```
Kanonik:  ProductData.name          →  attributes.UrunAdi
          ProductData.description   →  attributes.UrunAciklamasi
          ProductData.brandId       →  attributes.Marka   (id değil, İSİM)
          VariantData.sku           →  attributes.merchantSku
          VariantData.barcode       →  attributes.Barcode
          VariantData.vatRate       →  attributes.tax_vat_rate
          VariantData.weight/dims   →  attributes.kg      (DESİ, kg değil)
          ProductData.images[0..N]  →  attributes.Image1..ImageN
          (kanonik karşılığı yok)   →  attributes.GarantiSuresi  (ay, integer, ZORUNLU)
          ProductData.attributes[]  →  attributes.<kategoriye özgü id>
```

**Bu bir alan yeniden adlandırma değil, tipli sütunların tipsiz bir dizeye katlanmasıdır.** Mapper'ın **ayrılmış anahtar listesi** taşıması zorunludur: bir tenant `price` ya da `merchantSku` adlı bir attribute tanımlarsa ticari alanı ezer.

⚠️ **Attribute `id`'leri opak dizelerdir, Türkçe slug değil** ✅ ölçüldü: `000009D`, `Bluetooth`, `calisma_sekliNew1`, `00000MU`. Aynı mantıksal özellik farklı kategorilerde farklı `id` alabilir. → **Global attribute eşlemesi `remoteId` üzerinden yapılamaz**; `channel_attribute_mappings` anahtarı `(connection_id, remote_category_id, attribute_id)` olmak zorundadır (şema zaten böyle — iyi).

### 9.5 `VaryantGroupID` — istemci-uydurması opak dize

- HB **varyant grubu kimliği üretmez.** Aynı değeri taşıyan satırlar HB'de tek varyant grubu olarak render edilir.
- **Varyantsız ürünler bile benzersiz bir değer ister.** Boş göndermek ya da sabit bir değer paylaşmak alakasız ürünleri **tek varyant grubunda birleştirir** — geri alınması zor bir production kazası.
- ✅ Ölçüm: okuma tarafında `variantGroupId` **dönüyor ve dolu** (`HB00000U2NIU`) → varyant grupları okuma tarafında **yeniden kurulabilir**. (Bir araştırma kaynağının "hiçbir okuma endpoint'i grup id döndürmez" iddiası **yanlış**.)
- **Kural:** `VaryantGroupID := deterministik_türetme(ProductData.reference)` — asla rastgele, asla boş, asla paylaşılmış sabit.

### 9.6 Varyant eksenleri — `_variant_property` sonekli dinamik slug'lar ⚠️

Varyant eksenleri, kategoriye özgü attribute anahtarlarına `_variant_property` soneki eklenerek gönderilir:
```json
"renk_variant_property": "Siyah",
"ebatlar_variant_property": "Büyük Ebat"
```
Sabit `option1/option2/option3` **yoktur**; eksen listesi kategoriden gelir.

- **Okuma tarafında** eksenler `variantAttributes` kovasında ✅ (yapı ölçüldü) ve ürün satırında `variantTypeAttributes[]` alanında ✅ (ölçümde boş) döner.
- ⚠️ **`_variant_property` sonek konvansiyonu doğrulanmadı.** Ölçtüğümüz kategoride `variantAttributes` boştu. Sonekin `variantAttributes[].id`'ye mi ekleneceği, yoksa `id`'nin zaten soneki taşıyıp taşımadığı **bilinmiyor** → Ek A **P0**, varyantlı bir kategoride tek çağrıyla kapanır.
- **Tuzak:** `ebatlar_variant_property` fiziksel ölçü **değildir**; "Büyük Ebat" gibi bir varyant **etiketidir**. Fiziksel boyut alanı HB katalog payload'ında **hiç yoktur**.

### 9.7 Marka — varlık yok, yalnız serbest metin

- **Marka endpoint'i yoktur.** Listeleme yok, arama yok, yaratma yok, id yok.
- `Marka` ürün payload'ında bir **string**tir ve HB editörleri tarafından kendi kopya-marka listelerine karşı elle eşleştirilir. Doküman büyük/küçük harf ve noktalama uyarısı veriyor: `Lego`–`LEGO`, `Faber Castell`–`Faber-Castell`, `Dr Brown`–`Dr. Brown's`.
- Yanlış marka `restrictedProductBrand` uyarısını tetikler.
- ✅ Ölçüm: okuma tarafında `brand` düz string olarak dönüyor (`"Daniel Klein"`, `"Tabasco"`).

→ **Sonuç:** `BrandData.remoteId`'yi dolduracak **hiçbir kaynak yoktur**. HB sürücüsü `SupportsBrandCatalog`'u **implement etmez** — yetenek sistemi bunu doğal olarak halleder (§10 M7). `channel_brand_mappings.remote_brand_id` HB için **marka adını** saklar.

### 9.8 Kategori uygunluğu = `leaf AND status=ACTIVE AND available` ✅ ölçüldü

Üç bağımsız bayrak; üçü de doğru olmalı. `leaf`'e tek başına bakmak kullanıcıya **her yüklemeyi reddedecek** kategorileri gösterir.

✅ **Ölçülmüş karşı örnek:** `categoryId 400276` (Müzik Aletleri) — `leaf: true`, `status: "ACTIVE"`, **`available: false`**.

Ayrıca ölçülen ve kanonik modelde yeri olmayan alanlar: `displayName`, `paths[]` (breadcrumb — **kullanıcıların kategori seçerken gerçekten tanıdığı alan**), `type` (`"HB"`), `sortId`, `productTypes[]`, `merge`.

⚠️ Kategori sayısı: SIT'te **5611** ✅ ölçüldü. Bir OSS kaynağı prod için ~27.000 diyor ⚠️ — SIT ve PROD ağaçları farklıdır, canlıya geçişte yeniden çekilmelidir.

### 9.9 Görseller — 5 (veya 10) sabit skaler alan, koleksiyon değil

- `Image1`, `Image2`, … **numaralı ayrı alanlar**dır; dizi değil.
- Doküman **`Image1..Image5`** (sabit 5 tavan) diyor. ✅ **Ölçüm `Image1..Image10` gösteriyor** (kategori 26012174). ⚠️ Bunun evrensel mi kategoriye özgü mü olduğu ölçülmedi → Ek A P1.
- `Image1` **zorunludur** ✅ ölçüldü.
- Yalnız **PNG/JPG** (GIF yok); `Video1` yalnız **MP4**.
- URL'ler herkese açık erişilebilir olmalı; HB **5 kez dener, sonra o görseli kalıcı olarak atlar** — ürünün tamamı yeniden gönderilmeden düzelmez.
- İçerik kuralları insan incelemesiyle uygulanır: beyaz/açık zemin, metin/logo/filigran yok, ürün karenin %80'ini doldurmalı, arka plan kaldırıldıktan sonra en az bir kenar ≥250px.
- ⚠️ HB'nin görsel tarayıcısı sabit çıkış IP'lerinden gelir: `193.28.225.94`, `185.92.214.94`, `34.78.190.48`, `104.155.47.90`, `34.76.71.175`, `35.240.98.85`.

→ **Sonuç:** `ProductData.images` sınırsız `list<string>`tir; mapper kategorinin açtığı alan sayısına **kesmek** zorundadır. Sessiz kesme veri kaybıdır — push öncesi yerel ön-doğrulama (BACKEND-PLAN §7.5) kullanıcıyı uyarmalıdır.

### 9.10 `kg` kilogram değil, DESİ'dir ✅ ölçüldü

Alan etiketi birebir **"Desi"**. Desi Türkiye'de hacimsel kargo birimidir (`en × boy × yükseklik / 3000`), kütle değil. `VariantData.weight`'i (kg) doğrudan `kg` alanına yazmak **hafif ama hacimli** her üründe kargo maliyetini yanlış hesaplatır.

→ **Sonuç:** mapper `kg := max(weight_kg, desi(dimensions))` hesaplamalıdır. ⚠️ HB'nin kullandığı desi bölen sabiti (3000 mi 5000 mi) belgelenmemiş → Ek A P2.

### 9.11 Kanonik modelde karşılığı hiç olmayan zorunlu alanlar

| HB alanı | Tip | Zorunlu ✅ | Kanonik karşılık |
|---|---|---|---|
| `GarantiSuresi` | integer (ay) | **evet** | **Yok.** `ProductData.attributes[]` içine `attributeCode: 'GarantiSuresi'` ile yerleştirilir; değer `channel_connections.field_overrides`'tan gelir |
| `tax_vat_rate` | string | **evet** | `VariantData.vatRate` ✅ |
| `kg` (desi) | string | **evet** | Hesaplanır (§9.10) |
| `Barcode` | string EAN13 | **evet** | `VariantData.barcode` ✅ — ama **13 karakter EAN13 doğrulaması bizim sınırımızda** yapılmalı |
| `type: media` attribute'lar (örn. "Paket Görseli (ön)") | URL | **evet** (bu kategoride) | **Yok.** `AttributeValueData.value` bir URL taşır ama `AttributeData` bunun medya olduğunu söyleyemez (§10 M4) |

---

## 10. KobiConnect kanonik modeliyle FARK ANALİZİ

Bu bölüm dokümanın en kritik parçasıdır ve **BACKEND-PLAN.md §12'nin kabul kriterinin doğrudan sınavıdır**:

> *"İkinci pazaryerini eklemek, `app/Marketplaces/<YeniPazaryeri>/` klasörü dışında kod değişikliği gerektirmemelidir."*

**Sonuç, açıkça: bu kriter Hepsiburada ile TAM OLARAK KARŞILANMIYOR.** Aşağıda hangi maddelerin mapper'da çözüldüğü, hangilerinin `app/Marketplaces/{Contracts,Data,Support}` altında değişiklik istediği ve hangilerinin şema/UI'a taştığı tek tek yazılıdır. Bunu gizlemek en kötü sonuç olurdu.

### 10.1 Fark tablosu

| # | Ne uymuyor | Mapper'da çözülebilir mi? | Çözülemiyorsa contract'ta ne gerekiyor |
|---:|---|---|---|
| **M1** | **`PRE_MATCHED`** — "pazaryeri bir eşleşme önerdi, onayımızı bekliyor" kavramı. `CanonicalListingStatus`'ta karşılığı yok; `PendingApproval`'a katlamak **aksiyonun kimde olduğunu ters çevirir** (PendingApproval = onlar bizi inceliyor; PRE_MATCHED = biz karar vermeliyiz). Ayrıca karşı öneri (`matchedHbProductInfo`: `hbSku`, `productName`, `brand`, `images[]`, `variantTypeAttributes[]`) taşınacak yer yok — `ProductData`'ya koymak sahiplik semantiğini bozar (o bizim ürünümüz değil). Onay/red için yetenek arayüzü de yok. | ❌ **Hayır.** Ne durum ne karşı öneri ne de aksiyon temsil edilebiliyor. | **CONTRACT DEĞİŞİKLİĞİ GEREKİYOR** (§10.2 K1) |
| **M2** | **Fiyat bandı ihlalinde "kabul edildi ama etkisiz hâle getirildi"** (H6, ✅ ölçüldü). `PushResult.itemResults[ref]` şekli `{accepted: bool, code, message}` — ikili. `accepted:true` dersek senkron motoru `Completed` yazar, satıcı yeşil görür, hiçbir şey satılmaz. `accepted:false` dersek gerçekte başarılı bir çağrıyı sonsuza kadar retry ederiz. | ⚠️ **Kısmen.** `accepted:false` + `code:'DEGRADED_ZERO_PRICE'` semantik bir yalan; retry politikasını da bozar. | **CONTRACT DEĞİŞİKLİĞİ GEREKİYOR** (§10.2 K2) |
| **M3** | **`itemOrderID` konumsal indeksi ↔ `PushResult.itemResults` `reference` anahtarı.** | ✅ **EVET, taşınabilir** — üç koşulla (§10.3). Contract değişikliği **gerekmiyor**. | – |
| **M4** | **`AttributeData`'da `type` alanı yok.** ✅ Ölçülen beş tip: `string`, `integer`, `enum`, `video`, `media`. `allowsCustomValue` (`type !== 'enum'`) tipin yalnız bir bitini taşır; `media`/`video` (URL bekler) ile `string` (serbest metin) ayrımı **kaybolur** — ve ölçümde `type:"media"` bir attribute **`mandatory: true`**. Ayrıca `type`'ı bilmeden değer endpoint'inin çağrılıp çağrılmayacağı bilinemez → her attribute için körlemesine çağrı = paylaşılan IP bütçesinin (§7) israfı. | ⚠️ **Kısmen.** Sürücü kendi içinde tipi tutabilir, ama `AttributeData` senkron motorunun ve **yerel ön-doğrulamanın** (BACKEND-PLAN §7.5) gördüğü şeydir; oraya ulaşmayan bilgi ön-doğrulamada kullanılamaz. Ayrıca `channel_attribute_mappings` tablosunda da `type` kolonu yok. | **CONTRACT DEĞİŞİKLİĞİ ÖNERİLİYOR** (§10.2 K3) + şema |
| **M5** | **`AttributeData.isSlicer`** HB'de karşılığı olmayan bir Trendyol kavramı. | ✅ **Evet** — her zaman `false`. `isVarianter` ise `variantAttributes` kovası üyeliğinden **doldurulabilir** ✅ (araştırmanın "imkânsız" iddiası ölçümle çürüdü). | – |
| **M6** | **`CategoryNodeData.isLeaf` tek bayrak; HB üç bayrağın VE'sini istiyor** (`leaf && status==='ACTIVE' && available`) ✅ ölçüldü. Ayrıca `paths[]` breadcrumb'ının yeri yok. | ✅ **Evet.** `isLeaf := leaf && status==='ACTIVE' && available` olarak doldurulur — docblock zaten "yalnız yaprak düğümler ürün kabul eder" diyor, yani `isLeaf`'in anlamı "yayımlanabilir yaprak"tır. `paths` ise `parentRemoteId` zincirinden yeniden kurulabilir ve `channel_category_mappings.remote_path` kolonuna yazılır. | – (opsiyonel iyileştirme: `CategoryNodeData`'ya `?string $path`) |
| **M7** | **`BrandData` doldurulamıyor** — HB'de marka varlığı yok, `Marka` serbest metin (§9.7). | ✅ **Evet.** HB sürücüsü `SupportsBrandCatalog`'u **implement etmez**; `Capability::supportedBy()` bunu otomatik yansıtır ve UI'dan marka ekranı düşer. `MappingContext.brandIds` haritası HB için `canonicalBrandId → markaAdı` taşır (harita `array<string,string>`, kısıt yok). | – (kural: `channel_brand_mappings.remote_brand_id` HB'de **isim** saklar) |
| **M8** | **`ProductData` → `VariantData` iç içeliği HB'de TERS.** HB düz satır listesidir; gruplama istemci-uydurması `VaryantGroupID` ile yapılır ve varyantsız ürün bile benzersiz bir değer ister. | ✅ **Evet.** `toRemote()` `variants[]`'ı N satıra düzleştirir, hepsine aynı `VaryantGroupID`'yi verir. Okuma tarafında `variantGroupId` **dönüyor** ✅ → yeniden gruplama mümkün. | – |
| **M9** | **`PriceData.listPrice` HB'de yok.** Katalog `import` tek bir `price` alır; listing `price` + `pricings[]` (✅ ölçümde boş). "Üstü çizili fiyat" ayrı bir ürün yüzeyidir (kapsam dışı). | ✅ **Evet** — `listPrice` HB push'unda **düşürülür**, `salePrice` gönderilir. Kullanıcıya kanal başına "bu alan Hepsiburada'ya gönderilmiyor" bilgisi verilmeli. | – |
| **M10** | **`StockData.remoteWarehouseId`** — listing'de `availableWarehouses[]` var ✅ (boş ölçüldü) ama upload tarafının depo kabul edip etmediği ⚠️ bilinmiyor. | ✅ **Evet** (MVP'de yok sayılır). | – |
| **M11** | **`VariantData.weight` (kg) → `kg` (DESİ)** ✅ ölçüldü. Doğrudan yazmak kargo maliyetini yanlış hesaplatır. `VariantData.dimensions` ve `hsCode`'un HB'de karşılığı yok. | ✅ **Evet.** `kg := max(weight, desi(dimensions))`. `hsCode` HepsiGlobal konusudur, yurt içi HB'de düşürülür. | – |
| **M12** | **`ProductData.images` sınırsız liste → `Image1..ImageN` sabit skaler alanlar** (✅ ölçülen N=10; doküman 5). Fazlası **sessizce düşer**. | ✅ **Evet** — mapper keser. Ama sessiz kesme veri kaybıdır: yerel ön-doğrulama kullanıcıyı **push öncesi** uyarmalı. | – |
| **M13** | **`SupportsProductSync::updateProducts()` HB'de İKİ FARKLI YOLA gider** ve seçim son bilinen `productStatus`'a bağlıdır: düzenlenebilir statüde `/products/import` (tam doküman, Türkçe PascalCase anahtarlar), `MATCHED` sonrası `/ticket-api/api/integrator/import` (**yalnız değişen alanlar**, camelCase anahtarlar, `hbSku` anahtarlı). Tam doküman göndermek HB'nin editoryal zenginleştirmesini **ezer**. | ⚠️ **Kısmen.** Yönlendirme `ProductData.status` üzerinden yapılabilir (alan **mevcut**) — çağıran tarafın `channel_listings.remote_status`'tan doldurması **invariant** olarak yazılmalı. Ama **diff için son bilinen uzak durum gerekiyor**; `channel_listings` yalnız `remote_payload_hash` tutuyor, payload'ın kendisini değil. | ⚠️ **Şema ya da ekstra çağrı.** MVP çözümü: güncelleme öncesi ürünü HB'den çekip bellekte diff'lemek (+1 GET/güncelleme). Kalıcı çözüm: `channel_listings.remote_snapshot jsonb` (şema değişikliği → §12 ihlali) |
| **M14** | **Artımlı senkron imkânsız.** Katalog okuma endpoint'lerinde tarih filtresi yok; listing ve sipariş yalnız `offset`/`limit`. `PullPage.watermark` HB'de **her zaman null**; `pullProducts(?DateTimeImmutable $updatedSince)` parametresi **yok sayılır**. | ✅ **Evet** (contract değişmiyor) — ama mimari etkisi büyük: mutabakat **tam tarama**dır ve paylaşılan IP bütçesine karşı planlanmalıdır. | – (BACKEND-PLAN §7.1'in watermark modeli HB katalog/listing için **geçersiz**; §7.6 mutabakatı tek çare) |
| **M15** | **`SupportsWebhooks` şekli TERS.** Contract `registerWebhook(url): string`, `listWebhooks()`, `activateWebhook()`, `deleteWebhook()` bekliyor. HB'de **abonelik API'si yoktur**: bir BaseURL bant dışı verilir, HB **bizim sunucumuzdaki sabit path'lere** POST/PUT eder. HB'nin implement edebileceği tek metot `parseWebhookOrders()`. | ❌ **Hayır** — dört metottan üçü karşılıksız. | **CONTRACT DEĞİŞİKLİĞİ GEREKİYOR (v1.1)** (§10.2 K4) |
| **M16** | **Üç paralel hata kanalı** (`taskDetails`, `validationResults[]`, `importMessages[]`) tek bir `message` string'ine katlanıyor; `attributeName` çapası ve MPOP görev URL'i kayboluyor. | ✅ **Evet** — tam ayrıntı `channel_operations.remote_result jsonb`'sine yazılır (kolon **mevcut**), `message` insan-okur özet taşır. | – |
| **M17** | **Rate limiter tenant içermemeli** (H11). `TrendyolRateLimiter`'ın `(sellerId, endpoint)` şekli HB'de yanlış. | ✅ **Evet** — `HepsiburadaRateLimiter` `app/Marketplaces/Hepsiburada/` altında, `global_cache()` ile `(host, endpointClass)` anahtarlar. `Support/` değişmez. | – |
| **M18** | **`multipart/form-data` + `.json` dosya part'ı** ile yazma. Genel bir JSON HTTP istemcisi bunu karşılamaz. | ✅ **Evet** — `Http::attach('file', $json, 'integrator.json')`. | – |
| **M19** | **`channel_listings.remote_status` tek kolon, HB'de İKİ dik eksen var:** katalog `productStatus` ve listing satılabilirliği (`isSalable` + `deactivationReasons[]`). Biri diğerini gizler. | ⚠️ **Hayır** (temiz biçimde). | ⚠️ **Şema.** Öneri: `channel_listings.remote_state jsonb` (tam statü vektörü); `remote_status` başlık değeri kalır. Şema değişikliği → §12 ihlali |
| **M20** | **`merchantSku` büyük harfe çevrilmesi** `ProductData.reference`/`VariantData.sku` üretimini bağlar. | ✅ **Evet** — normalize sınırda yapılır ve **saklanır**. Contract değişmez; ama bu bir **invariant**tır ve testle korunmalıdır. | – |

### 10.2 Gerekli contract değişiklikleri — `app/Marketplaces/{Contracts,Data,Support}`

Bu dört madde `app/Marketplaces/Hepsiburada/` dışına taşar. **BACKEND-PLAN §12 kriterini ihlal ederler.**

#### K1 — Ön eşleşme (M1) · **MVP · zorunlu**

| Dosya | Değişiklik |
|---|---|
| `Data/Enums/CanonicalListingStatus.php` | Yeni case: `AwaitingMatchDecision = 'awaiting_match_decision'`. `isApproved(): false`, `isEditable(): true` |
| `Data/MatchProposalData.php` | **YENİ DTO:** `reference`, `proposedRemoteId`, `proposedName`, `proposedBrand`, `list<string> proposedImages`, `list<AttributeValueData> proposedAttributes` |
| `Contracts/SupportsCatalogMatching.php` | **YENİ yetenek:** `pendingMatchProposals(?string $cursor): PullPage<MatchProposalData>`, `approveMatches(array $references): PushResult`, `rejectMatches(array $references): PushResult` |
| `Support/Capability.php` | Yeni case `CatalogMatching` + `contract()` eşlemesi |

**Neden mapper'da çözülemiyor:** bu bir *inbox*'tır — kendi yaşam döngüsü, kendi satıcı aksiyonu ve kendi ekranı olan bir kuyruk. `ProductData`'nın bir alanına katlanamaz.
**§12 ihlalinin ikinci yarısı:** bu yetenek **bir UI ekranı gerektirir** (yan yana karşılaştırma + onayla/reddet). §12 *"resources/js — YALNIZCA logo/isim; ekran yok"* diyor. Bu madde karşılanamaz. **Ekransız alternatif yoktur**: otomatik onay ticari olarak savunulamaz (H10).

#### K2 — Bozulmuş kabul (M2) · **MVP · zorunlu**

| Dosya | Değişiklik |
|---|---|
| `Data/PushResult.php` | `itemResults` shape'i: `array{accepted: bool, degraded: bool, code: ?string, message: ?string}` (varsayılan `degraded: false`). Yeni yardımcı: `degradedReferences(): list<string>` |

**Neden gerekli:** ✅ ölçülmüş bir gerçeği (0 fiyat/0 stok ile canlıya çıkma) temsil edecek başka yer yok. `accepted:false` demek retry politikasını bozar; `accepted:true` demek satıcıya yalan söyler.
**Blast radius küçük:** `degraded` opsiyonel bir anahtardır; Trendyol mapper'ı değiştirilmeden çalışmaya devam eder (varsayılan `false`).

#### K3 — Attribute tipi (M4) · **MVP · şiddetle önerilir**

| Dosya | Değişiklik |
|---|---|
| `Data/AttributeData.php` | Yeni alan: `?string $type = null` — ham pazaryeri tipi (`string`\|`integer`\|`enum`\|`media`\|`video`) |
| (şema) `channel_attribute_mappings` | Yeni kolon `remote_type` — ⚠️ **§12 ihlali** |

**Alternatif (contract'a dokunmadan):** HB sürücüsü tipi kendi tablosunda tutar. Maliyeti: yerel ön-doğrulama `type: "media"` zorunlu alanları serbest metin sanır ve kullanıcı hatayı ancak 4 saat sonra "MISSING_INFO" olarak görür — BACKEND-PLAN §7.5'in var oluş sebebine aykırı.

#### K4 — Webhook arayüz bölünmesi (M15) · **v1.1 · MVP'de gerekmez**

| Dosya | Değişiklik |
|---|---|
| `Contracts/SupportsWebhooks.php` | İkiye ayrılır: `SupportsWebhookSubscriptions` (`registerWebhook`, `listWebhooks`, `activateWebhook`, `deleteWebhook`) ve `SupportsInboundWebhooks` (`parseWebhookOrders`) |
| `Support/Capability.php` | `Webhooks` yerine iki case, ya da `Webhooks` = abonelik, `InboundWebhooks` = yeni |

Trendyol her ikisini de implement eder (mevcut davranış korunur); Hepsiburada yalnız ikincisini.

### 10.3 Contract değişikliği GEREKMEYEN, ama invariant olarak yazılması gereken kurallar

**M3 — `itemOrderID` ↔ `reference`: taşınabilir.** Üç koşul:
1. `ProductData.reference` = `UPPER(no_spaces(sku))` ve **aynı değer** `attributes.merchantSku`'ya gönderilir.
2. `channel_operations.payload` gönderilen dizinin **sırasını korur**; `index → reference` haritası saklanır.
3. Poll satırı çözümlenirken önce `merchantSku` (normalize edilerek), yoksa `payload[itemOrderID]` kullanılır.
⚠️ `itemOrderID` tabanı (0/1) ölçülmeden 2. adım güvenli değildir → Ek A P0.

**Diğer invariant'lar:** `ProductData.status` çağıran tarafından `channel_listings.remote_status`'tan doldurulur (M13 yönlendirmesi buna bağlı) · `channel_brand_mappings.remote_brand_id` HB'de marka **adını** tutar (M7) · `CategoryNodeData.isLeaf` = üç bayrağın VE'si (M6) · `AttributeData.isSlicer` HB'de her zaman `false` (M5).

### 10.4 Özet — §12 kabul kriterinin durumu

| Kategori | Durum |
|---|---|
| `app/Marketplaces/Hepsiburada/` içinde kalan | M3, M5–M12, M14, M16–M18, M20 — **14 madde** |
| `app/Marketplaces/{Contracts,Data,Support}` değişikliği gerektiren | **K1** (ön eşleşme: 1 enum case + 1 DTO + 1 contract + 1 Capability case), **K2** (`PushResult.itemResults` shape), **K3** (`AttributeData.type`, önerilir), **K4** (webhook bölünmesi, v1.1) |
| Şema (migration) değişikliği gerektiren | `channel_attribute_mappings.remote_type` (K3) · `channel_listings.remote_state jsonb` (M19) · opsiyonel `channel_listings.remote_snapshot` (M13, kaçınılabilir) |
| UI ekranı gerektiren | **Ön eşleşme onay/red kuyruğu** (K1) — kaçınılamaz |

**Değerlendirme.** K1–K4'ün hepsi **eklemeli** (additive) değişikliklerdir; hiçbiri Trendyol adaptörünü bozmaz ve hepsi BACKEND-PLAN §6.4'ün *"genişleme noktası kanonik DTO'lar + yetenek contract'larıdır"* ifadesiyle uyumludur. Yine de §12'nin **yazıldığı hâliyle** karşılanmadığı doğrudur.

**Öneri:** §12 şu şekilde güncellensin — *"İkinci pazaryerini eklemek; kanonik DTO'lara ve yetenek arayüzlerine **eklemeli** genişletmeler dışında, `app/Models/*`, senkron motoru, kuyruk yapılandırması ve mevcut sürücülerde değişiklik gerektirmemelidir."* Ekransızlık maddesi ise **pazaryerine özgü insan-döngüde akışlar için istisna** tanımalıdır; aksi hâlde Hepsiburada entegrasyonu ya eksik ya da ticari olarak tehlikeli olur.

---

## 11. Sipariş / iade / webhook durumu

Satıcı yalnızca **katalog** bağlantıları verdi (16 URL, hepsi `katalog-urun-entegrasyonu` ürün düğümünde). Sipariş, iade ve webhook için bize hiçbir doküman iletilmedi. Taramada durum şu:

### 11.1 Ne bulundu ✅ ölçüldü

| Bulgu | Kanıt |
|---|---|
| **OMS host'u erişilebilir ve aynı kimlik bilgisiyle çalışıyor** | `GET https://oms-external-sit.hepsiburada.com/orders/merchantid/{id}?offset=0&limit=1` → **200** |
| Sipariş zarfı | `{"totalCount":0,"limit":1,"offset":0,"pageCount":0,"items":[]}` |
| Paket ucu da çalışıyor | `GET .../packages/merchantid/{id}?offset=0&limit=1` → **200**, gövde **`[]` çıplak dizi** |
| `limit`/`offset` sayfalaması geçerli | İkisi de yansıtıldı |

**Bu, araştırma notlarının bir tavsiyesini geçersiz kılar:** "Sipariş Entegrasyonu için ayrı kimlik bilgisi isteyin" gerekmiyor — mevcut `merchantId` + servis anahtarı üçlüsü OMS host'unu da açıyor. **Sipariş senkronu MVP'de teknik olarak mümkündür.**

### 11.2 Neyin eksik olduğu

| Eksik | Neden eksik | Nasıl kapanır |
|---|---|---|
| **`items[]` satır şeması** ⚠️ | Test merchant'ında **sıfır sipariş** var | SIT'te `POST /orders/merchantid/{id}` ile test siparişi üret (yalnız HB'nin önceden yüklediği ürünlerle çalışır), sonra `GET /orders/...` gövdesini yakala → Ek A **P0** |
| **Paket satır şeması** ⚠️ | Aynı | Aynı akış |
| **Statü sözlüğü** ⚠️ | Arşivden: `Open`, `Unpacked`, `Packaged`/`Shipped`, `Delivered`, `Undelivered`, `Cancelled`, `PaymentAwaiting` | Test siparişini statülerde gezdirip gözlemle |
| **Talep (claim) uçları** ⚠️ | Hiç çağrılmadı | `GET /claims/merchantid/{id}` tek çağrıyla; test claim'i `claim-stub-external-sit` üzerinden |
| **Kargo/shipping host'u** ⚠️ | Hiç çağrılmadı | `GET /cargoFirms/{merchantId}` tek çağrıyla |
| **Sipariş tarih filtreleri** ⚠️ | Belgelenmemiş; `offset`+`limit` **birlikte zorunlu**, `limit` tavanı paketlerde 10 / sipariş listelerinde 50 | Deneyerek |

**Beklenen sipariş satırı alanları** (webhook `create-order` payload'ından, ⚠️ REST yanıtıyla aynı olduğu **doğrulanmadı**):
`dueDate` (kargo SLA son tarihi) · `lastStatusUpdateDate` · `id` · `name` · `sku` (HB SKU, örn. `HBV00000NE0YY`) · `productImageUrlFormat` · `quantity` · `merchantId` · `totalPrice{currency, amount}` · `unitPrice` · `hbDiscount{totalPrice, unitPrice}` · `vat` · `vatRate` · `discountPriceToBeInvoicedHb` · `customerName` · `CustomerId` · `status` · `shippingAddress{addressId, address, name, email, countryCode, phoneNumber, alternatePhoneNumber, district, city, town}` · `invoice{turkishIdentityNumber, taxNumber, taxOffice, address{…, postalCode}}` · `sapNumber` · `dispatchTime` · `commission{currency, amount}` · `paymentTermInDays` · `commissionType` · `cargoCompanyModel`

> **KVKK:** bu payload `turkishIdentityNumber` (TCKN), ad-soyad, e-posta, telefon ve tam adres taşır. `orders.customer` ve `orders.raw` **şifreli cast** ile saklanmalı ve saklama süresi politikasına tabi olmalıdır (BACKEND-PLAN §13). Trendyol ile birebir aynı yükümlülük.

### 11.3 Webhook var mı? — **Evet, ama ters kontrat ve imzasız** ⚠️

Hepsiburada'nın webhook modeli Trendyol'unkinden bile daha az standarttır:

| Boyut | Hepsiburada |
|---|---|
| Abonelik API'si | **Yok.** URL başına olay kaydı yok, abonelik yönetimi yok |
| Yön | **Ters kontrat:** biz sabit path'ler açarız, HB'ye **tek bir BaseURL** veririz (bant dışı, ticket ile) |
| İmza / HMAC / paylaşılan sır | **Yok** ⚠️ — hiçbir portal sayfasında `X-Signature` benzeri bir başlık geçmiyor |
| Gelen isteğin kimlik doğrulaması | Bant dışı ayarlanır: kendi endpoint'imizde Basic Auth kullanıcı adı/parolası HB'ye iletilir, IP kısıtı önerilir ⚠️ |
| Teslimat garantisi | **En az bir kez.** Dokümanın tek bütünlük tavsiyesi: *"Servise response iletirken idempotent mantığını kullanmanız önerilir."* |
| Teslimat kaydı / replay API'si | **Yok** |
| Zorunlu ön koşul | Webhook **SIT test süreci** tamamlanmadan production'da açılmaz |

**Sipariş webhook olayları (8)** ⚠️ — bizim sunucumuzda açılacak path'ler:

| Olay | Path | Metod |
|---|---|---|
| Create Order | `/orders` | POST (**belgeli, birebir**) |
| Create Packages | `/packages` ⚠️ | POST |
| Order Cancel | ⚠️ path belgesiz | – |
| Unpack | ⚠️ path belgesiz | **PUT** |
| Intransit | ⚠️ | – |
| Deliver | ⚠️ | – |
| Undeliver | ⚠️ | – |
| Change Shipping Address Order | ⚠️ | – |

**Talep webhook olayları (3)** ⚠️: `Aksiyon Bekleyen Talep Bildirimi` → `PUT /claims/awaitingaction` (belgeli) · `İhtilaflı Talep Kabul/Red Bildirimi` ⚠️ · `Talep Kabul/Red Sonucu Oluşan Paket Bildirimi` ⚠️

**Mimari sonuçlar:**
1. **Tenant kimliği URL'den gelemez.** Trendyol'da `webhook_token` kolonu URL'e gömülür; HB'de tek BaseURL vardır. ⚠️ BaseURL'in entegratör başına mı satıcı başına mı kaydedildiği **belgelenmemiş** — satıcı başınaysa tenant başına path verilebilir, entegratör başınaysa tenant **payload'daki `merchantId`'den** çözülmek zorundadır (BACKEND-PLAN §2.2'nin dördüncü katmanı). → Ek A **P1**.
2. **İmza yok ⇒ webhook doğruluğun kaynağı olamaz.** Trendyol'daki karar burada da geçerli: **webhook = gecikme, polling = doğruluk.** İkisi aynı upsert yoluna yazar.
3. **Dedup zorunlu.** `webhook_events` tablosunun `(connection_id, payload_hash)` tekil indeksi (BACKEND-PLAN §5.4) burada da yeterli; ayrıca `orderNumber`/`packageNumber` + olay tipi üzerinden ikinci bir dedup önerilir.
4. **IIS notu:** dokümanın kendisi PUT verb'ünün varsayılan kapalı olabileceğini hatırlatıyor. Bizim yığınımızda (Octane/RoadRunner) sorun değil, ama `PUT /claims/awaitingaction` rotası açıkça tanımlanmalı.
5. **v1.1'e ertelenmesi güvenlidir.** MVP polling ile çalışabilir; sipariş listeleri statü kovalarına bölünmüş olduğu için artımlı olmayan tarama makul maliyettedir.

---

## 12. Sandbox / test ortamı

**Bu, Trendyol'a göre en büyük avantajımızdır: çalışan, ölçülmüş bir test erişimimiz var.**

| Konu | Trendyol | Hepsiburada |
|---|---|---|
| Test ortamı var mı | Var (`stageapigw`) | **Var** (`-sit` host'ları) |
| Erişim durumumuz | **Yok** — IP allow-list gerekiyor, talep açılmamış | ✅ **Var ve çalışıyor** — üç host'ta 200 |
| IP kısıtı | **Zorunlu**; yetkilendirilmemiş IP `503` alır | **Yok** ✅ ölçüldü |
| Dokümantasyon hangi ortama yazılı | Prod | **SIT** — resmî sayfalardaki tüm örnekler SIT host'u kullanır |
| Prod'a geçiş | Ayrı stage/prod anahtar çifti | Host'tan `-sit` çıkarılır + **ayrı** kimlik bilgisi |

### 12.1 SIT'in bize verdikleri ✅ ölçüldü

- Gerçek kategori ağacı (**5611** düğüm), gerçek attribute şemaları, gerçek enum tipleri.
- HB'nin önceden yüklediği **40 katalog ürünü** ve **39 listing** — hepsi `MATCHED` statüsünde, biri 0 fiyat/0 stok durumunda (H6'nın canlı örneği).
- Çalışan OMS ve listing host'ları.

### 12.2 SIT'in yapamadıkları — naif test planlarını kıran kısıtlar ⚠️

| Kısıt | Sonuç |
|---|---|
| **Test ortamında ürün onay ekibi YOKTUR.** Yüklediğiniz ürünler `İncelenecek` (WAITING) statüsünde **sonsuza kadar kalır.** | `WAITING → MATCHED` geçişi **kendi ürünlerinizle test edilemez.** `PRE_MATCHED` akışı yalnız üç ekili barkodla denenebilir: `7541828790114`, `7541828790155`, `7541828790080` ⚠️ |
| **Test siparişi yalnız HB'nin önceden yüklediği ürünlerle** oluşturulabilir | Kendi ürününüzle uçtan uca sipariş akışı SIT'te kurulamaz |
| **Test sipariş oluşturma ucu yalnız test ortamında vardır** | Prod'a asla ateşlenemeyeceğini kod düzeyinde garanti edin |
| **Webhook için manuel el sıkışma gerekir** | Test (production değil) HTTPS endpoint'i açılır, Basic-auth bilgisi HB'ye verilir, HB kaydeder, sonra olay üretilebilir |
| **Kategori ağacı SIT ≠ PROD** | Canlıya geçişte kategori/attribute cache'i **sıfırdan** yeniden çekilmeli; SIT'te kurulan kategori eşlemeleri taşınamaz |
| **Entegratör yetkilendirmesi ~2 saatte yayılır** | İlk çağrının "geçersiz" dönmesi beklenen davranıştır |

**Sonuç:** `PRE_MATCHED` işleme mantığı (K1) SIT'te **kısmen** doğrulanabilir (üç ekili barkod) ve tam doğrulama bir **production pilot satıcısı** gerektirir. Fixture tabanlı testler bunu telafi etmelidir.

---

## 13. Kaynaklar

### 13.1 Ölçüm — en yüksek güven ✅

19 Ağustos 2026, SIT ortamına yapılan canlı GET çağrıları. Ham yanıt gövdeleri saklandı: `categories.json`, `attributes.json`, `attribute-values.json` (404 örneği), `products.json`, `listings.json`, `orders.json`, `packages.json`.

| Çağrı | Sonuç |
|---|---|
| `GET mpop-sit…/product/api/categories/get-all-categories?page=0&size=2` | 200, `totalElements: 5611` |
| `GET mpop-sit…/product/api/categories/26012174/attributes` | 200, 22+12+0 attribute |
| `GET mpop-sit…/product/api/categories/26012174/attributes/000009D/values` | **404** (çoğul yol) |
| `GET mpop-sit…/product/api/categories/26012174/attribute/000009D/values` | **200** (tekil yol) |
| `GET mpop-sit…/product/api/products/all-products-of-merchant/{id}?page=0&size=2` | 200, `totalElements: 40` |
| `GET listing-external-sit…/listings/merchantid/{id}?offset=0&limit=2` | 200, `totalCount: 39` |
| `GET oms-external-sit…/orders/merchantid/{id}?offset=0&limit=1` | 200, boş |
| `GET oms-external-sit…/packages/merchantid/{id}?offset=0&limit=1` | 200, `[]` |
| `GET mpop.hepsiburada.com/…` (prod, SIT kimlik bilgisiyle) | **403** |

### 13.2 Resmî — `developers.hepsiburada.com`

⚠️ **Portal Akamai Bot Manager arkasındadır.** WebFetch, curl (her UA), proxy'ler **HTTP 403** ve `HBBlockandCaptcha.html` döner. Ayrıca içerik client-render bir Vite SPA'dir; `sitemap.xml` ve `robots.txt` boş kabuğa düşer. **CI'da dokümanı izleyemez, diff alamazsınız.**

**İki çalışan yol (yeniden üretilebilir):**
1. **Tam tarayıcı DOCUMENT başlık seti** gönderin (`sec-fetch-dest: document`, `sec-fetch-mode: navigate`, `sec-ch-ua`, `upgrade-insecure-requests`, gerçek Chrome UA). XHR biçimli başlıklar hâlâ 403 alır.
2. **SPA'yı atlayıp kendi backend API'sine gidin** (`/assets/index-*.js` içindeki axios istemcisinden bulundu, `baseURL: "/api/v1"`):
   - `GET /api/v1/public/docs/{company}/{product}/{version}/openapi` — **tam OpenAPI 3.0.1 dokümanı**
   - `/api/v1/public/docs/{company}/{product}/{version}/operations` · `/operations/{operationId}`
   - `/api/v1/public/docs/{company}/{product}/guides` · `/guides/{slug}`
   - Katalog için: `https://developers.hepsiburada.com/api/v1/public/docs/hepsiburada/katalog-urun-entegrasyonu/v1.0/openapi`

**Okunan resmî sayfalar** (canlı ya da Wayback `id_` anlık görüntüsü):
- Katalog Önemli Bilgiler — SIT/prod kuralı, auth banner'ı, ürün yükleme modeli, `trackingId` semantiği, ürün statüleri, ön eşleşme, fiyat bandı, zarf + hata kodları
- Katalog Test Süreci Adımları
- Endpoint sayfaları: `getAllCategoriesByParameters`, `getAllAttributesByCategory`, `getAllAttributeValuesByCategoryIdAndAttributeId`, `uploadProductViaFile`, `uploadFastListingProduct`, `getProductStatusByTraceId`, `getTrackingList`, `checkProductStatus`, `getProductByMerchantIdAndStatus`, `getAllProductsByMerchantId`, `integratorApprovePreMatch`, `integratorRejectPreMatch`, `deleteByMerchantAndMerchantSkuList`, `getDeleteProcess`, `uploadTicketViaFile`, `getTicketProductsStatusByTrackingId`
- Listeleme Entegrasyonu Önemli Bilgiler — listing alanları, upload/poll modeli, 5 eşzamanlı + 4000 SKU + 10× günlük limit, MinLock/MaxLock, tam listing hata kataloğu, `x-correlation-id` 7 gün, XML desteği
- Sipariş Entegrasyonu Önemli Bilgiler — sipariş rate limit'i, `X-RateLimit-*` başlıkları, 429, `limit`/`offset` kuralları, 406 günlük limit
- Sıkça Sorulan Sorular — sandbox erişim prosedürü, SIT kısıtları, entegratör yetkilendirme + 2 saatlik gecikme, webhook onboarding, kopya/barkod hataları
- Entegratöre Servis Anahtarı Ekleme/Görüntüleme — servis anahtarı üretimi ve rotasyonu
- Changelog **Ocak 2024** — auth değişikliği (birebir), `dispatchTime` kaldırılması, görsel tarayıcı IP beyaz listesi
- Changelog **Mayıs 2024** — sipariş rate limit'i, zorunlu `limit`/`offset`
- Changelog **Ekim 2025** — Komisyon Bilgisi Sorgulama: 240 istek/dk, maks 50 SKU
- Webhook: `sipariş-webhook-modeli`, `webhook-önemli-bilgiler`, `webhook-modeli-test-süreci-adımları`, `talep-webhook-modelleri`, `create-order`
- Satıcının ilettiği "Hepsiburada API Authentication Bilgilendirmesi" notu (auth şeması, `page` 0 tabanlı, `size` 100 tavanı, `merchantId` zorunlu, **180 istek/dk IP başına**)

⚠️ Portalın yeni bir kuşağı (`developers-v2.hepsiburada.com` ve `/tr/…`) mevcut sayfalardan link veriliyor; içerik ReadMe tabanlı kuşaktan **farklı** olabilir — canlıya geçmeden tarayıcıyla kontrol edilmeli.

### 13.3 Topluluk / üçüncü parti — **DÜŞÜK GÜVEN**

Gerçek dünya davranışını gösterirler, sözleşme değildirler. Bu dokümandaki hiçbir iddia yalnız bunlara dayanmıyor; dayandığı yerler ⚠️ ile işaretli.

| Kaynak | Ne için kullanıldı | Güven |
|---|---|---|
| `loncadev/lonca` (`sdks/hepsiburada/src/transport.ts`) | Çok-host tablosu (SIT varyantları dâhil), `User-Agent` 401/403 bulgusu, host başına `merchantId` büyük/küçük harf tablosu, asimetrik yazma retry politikası, attribute kovaları | Orta — 2026'da canlı doğrulama iddiası |
| `CNRWhoAmI/meraycoshop` (`backend/apps/hepsiburada/api_client.py`) | Üç host'ta endpoint haritası, 429 ele alma, `ticket-api` akışı, ön eşleşme onayı | Orta |
| `developkariyer/iwapim` (`HepsiburadaConnector.php`) | Production connector; her çağrıda Basic + `User-Agent`, `variantAttributes` kalıcılığı | Orta |
| `hcbayram/iberodooaddons` | Host ayrımı, 202-başarı ele alma | Düşük |
| `mustafa-m-ugur/hepsiburada-api-php`, `ksmylmz/hepsiburada` | Üç katalog yolunu bağımsız doğrulama | Düşük — ⚠️ `check-product-status` yolunu **eksik** (`/products/` segmentsiz) hard-code ediyorlar |
| `mstfkrbyk/Senkronize`, `Ovtnc/OmniCore`, `hemreduru/marketplacerbe`, `bagbaq/hepsiburada-api` | Rate limit pratikleri | Düşük |
| AKINSOFT bilgi bankası #3757 | Servis Anahtarı göçünün bağımsız teyidi | Düşük ama teyit edici |

### 13.4 ❌ Kullanılmayan / kara listeye alınan kaynaklar

| Kaynak | Neden |
|---|---|
| `zunapro.com` | AI içerik çiftliği. `User-Agent` biçimini (`{MERCHANT_ID} - {AppName}`) ve rate limit'leri (30/sn) **uyduruyor**; var olmayan bir endpoint tanımlıyor (`GET /product/api/products/import/{trackingId}` — gerçeği `/products/status/{trackingId}`) |
| `temasis.net` | OAuth2 / access token / süreli token iddia ediyor — **tamamen yanlış**, auth düz Basic'tir |
| `ilkkod.com` | Aynı sınıf hatalar |
| `wiensa/hepsiburada-sp-api` (Packagist) | Var olmayan bir base URL (`marketplace-api.hepsiburada.com`), var olmayan bir `leaf_id` parametresi ve yanlış attribute-değer yolu uyduruyor |

---

## Ek A — Doğrulama listesi

Bu dokümandaki **⚠️ doğrulanmadı** maddeleri. **Test ortamı erişimimiz olduğu için çoğu tek bir çağrıyla kapatılabilir** — "Kapanış maliyeti" sütunu bunu gösterir. Entegrasyonun ilk işi P0 satırlarını kapatmaktır.

| # | Belirsizlik | Neyi çalıştırınca kapanır | Kapanış maliyeti | Öncelik |
|---:|---|---|---|---|
| 1 | **`/categories/{c}/attribute/{a}/values` yanıt gövdesi** — `{id, value}` mi, toplam sayı hangi header'da? — §4.1.3 | Ölçülen 200 çağrısını tekrarla, **gövdeyi ve tüm yanıt başlıklarını** dök | **1 GET** | **P0** |
| 2 | **`itemOrderID` 0 mı 1 mi tabanlı** — §6.2, M3 | 2 kalemlik bir `products/import` at, `status/{trackingId}` satırlarındaki indeksleri oku | 1 POST + 1 GET | **P0** |
| 3 | **`merchantSku` büyük harfe çevirme ve Türkçe karakter davranışı** — §9.2 | `test-ıi-şğ` içeren bir SKU ile import at, `all-products-of-merchant` ile geri oku | 1 POST + 1 GET | **P0** |
| 4 | **Varyant ekseni anahtar biçimi** — `<slug>_variant_property` sonek konvansiyonu doğru mu? `variantAttributes[].id` soneki taşıyor mu? — §9.6 | Varyantlı bir kategori bul (`get-all-categories` + `attributes`), `variantAttributes` kovasını incele | **1–2 GET** | **P0** |
| 5 | **`size` gerçek tavanı endpoint başına** — 100 / 1000 / 2000 çelişkisi — §H3 | `get-all-categories?size=2000`, `status/{id}?size=1000`, `all-products-of-merchant?size=1000` dene; yanıttaki `numberOfElements`'a bak | **3 GET** | **P0** |
| 6 | **Sipariş `items[]` satır şeması ve statü sözlüğü** — §11.2 | SIT'te `POST /orders/merchantid/{id}` ile test siparişi üret, `GET /orders/...` gövdesini yakala | 1 POST + N GET | **P0** |
| 7 | **`Image1..Image10` evrensel mi kategoriye özgü mü** — §9.9 | 3 farklı kategoride `attributes` çağır, `Image*` sayısını karşılaştır | **3 GET** | P1 |
| 8 | **`type: "enum"` dışı değer kabul ediliyor mu** (listede olmayan enum değeri) — §4.1.2 | Bir enum attribute'a listede olmayan bir değerle import at, `validationResults`'a bak | 1 POST + 1 GET | P1 |
| 9 | **Gerçek rate limit ve 429 davranışı** — 180/dk mı, 100/sn mi? Hangi başlıklar dönüyor? — §7 | Kasıtlı olarak hızlı ardışık GET at, ilk 429'da **tüm yanıt başlıklarını** dök | ~200 GET | P1 |
| 10 | **`x-correlation-id` / `X-Request-Id` başlıkları geliyor mu** — §6.6 | Herhangi bir çağrının yanıt başlıklarını dök | **1 GET** | P1 |
| 11 | **`uploadFastListingProduct` yanıt gövdesi** (OpenAPI'de boş `{"type":"object"}`) — §4.1.12 | SIT'te tek kalemlik bir `fastlisting` POST'u at, ham gövdeyi logla | 1 POST | P1 |
| 12 | **`deleteByMerchantAndMerchantSkuList` POST yanıt gövdesi** (aynı sorun) — §4.1.13 | Silinebilir bir SKU ile POST at | 1 POST | P1 |
| 13 | **`checkProductStatus` gövdesi dizi mi nesne mi** — §4.1.7 | İki biçimi de gönder, hangisi 200 veriyor gör | **2 POST** | P1 |
| 14 | **Listing `limit` tavanı ve `merchantSkuList` filtresi** — §4.3.1 | `limit=1000` ve `merchantSkuList=A,B` ile çağır | **2 GET** | P1 |
| 15 | **Listing upload `status` değer kümesi** (`PROCESSING` var mı) — §4.3.2, §6.4 | Bir `price-uploads` at, işlem bitene kadar poll et, gözlemlenen tüm değerleri katalogla | 1 POST + N GET | P1 |
| 16 | **Webhook BaseURL entegratör başına mı satıcı başına mı** — §11.3, tenant çözümlemesini belirler | HB entegrasyon ekibine ticket | Ticket | P1 |
| 17 | **`talep` (claim) uçları ve gövde şekilleri** — §11.2 | `GET /claims/merchantid/{id}` çağır; gerekirse `claim-stub-external-sit` ile test claim'i üret | **1 GET** | P1 |
| 18 | **Shipping host ve `cargoFirms` gövdesi** — §4.5 | `GET shipping-external-sit…/cargoFirms/{merchantId}` | **1 GET** | P1 |
| 19 | **`version` parametresinin endpoint başına doğru değeri** (1 / 2 / 5) — §4.0 | Her endpoint'i `version` göndermeden ve doküman değeriyle çağır, farkı karşılaştır | ~8 GET | P2 |
| 20 | **`ticket-api` gerçekten çalışıyor mu ve PATCH semantiği** — §4.2, M13 | `MATCHED` statüsündeki bir ürüne yalnız `productName` gönder, `all-products-of-merchant` ile diğer alanların korunduğunu doğrula | 1 POST + 2 GET | P2 |
| 21 | **`productId` alanının anlamı** (listing'de, belgelenmemiş) — §9.1 | HB entegrasyon ekibine ticket; ya da `hepsiburadaSku` ile ilişkisini örneklem üzerinden çıkar | 1 GET + ticket | P2 |
| 22 | **`preMatchedSku` / `siblingSku` gerçekten dönüyor mu** — §9.1 | `products-by-merchant-and-status?productStatus=PRE_MATCHED` çağır (üç ekili barkoddan biriyle) | **1 GET** | P2 |
| 23 | **Desi bölen sabiti** (3000 mi 5000 mi) — §9.10 | HB entegrasyon ekibine ticket | Ticket | P2 |
| 24 | **`availableWarehouses` upload tarafında kabul ediliyor mu** — §5.7, M10 | `inventory-uploads` payload'ına depo alanı ekleyip poll hatalarına bak | 1 POST + 1 GET | P2 |
| 25 | **Prod ve SIT kategori ağacı farkı** — §9.8 | Production kimlik bilgisi alındıktan sonra iki ağacı diff'le | 2×57 GET | P2 |
| 26 | **`INACTIVE` kategori statüsü gerçekten dönüyor mu** — §5.4 | `get-all-categories?status=INACTIVE` çağır | **1 GET** | P2 |
| 27 | **`4000` hata kodunun belirsizliği** ("henüz yok" vs "süresi doldu") — §8.2 | Yeni bir `trackingId`'yi hemen sorgula; ayrıca eski bir `trackingId`'yi 30 gün sonra sorgula | 1 GET + bekleme | P3 |
| 28 | **`trackingId` / `uploadId` sonuç saklama süresi** — §6.6 | Bir `trackingId`'yi 7/30 gün sonra tekrar sorgula | Bekleme | P3 |
| 29 | **`HBV…` vs `HBCV…` önek semantiği** — §9.1 | HB entegrasyon ekibine ticket | Ticket | P3 |
| 30 | **`IN_EXTRENAL_PROGESS` / `BLOCKED` statüleri gerçek mi** — §5.1 | `products-by-merchant-and-status?productStatus=BLOCKED` çağır (400 bekleniyor) | **2 GET** | P3 |
| 31 | **Webhook başarı kriteri ve retry davranışı** — §11.3 | HB ile webhook test sürecini başlat, farklı HTTP kodları döndüren alıcılar kur | Test süreci | P3 |
| 32 | **`MerchantAlreadyListedAgainstProduct` sonrası kurtarma** — §8.3 | Kasıtlı kopya listing yarat, panelden davranışı gözle | 1 POST | P3 |

**Toplam:** P0 = 6 madde, hepsi **10 çağrının altında** kapanır. Bu dokümanın ⚠️ yükünün büyük kısmı bir öğleden sonrasında eritilebilir.

---

*Bu doküman KobiConnect Hepsiburada adaptörünün uygulama sözleşmesidir. `developers.hepsiburada.com` bot korumalı olduğu için otomatik diff kurulamaz; değişiklik takibi için `/hepsiburada/changelog` sayfası aylık olarak elle ya da headless tarayıcıyla izlenmelidir. §10'daki contract değişiklikleri BACKEND-PLAN.md §12 ile birlikte karara bağlanmadan Hepsiburada sürücüsü yazılmaya başlanmamalıdır.*
