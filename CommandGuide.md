# KobiConnect Komut ve Simülasyon Rehberi (CommandGuide.md)

Bu rehber, KobiConnect üzerinde test ve demo siparişleri oluşturmak, pazaryeri senkronizasyonunu tetiklemek, finansal cezaları simüle etmek ve sistemi test etmek için kullanabileceğiniz komutları ve kullanım örneklerini içerir.

---

## 📋 İçindekiler
1. [Demo Sipariş & Finans/Ceza Simülasyonu](#1-demo-sipariş--finansceza-simülasyonu)
2. [Hepsiburada SIT Canlı Test Siparişi Oluşturma](#2-hepsiburada-sit-canlı-test-siparişi-oluşturma)
3. [Pazaryeri Senkronizasyon Komutları](#3-pazaryeri-senkronizasyon-komutları)
4. [Tenant ve Demo Seeder Komutları](#4-tenant-ve-demo-seeder-komutları)
5. [Veri Temizleme & Sıfırlama (Tinker)](#5-veri-temizleme--sıfırlama-tinker)
6. [Geliştirici & Kod Üretim Araçları](#6-geliştirici--kod-üretim-araçları)

---

## 1. Demo Sipariş & Finans/Ceza Simülasyonu

Proje içerisindeki `demo:seed-orders` komutu ile Trendyol ve Hepsiburada kanalları üzerinden farklı komisyon, kargo maliyeti ve ceza kalemlerine sahip gerçekçi siparişler üretebilirsiniz.

### 🚀 Kullanım:
```bash
# Tüm tenant'lar için varsayılan 50 adet sipariş üretir
php artisan demo:seed-orders

# Belirli bir tenant için (örn: 1004) 50 sipariş üretir
php artisan demo:seed-orders --tenant=1004

# Özel sipariş adedi belirterek çalıştırma (örn: 100 adet)
php artisan demo:seed-orders --tenant=1004 --count=100
```

### 🔍 Simüle Edilen Parametreler:
* **Pazaryerleri:** Trendyol ve Hepsiburada mağazaları.
* **Komisyon Oranları:** `%8.5`, `%11.0`, `%14.5`, `%17.0`, `%20.0`, `%22.5`, `%25.0`.
* **Kargo Gideri:** Sipariş başına `38.50 TL` – `62.00 TL` standart kargo bedeli.
* **Kargo Desi Aşım Cezası:** `%28` olasılıkla ve `>3.5 desi` paketlerde `19.50 TL` – `68.00 TL` barem aşım cezası.
* **Tedarik / Gecikme Cezası:** İptal, iade ve gecikmelerde `50.00 TL` – `150.00 TL` pazaryeri cezası.
* **Kargo Firmaları:** Trendyol Express, HepsiJet, Yurtiçi Kargo, Aras Kargo, MNG Kargo, Sendeo.
* **Zaman Yayılımı:** Son 45 güne homojen şekilde dağıtılmış sipariş tarihleri.

---

## 2. Hepsiburada SIT Canlı Test Siparişi Oluşturma

Hepsiburada SIT (Test) ortamına doğrudan API üzerinden canlı test siparişi göndermek için aşağıdaki curl komutunu veya Tinker kodunu kullanabilirsiniz.

### 🌐 Curl ile İstek Gönderme:
```bash
curl -X POST "https://oms-stub-external-sit.hepsiburada.com/orders/merchantid/c5779c28-af0a-43e1-a8a6-8b30782e79ec" \
  -H "Authorization: Basic YzU3NzljMjgtYWYwYS00M2UxLWE4YTYtOGIzMDc4MmU3OWVjOlJKOWhOVDZ0Tjl2Qg==" \
  -H "User-Agent: finansfatura_dev" \
  -H "Content-Type: application/json" \
  -d '{
    "OrderNumber": "'$((RANDOM % 900000000 + 100000000))'",
    "OrderDate": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'",
    "Customer": {
      "CustomerId": "cust-001",
      "Name": "Tarık Kamat"
    },
    "DeliveryAddress": {
      "AddressId": "addr-001",
      "Name": "Tarık Kamat",
      "AddressDetail": "Barbaros Mah. Kardelen Sok. No:5",
      "Email": "tarik@example.com",
      "CountryCode": "TR",
      "PhoneNumber": "05321234567",
      "Town": "Ataşehir",
      "District": "Ataşehir",
      "City": "İstanbul"
    },
    "InvoiceAddress": {
      "AddressId": "addr-001",
      "Name": "Tarık Kamat",
      "AddressDetail": "Barbaros Mah. Kardelen Sok. No:5",
      "Email": "tarik@example.com",
      "CountryCode": "TR",
      "PhoneNumber": "05321234567",
      "Town": "Ataşehir",
      "District": "Ataşehir",
      "City": "İstanbul"
    },
    "LineItems": [
      {
        "Sku": "HBV00000U2NIV",
        "MerchantId": "c5779c28-af0a-43e1-a8a6-8b30782e79ec",
        "Quantity": 1,
        "Price": { "Amount": 350.0, "Currency": "TRY" },
        "Vat": 20.0,
        "TotalPrice": { "Amount": 350.0, "Currency": "TRY" },
        "CargoCompanyId": 1,
        "DeliveryOptionId": 1
      }
    ]
  }'
```

### 📥 SIT Siparişlerini Listeleme:
```bash
curl -X GET "https://oms-external-sit.hepsiburada.com/orders/merchantid/c5779c28-af0a-43e1-a8a6-8b30782e79ec?limit=10&offset=0" \
  -H "Authorization: Basic YzU3NzljMjgtYWYwYS00M2UxLWE4YTYtOGIzMDc4MmU3OWVjOlJKOWhOVDZ0Tjl2Qg==" \
  -H "User-Agent: finansfatura_dev"
```

---

## 3. Pazaryeri Senkronizasyon Komutları

KobiConnect arka plan senkronizasyon motorunu tetiklemek için:

```bash
# Pazaryerlerinden yeni sipariş, ürün ve stok güncellemelerini çeker
php artisan sync:pull

# Belirli bir tenant için senkronu tetikler
php artisan sync:pull --tenant=1004

# Dışa aktarılmayı bekleyen işlem kuyruğunu (outbox) tahliye eder / işler
php artisan sync:drain
```

---

## 4. Tenant ve Demo Seeder Komutları

Tenant ortamına ilk örnek verileri (ürünler, stoklar, bağlantılar vb.) yüklemek için:

```bash
# Tenant 1004 için genel DemoDataSeeder çalıştırma
php artisan tenants:seed --class='Database\Seeders\DemoDataSeeder' --tenants=1004 --force

# Rol ve Yetkileri tohumlama
php artisan tenants:seed --class='Database\Seeders\TenantRoleSeeder' --tenants=1004 --force
```

---

## 5. Veri Temizleme & Sıfırlama (Tinker)

Test ortamında siparişleri ve paketleri sıfırlayıp temiz bir simülasyon başlatmak istediğinizde:

```bash
# Tenant 1004'teki tüm sipariş ve paket verilerini siler
php artisan tinker --execute '
$tenant = App\Models\Tenant::find("1004");
$tenant->run(function () {
    DB::table("shipment_packages")->delete();
    DB::table("order_status_history")->delete();
    DB::table("order_lines")->delete();
    DB::table("orders")->delete();
    echo "Tenant 1004 sipariş verileri başarıyla temizlendi.\n";
});
'
```

---

## 6. Geliştirici & Kod Üretim Araçları

Frontend ve backend değişikliklerini derlemek ve doğrulamak için:

```bash
# Wayfinder TypeScript rotalarını üretir (ZORUNLU: --with-form parametresi ile çalıştırılmalıdır)
php artisan wayfinder:generate --with-form

# PHP Kod Standartlarını düzenler (Laravel Pint)
vendor/bin/pint --dirty --format agent

# Raporlar ve Siparişler testlerini çalıştırır
php artisan test --compact --filter=ReportControllerTest
php artisan test --compact --filter=OrderPageTest

# Frontend varlıklarını derler
npm run build
```
