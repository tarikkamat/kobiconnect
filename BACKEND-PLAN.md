# KobiConnect — Backend Mimari Planı

> Durum: taslak · Hedef: production-ready · Laravel 13.26.1 · PHP 8.4 · PostgreSQL · Octane (RoadRunner)
> Kardeş dokümanlar: [`TRENDYOL.md`](./TRENDYOL.md) (pazaryeri API sözleşmesi) · [`FRONTEND-PLAN.md`](./FRONTEND-PLAN.md)

---

## 0. Ürün tanımı ve mimari kabul kriteri

KobiConnect, KOBİ'ler için **çok-pazaryeri entegrasyon platformudur**: tek envanter, tek panel, N pazaryeri. İlk adaptör Trendyol.

Bu mimarinin tek gerçek kabul kriteri şudur:

> **İkinci pazaryerini eklemek, `app/Marketplaces/<YeniPazaryeri>/` klasörü dışında kod değişikliği gerektirmemelidir.**

Katalog, sipariş, envanter, senkron motoru, kuyruk yapısı, UI — hiçbiri Trendyol'u tanımaz. Bu belgedeki her karar bu kritere göre alınmıştır. §12'de bu kriterin somut kontrol listesi var.

### Bugünkü durum

Depo çıplak bir Laravel 13 React starter kit + kurulmuş ama **hiç bağlanmamış** `stancl/tenancy v3.10.1`. Domain kodu sıfır. `database/migrations/tenant/` boş. Üç canlı hata var (§1).

### Kilitlenen kararlar

| Karar | Seçim | Gerekçe |
|---|---|---|
| Tenant izolasyonu | PostgreSQL **schema-per-tenant** | Tek fiziksel DB, tek connection pool (Octane dostu), gerçek izolasyon, `pg_dump -n tenant_x` ile tenant bazlı export |
| Auth konumu | **Tenant DB'de** users/sessions/passkeys | KVKK izolasyonu net, koltuk limiti lisanstan gelir |
| Arama | **PostgreSQL native FTS** (`tsvector` + GIN + `pg_trgm`) | Ek servis yok, tenant izolasyonu bedava, SQL filtreleriyle tek sorguda birleşir |
| Yeni paketler | `laravel/horizon`, `laravel/pennant`, `spatie/laravel-permission` | Kuyruk görünürlüğü, plan bazlı feature gate, tenant içi rol yönetimi |

---

## 1. Faz 0 — Zemin düzeltme

Bunlar **bloke edici**. Hiçbir domain kodu bunlar düzelmeden yazılmamalı.

### 1.1 Canlı hatalar

| # | Sorun | Düzeltme |
|---|---|---|
| 1 | `app/Models/Tenant.php` `namespace App;` deklare ediyor ama `app/Models/` altında. `class_exists('App\Models\Tenant')` → `false`, `config/tenancy.php` ise onu referans veriyor. **Her tenant işlemi fatal verir.** | Namespace'i `App\Models` yap |
| 2 | `TenancyServiceProvider::mapRoutes()` `routes/tenant.php`'yi `app->booted()` içinde, yani `web.php`'den *sonra* yükler. İkisi de `GET /` tanımlıyor → tenant kazanıyor. `route('home')` → `RouteNotFoundException`, `tests/Feature/ExampleTest.php` **şu an kırık**. | Route yükleme sırasını `bootstrap/app.php`'de açıkça yönet; central ve tenant route'larını domain'e göre ayır (§2.2) |
| 3 | `tenants` / `domains` migration'ları hiç çalışmamış | PG'ye geçişle birlikte çalıştır |

### 1.2 Yapılandırma hataları

| # | Sorun | Düzeltme |
|---|---|---|
| 4 | **Tenant cache bugün bozuk.** `CacheTenancyBootstrapper` prefix değil **tag** kullanıyor (`CacheManager::__call` → `store()->tags(...)`). `CACHE_STORE=database` taggable değil. | `CACHE_STORE=redis` |
| 5 | `REDIS_CLIENT=phpredis` ama `ext-redis` yüklü değil, sadece `predis/predis` var | `REDIS_CLIENT=predis`, **veya** `ext-redis` kur. `RedisTenancyBootstrapper` phpredis'e bağımlı (`\Redis::OPT_PREFIX`) — predis ile o bootstrapper kullanılamaz (bize gerekmiyor, cache ve queue yeterli) |
| 6 | `config/tenancy.php` `central_connection` → `env('DB_CONNECTION','central')` → `sqlite`; `central` diye bir connection tanımlı değil | `config/database.php`'e açık `central` connection ekle, `central_connection => 'central'` sabitle |
| 7 | `User` `MustVerifyEmail` implement etmiyor ama `verified` middleware aktif → `mustVerifyEmail` prop'u hep `false` | `User implements MustVerifyEmail` |
| 8 | `config/inertia.php` SSR açık, `resources/js/ssr.tsx` yok | SSR'ı kapat (bu bir panel, SEO gerekmiyor) — bkz. `FRONTEND-PLAN.md` |
| 9 | `laravel/chisel` `require` içinde — starter-kit artığı, runtime'da gereksiz | Kaldır |

### 1.3 Hedef `.env`

```dotenv
DB_CONNECTION=pgsql          # tenant şemaları buraya
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kobiconnect
DB_SEARCH_PATH=public

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_DOMAIN=.kobiconnect.test   # subdomain'ler arası cookie paylaşımı
REDIS_CLIENT=predis

OCTANE_SERVER=roadrunner
APP_LOCALE=tr
APP_FALLBACK_LOCALE=tr
```

`config/database.php`'e `central` connection'ı (aynı PG, `search_path=public`) eklenir. Tenant connection'ı stancl tarafından runtime'da üretilir — adı `tenant`, bu isim **rezerve**.

### 1.4 Test ve CI

- `phpunit.xml` şu an `DB_DATABASE=:memory:` sqlite zorluyor. **Tenant şeması `:memory:`'de yaratılamaz.** Gerçek bir PG test veritabanına geçilecek.
- `.github/workflows/tests.yml`'a `services: postgres, redis` eklenecek. CI'da bugün hiç servis yok.
- `pg_trgm` ve `unaccent` extension'ları test kurulumunda da yaratılmalı.

---

## 2. Tenancy mimarisi

### 2.1 Schema-per-tenant

`config/tenancy.php` içinde tek satır değişir:

```php
'managers' => [
    'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
],
```

`PostgreSQLSchemaManager` `CREATE SCHEMA "tenant<uuid>"` / `DROP SCHEMA ... CASCADE` çalıştırır ve connection config'ine `search_path = <schema>` yazar. Central şema `public`.

**Central şemada** (`public`): `tenants`, `domains`, `plans`, `plan_features`, `licenses`, `license_events`, `usage_counters`, `jobs`, `job_batches`, `failed_jobs`.
**Tenant şemasında**: geri kalan her şey (§5).

Central modellerine `Stancl\Tenancy\Database\Concerns\CentralConnection` trait'i konur — böylece tenant context'indeyken yanlışlıkla tenant şemasında aranmazlar. Tenant modellerine hiçbir şey konmaz; schema izolasyonu zaten hallediyor (`BelongsToTenant` / global scope **gerekmiyor** — bu, single-DB yaklaşımının yükü).

### 2.2 Tenant tanımlama — dört katman

Uygulama **tek host'ta** yaşar: `app.kobiconnect.com`. `kobiconnect.com` ayrı bir landing projesidir ve buraya hiç uğramaz.

