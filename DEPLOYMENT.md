# Kobiconnect — VDS & Otomatik CI/CD Deployment Kılavuzu

Bu proje, **Laravel Octane (RoadRunner)** motoru ile yüksek performanslı çalışacak şekilde ve **KopiCRM ile aynı sunucu üzerinde çakışma olmadan** izole Docker konteynerleri ve **özel port (`8020`)** ile yapılandırılmıştır.

---

## 1. CI/CD Otomatik Deployment Akışı (Push & Tag)

GitHub Actions (`.github/workflows/ci.yml`) üzerinde tam otomatik CI/CD yapılandırılmıştır:

1. **Tag push (`v*`) veya manuel `workflow_dispatch`**:
   - **CI Aşaması:** Lint (Pint), PHPStan (Larastan L7), ESLint, Prettier, TypeScript ve PHPUnit testleri çalışır.
   - **Build Aşaması:** Testler başarıyla geçerse Docker imajı derlenir ve GitHub Container Registry'e (`ghcr.io/<owner>/kobiconnect`) `latest` ve commit SHA etiketleriyle push edilir.
   - **Deploy Aşaması:** SSH ile VDS'deki `/opt/kobiconnect` dizinine bağlanılır, yeni imaj çekilir (`pull`), veritabanı migration'ları çalıştırılır (`migrate --force`) ve servisler sıfır kesintiye yakın şekilde güncellenir (`up -d`).

---

## 2. GitHub Secrets Yapılandırması

Otomatik deployment için GitHub reponuzda **Settings → Secrets and variables → Actions** altına şu secret'ları ekleyin:

| Secret | Açıklama |
|---|---|
| `SSH_HOST` | VDS IP veya sunucu adresi |
| `SSH_USER` | Deploy kullanıcısı (docker grubunda) |
| `SSH_KEY` | Deploy kullanıcısının **Private SSH anahtarı** |
| `SSH_PORT` | *(Opsiyonel, varsayılan `22`)* |
| `GHCR_TOKEN` | *(İmaj private ise)* GHCR `docker pull` için Personal Access Token (scope: `read:packages`) |

---

## 3. VDS İlk Kurulum Hazırlığı (Tek Seferlik)

Sunucuda Kobiconnect için `/opt/kobiconnect` dizini oluşturun:

```bash
sudo mkdir -p /opt/kobiconnect && sudo chown $USER /opt/kobiconnect
cd /opt/kobiconnect
```

`docker-compose.yml` ve `.env` dosyalarını buraya yerleştirin:

```bash
# Repodaki docker-compose.yml dosyasını /opt/kobiconnect altına kopyalayın
# .env dosyasını oluşturun:
cp .env.production.example .env

# APP_KEY üretmek için:
docker run --rm php:8.4-cli-alpine php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

---

## 4. Web Sunucusu / Reverse Proxy Ayarı

KopiCRM'in kurulu olduğu sunucudaki web sunucusuna göre aşağıdaki bloklardan birini ekleyin:

### A) Sunucuda Caddy Kullanılıyorsa (KopiCRM'deki gibi):

> **Dikkat:** Caddy konteyner içinde çalıştığı için `reverse_proxy 127.0.0.1:8020`
> **çalışmaz** — o adres Caddy'nin kendi loopback'idir ve 502 döner. Docker ağı
> üzerinden konteyner adıyla bağlanmak gerekir.

Caddy'yi Kobiconnect ağına bağlayın (KopiCRM'in `docker-compose.yml`'ında caddy
servisine ekleyin, aksi halde konteyner yeniden yaratılınca kaybolur):

```yaml
services:
  caddy:
    networks: [default, milyem_backend, kobiconnect_backend]

networks:
  milyem_backend:
    external: true
  kobiconnect_backend:
    external: true  
```

Ardından `/opt/fotokopi-crm/Caddyfile` dosyasına şu bloğu ekleyip
`docker compose exec caddy caddy reload --config /etc/caddy/Caddyfile` çalıştırın:

```caddy
app.kobiconnect.com {
    reverse_proxy kobiconnect_app:8000
}
```

Compose'a dokunmadan hızlıca denemek için:
`sudo docker network connect kobiconnect_backend fotokopi-crm-caddy-1`

---

## 5. Manuel Başlatma veya Rollback

GitHub Actions yerine elle başlatmak veya eski sürüme dönmek isterseniz:

```bash
cd /opt/kobiconnect
docker compose pull
docker compose up -d
docker compose exec app php artisan migrate --force
```

---

## 6. Servis Durumu

| Servis | Motor | Port / Erişim |
|---|---|---|
| `kobiconnect_app` | **Laravel Octane + RoadRunner** | `127.0.0.1:8020` |
| `kobiconnect_scheduler` | Zamanlanmis Gorevler | Arka Plan |
| `kobiconnect_worker` | Kuyruk İşleyicisi | Arka Plan |
| `kobiconnect_nightwatch` | Nightwatch Agent | `nightwatch:2407` (İç Ağ) |
| `kobiconnect_redis` | In-Memory Önbellek & Kuyruk | `kobiconnect_redis:6379` (İç Ağ) |
