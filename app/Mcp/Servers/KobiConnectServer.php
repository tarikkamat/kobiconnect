<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CallActionTool;
use App\Mcp\Tools\DescribeActionTool;
use App\Mcp\Tools\ListActionsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('KobiConnect')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
KobiConnect, Trendyol ve Hepsiburada gibi pazaryerlerini tek panelde toplayan
cok kiracili bir e-ticaret yonetim uygulamasidir. Bu sunucu panelin **tum**
ozelliklerini uc arac uzerinden acar; her ozellik icin ayri bir arac yoktur.

Akis:
1. `list-actions` — kullanilabilir action'lari gor (gerekirse `search` ile daralt).
2. `describe-action` — secilen action'in parametrelerini ve dogrulama kurallarini oku.
3. `call-action` — action'i calistir.

Kapsam: katalog (urun, varyant, marka, kategori, toplu duzenleme), kanal
baglantilari ve eslemeler, siparisler, iadeler, envanter ve depolar, raporlar,
bildirimler, ekip yonetimi ve AI uclari (copilot, dinamik fiyat, stok tahmini,
SEO, desi tahkimi, iade riski, kampanya simulasyonu).

Kurallar:
- Islem oturumdaki kullanicinin yetkileriyle calisir; `forbidden` donerse
  kullanicinin o izni yoktur, tekrar deneme.
- `validation` hatasi alan adlariyla birlikte doner; duzeltip yeniden cagir.
- Silme (`*.destroy`) ve toplu guncelleme action'larini kullanici acikca
  istemeden calistirma.
- Musteri verisi KVKK geregi maskeli doner; maskeyi asmaya calisma.
MARKDOWN)]
class KobiConnectServer extends Server
{
    protected array $tools = [
        ListActionsTool::class,
        DescribeActionTool::class,
        CallActionTool::class,
    ];
}