| Katman | Middleware | Kullanım |
|---|---|---|
| `app.kobiconnect.com` kökü | — | Kayıt/onboarding, workspace seçici |
| `app.kobiconnect.com/{tenant}/…` | `InitializeTenancyByPath` | Panel (web) |
| `X-Tenant` başlığı | `InitializeTenancyByRequestData` | Genel API |
| Webhook URL'inde opak token | Özel `InitializeTenancyByWebhookToken` | Pazaryeri callback'leri |

**Tenant id'si aynı zamanda URL slug'ıdır.** `PathTenantResolver` `tenancy()->find($id)` ile çözer — `domains` tablosuna **bakmaz**. Bu yüzden tenant `id`'si onboarding'de kullanıcının seçtiği workspace slug'ıdır (`[a-z0-9-]`), UUID değil. `UUIDGenerator` yalnızca id verilmediğinde devreye giren bir yedek olarak kalır (`GeneratesIds` yalnızca `! $model->getKey()` iken üretir).

Prefix `bootstrap/app.php`'deki grup sarmalayıcısında uygulanır; `routes/tenant.php` prefix'ten habersizdir. `InitializeTenancyByPath` tenant'ın route'un **ilk** parametresi olmasını şart koşar, prefix bunu garanti eder. `PathTenantResolver::resolved()` parametreyi `forgetParameter()` ile düşürür, böylece controller imzalarına sızmaz.

⚠️ **Tek host = tek session cookie.** Bir kullanıcı aynı tarayıcıda aynı anda yalnızca **tek** tenant'ta oturum açabilir. `ScopeSessions` middleware'i bu yüzden opsiyonel değil **zorunludur**: session'a `_tenant_id` yazar ve başka bir tenant'ın path'ine geçildiğinde 403 verir. Subdomain modelinde bu bir tercihti, path modelinde bir güvenlik gereğidir.

⚠️ Bilinmeyen bir slug varsayılan olarak `TenantCouldNotBeIdentifiedByPathException` fırlatır ve **500** döner. `InitializeTenancyByPath::$onFail` 404'e, `InitializeTenancyByRequestData::$onFail` 400'e bağlanmıştır (`TenancyServiceProvider::boot()`).

**Neden webhook için özel bir katman:** Trendyol bize keyfi başlık gönderemez. Webhook URL'i tenant'ı ve bağlantıyı taşımak zorunda:

```
POST {webhook_base_url}/{marketplace}/{connection_token}
```

Taban `config('marketplaces.webhook_base_url')`'den gelir (`WEBHOOK_BASE_URL`). Geliştirmede uygulama host'unun altında bir path'tir (`…/hooks/trendyol/{token}`) — ek DNS gerekmez; production'da ayrı bir host'a işaret edilebilir. Host üzerinde string oynaması **yapılmaz**.

`connection_token` = tahmin edilemez, bağlantı başına üretilen (48 karakter rastgele), iptal edilebilir bir dize. Tenant **ve** bağlantı çözümü bundan yapılır. Pazaryeri segmenti URL'i kendi kendini açıklar hâle getirir ve tek bir handler'ın hangi adaptöre yönleneceğini belirler.

⚠️ **`InitializeTenancyByRequestData` `OPTIONS` isteklerinde tamamen atlar** (CORS preflight). API'de CORS kullanılacaksa preflight'ın tenant'sız çalışacağı bilinmeli.

Production'da `PathTenantResolver::$shouldCache = true` açılacak (varsayılan kapalı) — her istekte tenant lookup sorgusu atılmasın diye. Invalidasyon `InvalidatesResolverCache` ile.

### 2.3 Octane güvenlik ağı — atlanamaz

`stancl/tenancy` v3.10'da **sıfır Octane farkındalığı** vardır (`grep -ri 'octane\|swoole\|roadrunner' vendor/stancl/` → boş). Sonuçları:

- `Tenancy` bir **singleton**; `$tenant` ve `$initialized` yanıt gönderildikten sonra da yaşar.
- HTTP isteği sonunda `tenancy()->end()` çağıran **hiçbir şey yoktur**.
- Worker'da tenant A ile biten bir istekten sonra gelen **central** istek hâlâ tenant A context'inde çalışır. (Farklı bir tenant gelirse `initialize()` önce `end()` çağırdığı için o senaryo güvenli; tehlikeli olan central istektir.)
- `CacheTenancyBootstrapper` `$app->extend('cache', ...)` yapar, `FilesystemTenancyBootstrapper` `$app->useStoragePath()` çağırır. Octane'in config sandbox'ı `config` dizisini geri alır ama **uygulama nesnesindeki bu mutasyonları geri almaz**.

Yapılacaklar:

1. **Terminating middleware + `RequestTerminated` listener** koşulsuz `tenancy()->end()` çağırır.
2. `config/octane.php` `flush` listesine `cache` ve `cache.store` eklenir.
3. `DisconnectFromDatabases` listener'ı açılır (şu an yorum satırında).
4. `FilesystemTenancyBootstrapper::$originalPaths` ilk resolve anında yakalanır — ilk resolve bir tenant aktifken olursa "orijinal" yol sonsuza dek tenant yolu olur. Warm-up sırasında central context garanti edilmeli.

> Bu maddeyi atlarsanız, üretimde bir tenant'ın verisinin başka bir tenant'ın isteğinde görünmesiyle sonuçlanan, tekrarlanması zor bir hata alırsınız. Bu bir "nice to have" değildir.

### 2.4 Tenant provisioning

`TenancyServiceProvider` içindeki `TenantCreated` JobPipeline **senkron** çalışır (`shouldBeQueued(false)`):

```php
JobPipeline::make([
    Jobs\CreateDatabase::class,      // CREATE SCHEMA
    Jobs\MigrateDatabase::class,     // tenants:migrate
    Jobs\SeedDatabase::class,        // tenants:seed -> TenantRoleSeeder
])->send(fn (TenantCreated $e) => $e->tenant)->shouldBeQueued(false);
```

**Neden kuyruğa alınmadı:** kayıt hayatta bir kez yapılan bir istektir. Senkron akış bir "hazırlanıyor" durum makinesini, polling ekranını ve "tenant var ama şeması yok" yarışını tamamen ortadan kaldırır. `Tenant::save()` döndüğünde şema, tablolar ve roller hazırdır; sahip kullanıcı aynı istekte yaratılabilir.

**Ne zaman gözden geçirilmeli:** tenant migration sayısı kayıt isteğini kabul edilemez hâle getirdiğinde. O gün `shouldBeQueued(true)` + pipeline sonuna `CreateOwnerUser` job'ı gerekir. `RegisterTenant::createOwner()` şemayı `Schema::connection('tenant')->hasTable('users')` ile yoklar; kuyruğa geçildiği anda sessiz bir SQL hatası yerine bunu söyleyen açık bir `RuntimeException` alırsınız.

**Atomiklik:** gerçek bir DB transaction mümkün değildir (şema oluşturma ve migration ayrı PDO bağlantılarındadır). Telafi (compensation) kullanılır: herhangi bir adım patlarsa `$tenant->delete()` → `TenantDeleted` → `DROP SCHEMA CASCADE`; `domains` ve `licenses` FK cascade ile gider.

⚠️ `config/tenancy.php` → `seeder_parameters` içinde **`'--force' => true` şarttır**. `tenants:seed` `ConfirmableTrait` kullanır; production'da `--force` olmadan sessizce hiçbir şey yapmaz, roller kurulmaz ve her kayıt telafiyle geri alınır.

`CreateDatabase` işi, `$tenant->getInternal('create_database') === false` ise **tüm pipeline'ı iptal eder** — test kurulumlarında bu bilinmeli.

