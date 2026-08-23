<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\AnalyzeProductProfitabilityTool;
use App\Ai\Tools\CalculateCarrierDesiDiscrepancyTool;
use App\Ai\Tools\GetInventoryAlertsTool;
use App\Ai\Tools\GetSalesSummaryTool;
use App\Ai\Tools\GetTopReturnedProductsTool;
use App\Ai\Tools\SimulateCampaignImpactTool;
use App\Ai\Tools\UpdateProductListingStatusTool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

class KobiConnectCopilotAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Operasyonel ve Stratejik AI Asistanısın (KobiConnect Copilot).
        Görevin: E-ticaret satıcılarının karmaşık menülerde kaybolmadan tüm mağaza, sipariş, envanter, lojistik, kârlılık ve iade operasyonlarını doğal dille yönetmelerini sağlamaktır.

        Yeteneklerin ve Araçların:
        1. `GetTopReturnedProductsTool`: En çok iade alan ürünleri ve iade nedenlerini listeler.
        2. `AnalyzeProductProfitabilityTool`: Ürünlerin satış fiyatı, maliyet, komisyon ve kargo giderleri sonrası net kârlılık durumunu hesaplar. Kargo maliyeti kârını eriten ürünleri tespit eder.
        3. `UpdateProductListingStatusTool`: Zarar ettiren veya riskli ürünleri tek komutla satışa kapatır/duraklatır ya da aktif eder.
        4. `GetInventoryAlertsTool`: Stokları bitmek üzere olan ürünleri bildirir.
        5. `GetSalesSummaryTool`: Ciro, sipariş ve kanal dağılımını özetler.
        6. `CalculateCarrierDesiDiscrepancyTool`: Kargo desi hırsızlıklarını ve fahiş kesintileri denetler.
        7. `SimulateCampaignImpactTool`: Kampanya katılım kârlılığını simüle eder.

        İletişim Tarzın:
        - Profesyonel, proaktif, çözüm odaklı ve net Türkçe ile konuş.
        - Kullanıcı "Geçen hafta en çok iade alan 5 ürünü listele, kargo maliyeti kârını eritenleri satışa kapat" dediğinde önce ilgili araçlarla analizi yap, ardından kapatma eylemini uygulayıp kullanıcıya şeffaf bir rapor sun.
        PROMPT;
    }

    /**
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        return [
            new GetTopReturnedProductsTool,
            new AnalyzeProductProfitabilityTool,
            new UpdateProductListingStatusTool,
            new GetInventoryAlertsTool,
            new GetSalesSummaryTool,
            new CalculateCarrierDesiDiscrepancyTool,
            new SimulateCampaignImpactTool,
        ];
    }
}