`Stancl\Tenancy\Features\TenantConfig` ve `CheckTenantForMaintenanceMode` açılır. `ScopeSessions` middleware'i panel route'larına eklenir (bir tenant'ın session'ı başka tenant'ta geçersiz olsun).

---

## 3. Lisans modeli

**Bir tenant = bir lisans.** Lisans central şemada yaşar, tenant'ın kendisi ona dokunamaz.

### 3.1 Tablolar (central)

| Tablo | İçerik |
|---|---|
| `plans` | `code`, `name`, `price`, `billing_period`, `is_public` |
| `plan_features` | `plan_id`, `feature`, `value` (jsonb — bool/limit/enum) |
| `licenses` | `tenant_id`, `plan_id`, `status`, `seats`, `limits` (jsonb), `starts_at`, `ends_at`, `grace_until`, `cancelled_at` |
| `license_events` | Denetim izi: aktivasyon, plan değişimi, askıya alma, yenileme |
| `usage_counters` | `tenant_id`, `metric`, `period`, `value` — kota ölçümü |

`limits` jsonb örneği:

```json
{
  "channels.max": 3,
  "channels.allowed": ["trendyol", "hepsiburada"],
  "products.max": 10000,
  "orders.per_month": 5000,
  "seats.max": 5,
  "sync.interval_minutes": 15
}
```

### 3.2 Uygulama

- **Feature kapıları**: `laravel/pennant`, tenant scope'lu. `Feature::for($tenant)->active('channel.hepsiburada')`.
- **Kotalar Action seviyesinde kontrol edilir**, middleware'de değil. Middleware "lisans aktif mi" der; "1000 üründen fazlasını ekleyemezsin" kararı ürün oluşturma Action'ında verilir çünkü orada anlamlı bir hata mesajı üretilebilir.
- `EnsureLicenseIsActive` middleware'i tüm panel route'larına uygulanır. Süresi dolmuş ama grace period içindeyse **salt-okunur mod**: okuma serbest, yazma 402 döner, UI banner gösterir. Kritik nokta: **grace period'da senkron durur ama veri silinmez** — müşteri ödediğinde kaldığı yerden devam eder.
- `sync.interval_minutes` gibi limitler doğrudan scheduler'ı besler; ucuz plan daha seyrek senkron olur.

### 3.3 Olaylar

`LicenseActivated` · `LicenseRenewed` · `LicenseExpiring` (7/3/1 gün önce) · `LicenseExpired` · `LicenseSuspended` · `QuotaExceeded` · `QuotaWarning` (%80)

Hepsi `license_events`'e yazılır ve §11'deki bildirim zincirini tetikler.

---

## 4. Auth'un tenant'a taşınması

Starter kit auth'u (Fortify + passkey + 2FA) bugün tamamen central. Taşınacak.

### 4.1 Ne nereye

**Tenant şemasına taşınan migration'lar:** `users`, `sessions`, `password_reset_tokens`, `passkeys`, `roles`/`permissions` (spatie).

**Central'da kalan:** kayıt/onboarding akışı (tenant + lisans + sahip kullanıcı yaratır), şifre sıfırlama yönlendiricisi ("hangi paneldesiniz?"), pazarlama sayfaları.

### 4.2 Passkey — tek host'un sonucu

WebAuthn'de **Relying Party ID host'a bağlıdır.** Uygulama tek host'ta (`app.kobiconnect.com`) yaşadığı için RP ID **tüm tenant'lar için aynıdır** ve `config/fortify.php`'de statik durur:

```php
'passkeys' => [
    'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
    'allowed_origins'  => [config('app.url')],
],
```

> Not: subdomain modelinde RP ID tenant'a çivilenebiliyordu ve passkey doğal olarak tenant'a kapanıyordu. Path modelinde bu koruma **yoktur**; aşağıdaki handle ayrımı onun yerini alır.

⚠️ **`user_handle` çakışması — sessiz veri kaybı riski.** Paket varsayılanı (`PasskeyAuthenticatable::getPasskeyUserHandle()`) handle'ı şöyle türetir:

```php
hash_hmac('sha256', $this->getTable().'|'.$this->getKey(), $secret, binary: true)
```

Kullanıcılar tenant şemasında yaşadığı için **her tenant'ta `users.id = 1` vardır**. RP ID de ortak olduğuna göre tenant A'nın 1 numaralı kullanıcısı ile tenant B'nin 1 numaralı kullanıcısı **aynı handle'ı** üretir. Authenticator bunları tek bir WebAuthn hesabı sanar; aynı (RP ID, user handle) çifti için resident credential **üzerine yazılabilir** — yani B'de passkey kaydetmek A'nınkini sessizce silebilir.

Bu, çapraz tenant *girişine* yol açmaz (kimlik bilgisi araması tenant şemasındaki `passkeys` tablosunda yapılır, A'nın credential'ı B'de yoktur) ama gerçek bir veri kaybıdır.

Çözüm — `App\Models\User` içinde handle tenant ile nitelenir:

```php
public function getPasskeyUserHandle(): string
{
    return hash_hmac(
        'sha256',
        $this->getTable().'|'.(string) tenant()?->getTenantKey().'|'.$this->getKey(),
        (string) config('passkeys.user_handle_secret'),
        binary: true,
    );
}
```

`PASSKEYS_USER_HANDLE_SECRET` açıkça tanımlanmıştır; tanımsız bırakılırsa `APP_KEY`'e düşer ve `APP_KEY` rotasyonu **tüm passkey'leri geçersiz kılar**.

### 4.3 Roller

`spatie/laravel-permission` tenant şemasında. Başlangıç rolleri:

| Rol | Yetki özeti |
|---|---|
| Sahip | Her şey + faturalama + tenant silme |
| Yönetici | Her şey, faturalama hariç |
| Depo | Stok, sipariş hazırlama, kargo |
| Muhasebe | Fatura, finans raporları, salt-okunur katalog |
| Müşteri Temsilcisi | Sorular, iadeler, sipariş görüntüleme |

Yetkiler `HandleInertiaRequests::share()` ile frontend'e taşınır. **UI'da aksiyonlar gizlenmez, devre dışı bırakılır** — kullanıcı neyi yapamadığını görmeli.

---

## 5. Kanonik veri modeli

Tüm tablolar tenant şemasında. Kanonik = pazaryerinden bağımsız. Trendyol'a özgü hiçbir alan bu tablolarda yer almaz; pazaryeri detayları `channel_*` tablolarında ve `raw` jsonb sütunlarında yaşar.

### 5.1 Katalog

```
brands              (id, name, slug, created_at, ...)
categories          (id, parent_id, name, path ltree, position)
attributes          (id, code, name, type, is_variant_defining)
attribute_values    (id, attribute_id, value, position)

products            (id, ulid, name, description, brand_id, category_id,
                     status, attributes jsonb, search_vector tsvector, ...)
product_variants    (id, product_id, sku, barcode, attributes jsonb,
                     weight, dimensions jsonb, vat_rate, hs_code, ...)
product_images      (id, variant_id NULL, product_id, url, checksum, position)

warehouses          (id, name, code, is_default, address jsonb)
inventory_items     (id, variant_id, warehouse_id, on_hand, reserved,
                     available GENERATED, safety_stock)
prices              (id, variant_id, currency, list_price, sale_price, cost,
                     valid_from, valid_to)
```

**PG kasları:**

```sql
-- Tek doğruluk kaynağı: satılabilir stok hesaplanır, yazılmaz
available integer GENERATED ALWAYS AS (on_hand - reserved) STORED

-- Türkçe arama: unaccent + ağırlıklı tsvector
search_vector tsvector GENERATED ALWAYS AS (
  setweight(to_tsvector('turkish', unaccent(coalesce(name,''))),        'A') ||
  setweight(to_tsvector('turkish', unaccent(coalesce(description,''))), 'B')
) STORED
CREATE INDEX products_search_gin ON products USING gin (search_vector);

-- SKU/barkod için typo toleranslı arama
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX variants_sku_trgm ON product_variants USING gin (sku gin_trgm_ops);
```

#### ⚠️ İki tuzak — uygulamada doğrulandı

**1. `search_path` `public` içermez.** `PostgreSQLSchemaManager::makeConnectionConfig()` `search_path`'i **yalnızca** tenant şemasına ayarlar. Doğrulandı:

```
tenancy()->initialize($t);  →  show search_path  →  "tenantvercheck"
```

Sonuç: extension'lar (`unaccent`, `pg_trgm`) `public`'te yaşadığı için tenant şemasından görünmezler. Her public obje **şema nitelemeli** yazılmalı: `public.unaccent`, `public.gin_trgm_ops`.

Bu davranış aslında bir güvenlik özelliğidir ve **böyle bırakılmalıdır**: `public` search_path'te olsaydı, tenant şemasında bulunmayan bir tabloya yapılan sorgu sessizce central tabloya düşerdi. Şimdi hata veriyor — istediğimiz de bu.

**2. `unaccent()` IMMUTABLE değildir**, dolayısıyla generated column'da doğrudan kullanılamaz. Çözüm, tenant şeması başına IMMUTABLE bir sarmalayıcı:

```sql
CREATE FUNCTION f_unaccent(text) RETURNS text
LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$
```

**Bunun sonucu: `whereFullText()` KULLANILAMAZ.** Laravel'in `whereFullText`'i arama terimini `f_unaccent`'ten geçirmez; indekslenmiş `sarj` ile aranan `şarj` eşleşmez. Sorgu simetrik olmak zorunda:

```php
#[Scope]
protected function search(Builder $query, string $term): void
{
    $query->whereRaw(
        "search_vector @@ websearch_to_tsquery('turkish', f_unaccent(?))",
        [$term],
    );
}
```

> `$table->virtualAs()` PG 18+ gerektirir. **PG 16/17'de `storedAs()` kullanılacak.**
> `$table->index($col, $name, 'gin'|'brin')` Blueprint'ten çalışır; `gin_trgm_ops` gibi operator class'lar ve partial index'ler ham `DB::statement` gerektirir.

### 5.2 Kanal

```
channel_connections  (id, marketplace, name, credentials jsonb ENCRYPTED,
                      external_seller_id, status, settings jsonb,
                      field_overrides jsonb, webhook_token,
                      last_health_check_at, capabilities jsonb)

channel_listings     (id, connection_id, variant_id, remote_id,
                      remote_status, remote_payload_hash, sync_state,
                      last_pushed_at, last_pulled_at, error jsonb)

channel_category_mappings         (connection_id, category_id, remote_category_id, remote_path)
channel_attribute_mappings        (connection_id, remote_category_id, attribute_id,
                                   remote_attribute_id, is_required, allow_custom,
                                   allow_multiple, is_varianter, is_slicer)
channel_attribute_value_mappings  (mapping_id, attribute_value_id, remote_value_id)
channel_brand_mappings            (connection_id, brand_id, remote_brand_id)

channel_price_rules  (connection_id, scope, markup_type, markup_value, round_to)
channel_stock_rules  (connection_id, scope, allocation_type, allocation_value, buffer)
```

`channel_stock_rules` "tek envanter, N kanal" vaadinin kalbidir: paylaşılan `available` stoktan her kanala ne kadar tahsis edileceğini belirler (yüzde, sabit tavan, tampon düşülmüş kalan).

**Index'ler:**
```sql
UNIQUE (connection_id, variant_id)   -- bir varyant bir kanalda bir kez listelenir
UNIQUE (connection_id, remote_id)    -- uzak kimlik çakışmasın
```
Soft-delete varsa `nulls not distinct` kullanılır (PG 15+, Laravel `->nullsNotDistinct()` destekliyor) — partial index yazmaya gerek kalmaz.

### 5.3 Sipariş ve satış sonrası

```
orders               (id, connection_id, remote_order_number, remote_id,
                      status, customer jsonb ENCRYPTED, totals jsonb,
                      currency, placed_at, raw jsonb)
order_lines          (id, order_id, variant_id NULL, remote_line_id, sku, barcode,
                      quantity, unit_price, discounts jsonb, commission,
                      vat_rate, status)
shipment_packages    (id, order_id, remote_package_id, cargo_provider,
                      tracking_number, tracking_link, status, deci,
                      shipped_at, delivered_at, raw jsonb)
order_status_history (id, order_id, package_id, from_status, to_status, occurred_at, source)
invoices             (id, order_id, number, link, status, sent_at)

claims               (id, order_id, remote_claim_id, status, reason, opened_at, raw jsonb)
claim_items          (id, claim_id, order_line_id, quantity, status, reason)
questions            (id, connection_id, remote_id, product_id NULL, body,
                      status, asked_at, answered_at, answer)
```

`order_lines.variant_id` **nullable**: pazaryerinden eşleşmeyen bir barkod gelebilir. Bu satırlar "eşleşmemiş sipariş satırları" kuyruğuna düşer ve UI'da operatöre gösterilir. **Sipariş asla eşleşme yüzünden reddedilmez** — sipariş verisi kaybedilmez.

`orders.customer` ve `orders.raw` KVKK kapsamındadır: Trendyol payload'ı `customerTckn`, ad-soyad, e-posta ve tam adres taşır. Şifreli cast + saklama süresi politikası zorunlu (§13).

### 5.4 Altyapı

```
sync_runs         (id, connection_id, resource, direction, cursor_from, cursor_to,
                   started_at, finished_at, stats jsonb, status, error jsonb)
sync_cursors      (connection_id, resource, watermark, cursor, updated_at)  UNIQUE(connection_id, resource)

channel_operations (id, connection_id, entity_type, entity_id, operation,
                    desired_state jsonb, payload jsonb, payload_hash,
                    idempotency_key, status, attempts,
                    remote_batch_id, remote_result jsonb,
                    scheduled_at, sent_at, completed_at, error jsonb)

webhook_events    (id, connection_id, marketplace, external_ref, headers jsonb,
                   payload jsonb, payload_hash, received_at, processed_at,
                   status, error jsonb)

idempotency_keys  (key, tenant_id, user_id, endpoint, request_hash,
                   response_status, response_body jsonb, locked_at, expires_at)

notification_preferences (user_id, event, channels jsonb)
activity_log             (id, causer_id, subject_type, subject_id, event, properties jsonb, created_at)
```

**`channel_operations` bu mimarinin en önemli tablosudur** (§7.2). Index'ler:

```sql
-- Aynı bekleyen iş iki kez kuyruğa girmesin
CREATE UNIQUE INDEX channel_ops_pending_uniq
  ON channel_operations (connection_id, idempotency_key)
  WHERE status IN ('pending', 'in_flight');

-- Drenaj sorgusu
CREATE INDEX channel_ops_drain
  ON channel_operations (connection_id, status, scheduled_at)
  WHERE status = 'pending';
```

Partial index'ler ham `DB::statement` gerektirir — Laravel Blueprint'inde `where()` yok.

**`webhook_events` dedup:**
```sql
CREATE UNIQUE INDEX webhook_events_dedup ON webhook_events (connection_id, payload_hash);
```

**Partitioning bilinçli olarak ertelenmiştir.** `webhook_events` ve `sync_runs` için `Illuminate\Database\Eloquent\MassPrunable` + `received_at` üzerinde BRIN index birkaç yüz milyon satıra kadar yeter. Partitioning'in gerçek kazancı `DROP PARTITION` vs `DELETE`'tir; `DELETE` darboğaz olduğunda eklenir, önce değil. Laravel'de `PARTITION BY` desteği yok, ham SQL gerekir ve partition anahtarı her unique key'e girmek zorundadır — bugün ödenmeyecek bir karmaşıklık.

---

## 6. Marketplace driver mimarisi

Bu bölüm §0'daki kabul kriterinin karşılığıdır.

### 6.1 Yerleşim

```
app/Marketplaces/
├── Contracts/
│   ├── MarketplaceDriver.php          # kimlik, capabilities(), client()
│   ├── SupportsProductSync.php
│   ├── SupportsInventorySync.php
│   ├── SupportsPriceSync.php
│   ├── SupportsOrderSync.php
│   ├── SupportsShipmentUpdates.php
│   ├── SupportsClaims.php
│   ├── SupportsQuestions.php
│   ├── SupportsCategoryCatalog.php
│   ├── SupportsBrandCatalog.php
│   └── SupportsWebhooks.php
├── Data/                              # kanonik readonly DTO'lar (PHP 8.4)
│   ├── ProductData.php  VariantData.php  PriceData.php  StockData.php
│   ├── OrderData.php    OrderLineData.php  ShipmentData.php
│   ├── ClaimData.php    QuestionData.php
│   ├── CategoryNodeData.php  AttributeData.php  BrandData.php
│   ├── PushResult.php   PullPage.php   MappingContext.php
│   └── Enums/  CanonicalOrderStatus.php  CanonicalClaimStatus.php  SyncState.php
├── Support/
│   ├── MarketplaceManager.php         # Illuminate\Support\Manager
│   ├── Capability.php                 # enum
│   └── Exceptions/
└── Trendyol/
    ├── TrendyolDriver.php
    ├── TrendyolClient.php
    ├── Mappers/  ProductMapper.php  OrderMapper.php  ClaimMapper.php  QuestionMapper.php
    ├── Enums/    PackageStatus.php   ClaimStatus.php  (her biri ->toCanonical())
    └── Webhooks/ TrendyolWebhookHandler.php
```

### 6.2 Yetenek arayüzleri

Bir sürücü **yalnızca gerçekten desteklediği** arayüzleri implement eder:

```php
final class TrendyolDriver implements
    MarketplaceDriver,
    SupportsProductSync, SupportsInventorySync, SupportsPriceSync,
    SupportsOrderSync, SupportsShipmentUpdates,
    SupportsClaims, SupportsQuestions,
    SupportsCategoryCatalog, SupportsBrandCatalog,
    SupportsWebhooks
{ }
```

Bu, hem UI'ın (bir kanal soru-cevap desteklemiyorsa o menü çıkmaz) hem de scheduler'ın (desteklenmeyen kaynak için senkron job'ı planlanmaz) tek bilgi kaynağıdır. `instanceof` kontrolü tek karar noktasıdır — `if ($marketplace === 'trendyol')` **hiçbir yerde geçmez**.

### 6.3 Mapper sözleşmesi

```php
interface Mapper
{
    /** Uzak payload → kanonik DTO */
    public function toCanonical(array $remote): object;

    /** Kanonik DTO → uzak payload */
    public function toRemote(object $canonical, MappingContext $context): array;
}
```

`MappingContext`, çözümlenmiş kategori/attribute/marka eşlemelerini ve bağlantı ayarlarını taşır. Böylece mapper'lar **saftır**: veritabanına gitmezler, test edilmeleri kolaydır, round-trip testleri yazılabilir.

### 6.4 Bilinçli karar: field-map DSL yazmıyoruz

Config'den sürülen genel bir alan eşleme motoru (`['name' => 'title', 'sku' => ['stockCode', 'trim']]` gibi) **yazılmayacaktır.**

**Gerekçe:** Böyle bir motor, ikinci pazaryerini eklemeyi kolaylaştırdığı iddiasını taşır ama pratikte bunu yapmaz. Pazaryerleri arasındaki gerçek farklar alan adı eşlemesi değildir — Trendyol'un attribute'larının create'te tekil, update'te dizi olması; onaylı/onaysız ürünün iki ayrı model olması; 15 dakikalık dedup penceresi gibi **davranışsal** farklardır. Bir DSL bunları ifade edemez, sonunda DSL'in içine kaçış kancaları eklenir ve elinizde hem DSL hem de özel kod kalır.

**Genişleme noktası şudur:** kanonik DTO'lar + yetenek contract'ları. İkinci pazaryeri = aynı contract'ları implement eden yeni bir klasör. Açık, tipli, okunabilir, IDE'nin anladığı PHP.

**Kaçış kapısı:** tenant seviyesindeki %10'luk sapma (bir müşteri kendi SKU'sunu farklı üretmek istiyor) `channel_connections.field_overrides` jsonb'sinden okunur ve `MappingContext` üzerinden mapper'a geçer. Bu, mimariyi bozmayan sınırlı bir esnekliktir.

### 6.5 Kanonik statü eşlemesi

Her pazaryeri enum'u kendi `toCanonical()` metodunu taşır. Trendyol paket statüleri (yetkili küme: `Awaiting, Created, Picking, Invoiced, Shipped, Cancelled, Delivered, UnDelivered, Returned, AtCollectionPoint, UnPacked, UnSupplied`) → `CanonicalOrderStatus`. Tam tablo `TRENDYOL.md` §5'te.

Kritik: `Awaiting` **ödeme onayı beklemede** demektir. Trendyol bu siparişlerin gönderilmesi hâlinde sorumluluk kabul etmiyor. Kanonik modelde ayrı bir `PENDING_PAYMENT` durumu olmalı ve **stok rezervasyonu dışında hiçbir işlem tetiklememeli**.

---

## 7. Senkron motoru

### 7.1 Pull — watermark tabanlı artımlı senkron

- Kaynak başına imleç `sync_cursors`'ta tutulur.
- `WithoutOverlapping` job middleware'i `connection:{id}:resource:{name}` anahtarıyla eşzamanlı çalışmayı engeller.
- Sayfa fan-out'u `Http::pool()` / `Http::batch()` ile. **`Concurrency::run()` kullanılmayacak**: `fork` sürücüsü web isteğinde exception fırlatır, `process` sürücüsü görev başına tam framework boot eder. HTTP fan-out için `Http::batch()` doğru primitiftir ve `before/progress/catch/then/finally` geri çağrılarıyla kısmi başarısızlığı raporlar.
- Sipariş tarafında **`getShipmentPackagesStream` (cursor) hakikat kaynağıdır**. 1 aylık geçmiş penceresi ve 10.000 kayıt sorgu tavanı, sayfa numarası tabanlı gezinmeyi imkânsız kılıyor.

### 7.2 Push — outbox zorunlu

Domain olayları doğrudan HTTP çağrısı yapmaz. `channel_operations` tablosuna bir satır yazar; bir worker onu drene eder.

```
desired_state → payload_hash → idempotency_key → [gönder] → remote_batch_id → item_result → completed
```

**`channel_operations` bir kuyruk değil, bir defterdir.** Kuyruk (Redis) işi *ne zaman* yapacağını bilir; defter *neyin* yapılması gerektiğini ve *ne olduğunu* bilir. İkisi ayrıdır çünkü Trendyol'un yanıtı gecikmelidir.

**Neden bu kadar önemli:** Trendyol'da mutasyonların tamamı asenkrondur ve **item seviyesinde kısmen başarısız olabilir**. HTTP 200 başarı anlamına gelmez — yalnızca "isteğin alındı" demektir. Gerçek sonuç `batchRequestId` ile sorgulanır ve **yalnızca 4 saat saklanır**. Bir iş, item sonucu okunana kadar açık kalır; 4 saatlik pencere kaçırılırsa iş mutabakata (§7.6) düşer.

### 7.3 Trendyol'un 15 dakikalık dedup penceresi

`updatePriceAndInventory` aynı `(barcode, değerler)` kombinasyonunu 15 dakika içinde tekrar alırsa **sessizce düşürür.**

Bu, normal retry mantığını tersine çevirir:

- ❌ Başarısız isteği aynı gövdeyle tekrar gönderme — pencere içindeyse hiçbir şey olmaz ve biz "gönderdim" sanırız.
- ✅ Retry, **istenen durumu yeniden hesaplayarak** yapılır. Bu arada stok değiştiyse yeni değer gider (ve dedup tetiklenmez); değişmediyse gönderim zaten gereksizdir.
- ✅ Son gönderilen `(barcode, değerler)` hash'i + zaman damgası saklanır; pencere içinde aynı değer tekrar gönderilmez.

`#[DebounceFor(seconds: 60, maxWait: 300)]` job attribute'ü (Laravel 13'te yeni) push işlerinde kullanılır: "ürün 10 saniyede 40 kez değişti" senaryosunda tek push yapılır. Aynı varyant için bekleyen operasyonlar gönderimden önce birleştirilir (coalesce) — bu hem bizim verimliliğimiz hem Trendyol'un kuralı için gerekli.

### 7.4 Onaylı / onaysız ayrımı

Trendyol'da onaylı ve onaysız ürün **iki ayrı veri modelidir**, iki ayrı güncelleme yolu ve iki ayrı sonuç izleyicisi vardır:

| | Onaysız | Onaylı |
|---|---|---|
| Güncelleme | `updateUnapprovedProducts` | `updateContentBulk` / `updateVariantBulk` / `updateDeliveryInfoBulk` |
| Sonuç | `getBatchRequestResult` | `getBatchRequestResult` **+** `getUpdateAudits` (ikinci QC aşaması) |

⚠️ **Attribute payload şekli create ve update'te farklıdır** ve kaynaklar çelişiyor (`TRENDYOL.md` §9). Create ve update için **ayrı serializer'lar birinci günden yazılacaktır** — bu, sonradan ayrıştırılması acı veren bir birleşimdir.

### 7.5 Yerel ön-doğrulama

Kategori attribute bayrakları (`required`, `allowCustom`, `allowMultipleAttributeValues`, `varianter`, `slicer`) ve doğrulama kodu kataloğu kullanılarak gönderimden **önce** yerel kontrol yapılır. Bu, round-trip hatalarının çoğunu anında geri bildirime dönüştürür — kullanıcı 4 saat sonra "reddedildi" görmez.

Ek kurallar: `createProducts` **yalnızca yaprak kategori** kabul eder; kategori başına **tam bir** `varianter` olabilir; `slicer` ve `varianter` onaydan sonra **değiştirilemez**.

### 7.6 Referans verisi cache ve mutabakat

**Cache** — `Cache::flexible()` (stale-while-revalidate, Redis lock gerektirir):

| Veri | TTL | Not |
|---|---|---|
| Markalar | 1 gün | Arama **büyük/küçük harf duyarlı** |
| Kategori ağacı | 7 gün | |
| Kategori attribute'ları | 7 gün | Trendyol haftalık yenilemeyi öneriyor |
| Tedarikçi adresleri | ≥1 saat | Endpoint **saatte 1 istek** kabul ediyor |

Yenileme **zamanlanmış** yapılır, talep anında değil.

⚠️ `RateLimiter` cache kullanır ve `CacheTenancyBootstrapper` altında anahtarlar tenant-tag'lenir. Trendyol'un limitleri **satıcı bazlıdır, tenant bazlı değil** — ama bir tenant'ın bir satıcı hesabı olduğu için bu örtüşür. Buna karşılık *global* bir limit (tüm tenant'lar toplamı) gerekiyorsa `global_cache()` kullanılmalıdır.

**Mutabakat (reconciliation)** — periyodik tam diff. Şunları yakalar: kaybolan webhook'lar, kaçırılan 4 saatlik batch penceresi, panelden manuel yapılan değişiklikler, ve **Trendyol'un webhook göndermediği her şey** (§8.1 — ürün/fiyat/stok için webhook yoktur, mutabakat tek çaredir).

### 7.7 Stok tahsisi ve eşzamanlılık

Tek doğruluk kaynağı `inventory_items.available` (generated column). Kanal başına push edilecek miktar `channel_stock_rules` ile hesaplanır.

Sipariş rezervasyonunda satır kilitlenir:

```php
DB::transaction(function () use ($variantId, $qty) {
    $item = InventoryItem::where('variant_id', $variantId)
        ->lockForUpdate()          // SELECT ... FOR UPDATE
        ->firstOrFail();
    // reserved += $qty
});
```

Kuyruk drenajında boşta kalan satırları atlamak gerekirse:

```php
->lock('for update skip locked')   // Laravel'de skipLocked() YOK; lock() string'i aynen geçirir
```

---

## 8. Idempotency, dedup ve webhook dayanıklılığı

### 8.1 Trendyol webhook gerçeği — varsayımları düzeltir

Üç şey mimariyi doğrudan etkiliyor:

1. **"Event type" diye bir şey yoktur.** Trendyol'un webhook modeli **yalnızca sipariş-paket statüsüdür**. `subscribedStatuses` 13 değer alır (`CREATED, PICKING, INVOICED, SHIPPED, CANCELLED, DELIVERED, UNDELIVERED, RETURNED, UNSUPPLIED, AWAITING, UNPACKED, AT_COLLECTION_POINT, VERIFIED`). **Ürün, fiyat veya stok değişikliği için webhook YOKTUR.** → Katalog tarafındaki drift yalnızca §7.6'daki mutabakatla yakalanabilir. Bu opsiyonel değildir.

2. **İmza/HMAC yoktur — yön terstir.** Trendyol *bize* kimlik doğrular: webhook kaydında verdiğimiz `BASIC_AUTHENTICATION` (username/password) veya `API_KEY` (`x-api-key` başlığı) bilgisiyle. Yani bizim endpoint'imiz **gelen kimlik bilgisini doğrular**, imza hesaplamaz.
   → Güvenlik katmanı: (a) URL'deki opak `webhook_token` tenant/bağlantı çözümü için, (b) kayıtta verdiğimiz credential'ın `hash_equals` / `Timebox` ile sabit zamanlı doğrulaması, (c) oran sınırlaması.
   → Webhook URL'i `Trendyol`, `Dolap` veya `Localhost` alt dizgilerini **içeremez**.

3. **Payload, `getShipmentPackages` ile birebir aynı tam sipariş modelidir** (aynı `{totalElements, totalPages, page, size, content:[]}` zarfı). Hangi statü tetiklerse tetiklesin tüm sipariş verisi gelir.
   → **Tek `OrderMapper` hem webhook'a hem polling'e hizmet eder.** Bu, kod tekrarını sıfırlar; mimari açıdan hoş bir hediye.

### 8.2 Idempotency — dört katman

| # | Katman | Mekanizma |
|---|---|---|
| 1 | Gelen API | `Idempotency-Key` başlığı → `idempotency_keys`'e `insertOrIgnore ... RETURNING` (Laravel 13 `compileInsertOrIgnoreReturning` ile tek round-trip). Çakışma: tamamlanmışsa saklı yanıtı replay et, uçuştaysa 409 |
| 2 | Gelen webhook | `(connection_id, payload_hash)` unique → `insertOrIgnore` **gerçekten satır eklediyse** işle. Trendyol at-least-once ve **sırasız** gönderir; sıralama payload'daki zaman damgasıyla çözülür, geliş sırasıyla değil |
| 3 | Giden çağrı | Deterministik `idempotency_key = sha256(connection|entity|op|payload_hash)` + `status IN ('pending','in_flight')` üzerinde partial unique index. **Trendyol'un idempotency key'i yoktur** — dedup tamamen bizde |
| 4 | Trendyol'un 15 dk penceresi | Son gönderilen `(barcode, değerler)` hash'i + zaman damgası; pencere içindeyse gönderim atlanır (§7.3) |

Laravel'de idempotency desteği **yoktur** (`grep -ri idempoten vendor/laravel/framework` → yalnızca `Request::isMethodIdempotent()`). Tamamen bize aittir.

### 8.3 Webhook sağlık yönetimi

Trendyol başarısız teslimatı **5 dakikada bir tekrar dener** (üstel backoff yok, maksimum deneme sayısı dokümante değil). Kalıcı başarısızlıkta webhook'u **otomatik devre dışı bırakır** ve iki e-posta gönderir. Yeniden etkinleştirme manueldir (`/activate`). Satıcı başına **maks 15 webhook** (devre dışı olanlar dahil).

Gereken:

- `getWebhooks` ile periyodik sağlık kontrolü + otomatik yeniden aktivasyon job'ı.
- Teslim başarısızlığı sayacı ve `WebhookDeliveryFailing` bildirimi (Trendyol'un e-postası satıcıya gider, bize değil — kendi izlememiz olmalı).
- 15'lik kotanın izlenmesi; yeni webhook eklemeden önce ölü kayıtların temizlenmesi.
- **Polling yedeği her zaman açık.** Trendyol'un kendi dokümanı da bunu öneriyor.

---

## 9. Rate limiting ve dayanıklılık

### 9.1 İki eksenli limitleyici

| Eksen | Anahtar | Not |
|---|---|---|
| Servis grubu | `(sellerId, serviceGroup)` | 14 Eyl 2026'dan itibaren limitler endpoint bazlı değil **grup bazlı** |
| Endpoint koruması | `(sellerId, endpoint)` | Tüm endpoint'lerde 50 istek / 10 saniye |

`updatePriceAndInventory` **14 Eyl 2026'da limitsizden 350–2000 istek/dk'ya** geçiyor. Bugün limitsiz olduğu için bugün yazılan kod yarın patlar. **Limitler config'den yönetilecek, koda gömülmeyecek.**

### 9.2 Uygulama

```php
public function middleware(): array
{
    return [
        new WithoutOverlapping("conn:{$this->connectionId}:price"),
        new RateLimited('trendyol-product-group'),
        (new ThrottlesExceptions(maxAttempts: 10, decaySeconds: 600))
            ->when(fn ($e) => $e instanceof RequestException && $e->response->status() === 429)
            ->backoff(60),
    ];
}
```

⚠️ **Rate-limit yanıt başlıkları Trendyol tarafından dokümante edilmemiştir.** `Retry-App-After` / `X-RateLimit-*` başlıklarının varlığına **güvenilmeyecek**. Backoff kendi bütçemizden hesaplanır; başlık varsa bonus olarak kullanılır, yoksa üstel backoff devreye girer.

`Illuminate\Support\Sleep` kullanılır (çıplak `sleep()` değil) — `Sleep::fake()` ile backoff davranışı test edilebilir hâle gelir.

### 9.3 HTTP istemcisi

Kendi ince tipli istemcimizi yazacağız. **Bulgu:** incelenen açık kaynak Trendyol SDK'larının hiçbiri bu entegrasyonu zorlaştıran üç şeyi (grup-farkında rate limiting, item seviyesinde batch polling, 15 dakikalık dedup) uygulamıyor; en popüler PHP istemcisi hâlâ **Mayıs 2025'te emekli edilmiş** `api.trendyol.com/sapigw` host'unu kullanıyor. Trendyol'un resmî plugin'inden *kuralları* alacağız, topluluk SDK'larından *kodu* değil.

`TrendyolClient` şunları merkezîleştirir: taban URL, Basic auth, **zorunlu `User-Agent: "{sellerId} - SelfIntegration"`** (bu başlık olmadan istek **403** alır — en yaygın sessiz hata), `Http::retry()` üstel backoff, hata zarfı ayrıştırma, `Context`'ten `request_id` başlığı ekleme.

Sayfalama URL'leri `Illuminate\Support\Uri` ile kurulur.

---

## 10. Kuyruklar ve gözlemlenebilirlik

### 10.1 Horizon supervisor'ları

| Kuyruk | Amaç | Öncelik |
|---|---|---|
| `push-inventory` | Stok/fiyat push — düşük gecikme kritik | En yüksek |
| `webhooks` | Gelen webhook işleme | Yüksek |
| `sync-orders` | Sipariş çekme | Orta |
| `sync-products` | Katalog senkronu, batch polling | Orta |
| `tenant-provisioning` | Şema yaratma + migration | Düşük, uzun timeout |
| `default` | Bildirimler, raporlar | Düşük |

⚠️ **Tenant-aware kuyruk mekaniği**: `QueueTenancyBootstrapper` payload'a top-level `tenant_id` ekler; `JobProcessing`/`JobRetryRequested`'da tenancy başlatır, `JobProcessed`/`JobFailed`'da geri alır. Central'da kalması gereken bir connection için `queue.connections.<ad>.central = true` kullanılır. (`tenancy.queue_database_creation` ve `queue.tenant_aware` config anahtarları **v3.10'da yoktur** — onlar v4 kavramlarıdır.)

### 10.2 Observability

- **Nightwatch** kurulu ve otomatik keşfedilmiş durumda. Guzzle middleware'i pazaryeri çağrılarını zaten izliyor. Yapılandırılacak: credential redaction (`redactOutgoingRequests`), gürültülü cache anahtarlarının reddi, örnekleme oranı.
- **`Context`** — `Context::addHidden('tenant_id', ...)` ve `Context::add('request_id', ...)` tanımlama middleware'inde yazılır. Bunlar **otomatik olarak** log satırlarına ve kuyruk payload'ına taşınır (`ContextServiceProvider` `Queue::createPayloadUsing` kullanır — tenancy'nin `tenant_id` enjeksiyonuyla aynı mekanizma, çakışmazlar).
- `sync_runs` tablosu operatöre dönük gerçeği tutar (Nightwatch geliştiriciye dönüktür).

---

## 11. Request identification, olaylar ve bildirimler

### 11.1 İstek kimliği

Her isteğe ULID `X-Request-Id` verilir (gelen varsa saygı gösterilir), `Context`'e yazılır, oradan log'lara, kuyruk işlerine ve **giden pazaryeri HTTP başlıklarına** taşınır. Bir müşteri "şu ürün neden gitmedi" dediğinde tek bir kimlikle uçtan uca iz sürülebilir.

### 11.2 Olay haritası

`OrderReceived` · `OrderCancelled` · `OrderLineUnmatched` · `StockCriticalLow` · `ProductRejected` · `ProductApproved` · `SyncFailed` · `ClaimOpened` · `QuestionReceived` · `WebhookDeliveryFailing` · `WebhookDeactivated` · `LicenseExpiring` · `QuotaWarning` · `ConnectionCredentialsInvalid`

Olay keşfi (`withEvents(discover: ...)`) **kapalı bırakılacak**, listener'lar açıkça kaydedilecek — 30+ listener'da yansıma tabanlı keşif hata ayıklamayı zorlaştırır. (Not: Laravel'de `#[AsListener]` gibi bir attribute **yoktur**; keşif yansıma tabanlıdır, `ShouldBeDiscovered` ile opt-out edilir.)

Veritabanı yazan listener'lar `ShouldHandleEventsAfterCommit` uygular.

### 11.3 Bildirimler

Kanallar: `database` (panel zili) · `mail` · `broadcast` (canlı). Kullanıcı başına `notification_preferences` ile olay bazlı kanal seçimi.

`ConnectionCredentialsInvalid` özel muamele görür: Trendyol'da **credential rotasyon API'si yoktur**, rotasyon panelden manueldir. Rotasyon anında kısa bir 401 fırtınası beklenir — sistem bunu kalıcı hata sanıp senkronu kapatmamalı, ama uzarsa sahibi uyarmalıdır.

---

## 12. Kabul kriteri: ikinci pazaryerini eklemek

Hepsiburada eklenirken **dokunulacak dosyalar**:

```
app/Marketplaces/Hepsiburada/          ← YENİ klasör (sürücü, istemci, mapper'lar, enum'lar)
config/marketplaces.php                ← 1 satır kayıt
database/seeders/MarketplaceSeeder.php ← 1 satır (varsa)
resources/js/…                         ← YALNIZCA logo/isim; ekran yok
tests/Feature/Marketplaces/Hepsiburada ← YENİ testler
```

**Dokunulmaması gerekenler:** `app/Models/*`, `database/migrations/tenant/*`, senkron motoru, kuyruk yapılandırması, `channel_*` tablo şemaları, hiçbir Inertia sayfası.

> Bu liste `app/Marketplaces/<Yeni>/` dışına taşıyorsa **mimari yanlıştır ve düzeltilmelidir.** Bu, bu belgenin gerçek testidir; her pazaryeri eklemesinde tekrar edilecektir.

---

## 13. KVKK ve veri saklama

Trendyol sipariş payload'ı `customerTckn`, ad-soyad, e-posta, telefon ve tam adres taşır.

- `orders.customer` ve `orders.raw` şifreli cast ile saklanır (`AsEncryptedArrayObject`).
- `identityNumber` / `taxNumber` yalnızca fatura kesimi için gerekli olduğu sürece tutulur.
- `orders.raw` ham payload'ı için saklama süresi tanımlanır ve `MassPrunable` ile uygulanır; kanonik alanlar kalır, ham PII düşer.
- Nightwatch ve log redaction politikası bu alanları kapsar.
- Tenant silindiğinde `DROP SCHEMA ... CASCADE` tüm veriyi götürür — schema-per-tenant'ın yan faydası.

---

## 14. Test stratejisi

- **Tenant-aware `TestCase`**: gerçek PG şeması yaratır, migration'ları çalıştırır, testten sonra `DROP SCHEMA CASCADE`. `RefreshDatabase` tenant şemasıyla birlikte çalışacak şekilde uyarlanır.
- **Mapper round-trip testleri**: kanonik → uzak → kanonik dönüşümünde veri kaybı olmadığı doğrulanır. Her mapper için zorunlu.
- **`Http::fake()` + gerçek fixture'lar**: `TRENDYOL.md`'deki yanıt gövdeleri fixture olarak saklanır. Uydurma payload ile test edilmez.
- **`Sleep::fake()`** ile backoff/retry davranışı assert edilir.
- **Idempotency eşzamanlılık testleri**: aynı `Idempotency-Key` ile paralel istek, aynı webhook'un iki kez teslimi, aynı push'un iki kez kuyruğa girmesi.
- **Arch testleri** (Pest):
  - `app/Marketplaces/Trendyol` dışından hiçbir yer Trendyol sınıfı import edemez.
  - `app/Models` hiçbir `Marketplaces\*\` sınıfına bağımlı olamaz.
  - Kanonik DTO'lar `readonly` olmalı.
- **Statik analiz**: mevcut `phpstan level 7` korunur; `app/Marketplaces` için level 9 hedeflenir.

Kalite kapıları zaten `composer ci:check` altında toplanmış (pint → phpstan → pest + frontend lint/format/types). Yeni kod bu kapıdan geçmeden birleştirilmez.

---

## 15. Yol haritası

| Faz | İçerik | Çıktı |
|---|---|---|
| **0** | Zemin düzeltme (§1) | PG + Redis üzerinde çalışan, testleri geçen uygulama |
| **1** | Tenancy + lisans + auth (§2–4) | Tenant yaratılabiliyor, kendi subdomain'inde giriş yapılabiliyor |
| **2** | Kanonik katalog + arama (§5.1) | Ürün/varyant/stok/fiyat CRUD + Türkçe FTS |
| **3** | Sürücü iskeleti + Trendyol kategori/marka (§6) | Eşleme sihirbazı çalışıyor, referans verisi cache'li |
| **4** | Ürün push + batch polling (§7.2–7.5) | Ürün Trendyol'a gidiyor, sonuç item bazında izleniyor |
| **5** | Fiyat/stok push (§7.3) | Debounce + 15 dk penceresi + rate limit bütçesi |
| **6** | Sipariş + kargo (§5.3) | Sipariş çekiliyor, statü güncellemesi gönderiliyor |
| **7** | İade + soru-cevap | Satış sonrası tam kapsam |
| **8** | Webhook + mutabakat (§7.6, §8) | Gerçek zamanlı + drift koruması |
| **9** | **İkinci pazaryeri** | §12'nin sınavı |

Fazlar 4–7 birbirinden bağımsızdır ve paralelleştirilebilir; hepsi Faz 3'ün sürücü iskeletine bağlıdır.

---

## Ek: Laravel 13 / stancl notları

Bu belgede kullanılan API'ler vendor kaynağından **doğrulanmıştır**.

**Vardır ve kullanılacaktır:** `Http::pool()` / `Http::batch()` (ikisi de `PendingRequest` üzerinde) · `#[DebounceFor(int, ?int)]` · `#[Backoff]`, `#[Tries]`, `#[Timeout]`, `#[UniqueFor]` queue attribute'ları · `RateLimited`, `WithoutOverlapping`, `ThrottlesExceptions`, `Skip`, `Release`, `FailOnException` job middleware'leri · `Context` (kuyruğa otomatik taşınır) · `Cache::flexible()` · `whereFullText(..., ['mode'=>'websearch','vector'=>true,'language'=>'turkish'])` — `turkish` geçerli dil listesinde · `$table->tsvector()`, `->storedAs()`, `->index($c,$n,'gin')`, `->nullsNotDistinct()`, `->generatedAs()->always()`, `$table->jsonb()` · `IndexDefinition::online()` → `CREATE INDEX CONCURRENTLY` (migration'da `public $withinTransaction = false` gerekir) · `insertOrIgnore` + `RETURNING` · `Rule::anyOf()`, FormRequest `after(): array`, `#[FailOnUnknownFields]` · `Illuminate\Support\Uri`, `Number::useCurrency('TRY')`, `Sleep::fake()`, `Timebox` · `#[Scope]`, `casts()`, `AsCollection::of()`, `AsEncryptedArrayObject`, `MassPrunable` · Container attribute'ları (`#[Config]`, `#[Singleton]`, `#[Scoped]`, `#[Bind]`).

**Yoktur, güvenilmeyecektir:** `skipLocked()` (→ `->lock('for update skip locked')`) · Blueprint'te partial index `where()` (→ ham SQL) · `PARTITION BY` (→ ham SQL) · `pg_trgm` yardımcıları (→ ham `CREATE EXTENSION` + ham GIN index) · advisory lock API'si (→ Redis `Cache::lock`) · idempotency desteği · HTTP yanıt cache'i · `#[AsListener]` · `HasVersion7Uuids` (`HasUuids` zaten UUIDv7 üretir) · `$table->virtualAs()` PG 16/17'de geçersiz (PG 18+ gerektirir).

**stancl/tenancy v3.10 notları:** 5 bootstrapper, 6 feature (**hepsi kapalı**), 9 middleware, 36 event · `CacheTenancyBootstrapper` prefix değil **tag** kullanır (taggable store şart) · `InitializeTenancyByRequestData` `OPTIONS`'ta atlar · `CentralConnection` trait'i central modeller için · Octane farkındalığı **sıfır** (§2.3).
