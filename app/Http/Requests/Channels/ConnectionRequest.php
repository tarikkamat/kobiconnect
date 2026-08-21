<?php

declare(strict_types=1);

namespace App\Http\Requests\Channels;

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Support\MarketplaceManager;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ekleme ve duzenleme ayni formdan gelir. Pazaryeri yalnizca eklemede secilir:
 * kimlik bilgisi semasi pazaryerine ozeldir, sonradan degistirmek bagli tum
 * eslemeleri ve listelemeleri anlamsiz kilardi.
 *
 * Kimlik alanlarinin listesi BURADA YAZILI DEGIL; surucunun
 * `credentialFields()` bildirimidir. Trendyol sayisal bir satici id ve anahtar
 * cifti ister, Hepsiburada bir merchantId UUID'si ve tek servis anahtari —
 * sabit bir kural listesi ikisinden yalnizca birine uyar.
 */
class ConnectionRequest extends FormRequest
{
    /**
     * Isaretlenmemis onay kutusu istekte hic gelmez; `boolean` kurali once
     * degeri normalize etmeyi bekler.
     */
    protected function prepareForValidation(): void
    {
        foreach ($this->credentialFields() as $field) {
            if ($field['type'] === 'checkbox') {
                $this->merge([$field['name'] => $this->boolean($field['name'])]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $updating = $this->connection() !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'marketplace' => [
                Rule::requiredIf(! $updating),
                Rule::in(array_keys($this->configuredMarketplaces())),
            ],
            ...$this->credentialRules($updating),
        ];
    }

    /**
     * Alan adlari surucuden geldigi icin mesajlar da oradaki etiketten turer;
     * "seller_id alanı zorunludur" yerine "Satıcı ID alanı zorunludur".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = [];

        foreach ($this->credentialFields() as $field) {
            $labels[$field['name']] = $field['label'];
        }

        return [...$labels, 'name' => 'bağlantı adı'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marketplace.required' => 'Pazaryeri seçilmeli.',
            'marketplace.in' => 'Desteklenmeyen pazaryeri.',
            'seller_id.regex' => 'Satıcı ID yalnızca rakamlardan oluşur (Satıcı Paneli’nde "supplierId" olarak görünür).',
            'integrator.regex' => 'Entegratör adı alfanumerik ve en fazla 30 karakter olmalı.',
            'integrator_user_agent.regex' => 'Entegratör kullanıcı adı boşluksuz olmalı; harf, rakam, nokta, tire ve alt çizgi kabul edilir.',
            'merchant_id.uuid' => 'Merchant ID bir UUID olmalı (satıcı panelinde "merchantId").',
            'listing_tier.in' => 'Geçersiz ürün limiti kademesi.',
        ];
    }

    /**
     * Modele yazilacak nitelikler. `credentials` sifreli kolona gider ve
     * ARAYUZE HIC DONMEZ; bos birakilan bir sir mevcut degeri korur, boylece
     * yalnizca ismi degistirmek icin anahtarlari yeniden yazmak gerekmez.
     *
     * @return array<string, mixed>
     */
    public function connectionAttributes(): array
    {
        $connection = $this->connection();
        $existing = $connection?->credentials->toArray() ?? [];

        $credentials = [];
        $identity = '';

        foreach ($this->credentialFields() as $field) {
            $name = $field['name'];

            $credentials[$name] = match ($field['type']) {
                'secret' => $this->secret($name, $existing),
                'checkbox' => $this->boolean($name),
                default => $this->string($name)->toString(),
            };

            if (($field['identity'] ?? false) === true) {
                $identity = (string) $credentials[$name];
            }
        }

        return [
            ...($connection === null ? ['marketplace' => $this->marketplace()] : []),
            'name' => $this->string('name')->toString(),
            'credentials' => $credentials,
            'external_seller_id' => $identity,
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function credentialRules(bool $updating): array
    {
        $rules = [];

        foreach ($this->credentialFields() as $field) {
            // Sirlar yalnizca EKLERKEN zorunludur: duzenlemede bos birakilan
            // alan kayitli degeri korur, yeniden yazdirmak gerekmez.
            $rules[$field['name']] = $field['type'] === 'secret'
                ? [Rule::requiredIf(! $updating), 'nullable', ...$field['rules']]
                : $field['rules'];
        }

        return $rules;
    }

    /**
     * Secilen pazaryerinin alan semasi. Pazaryeri tanimsizsa BOS doner ve
     * `marketplace.in` kurali hatayi uretir — burada patlamak, gecersiz bir
     * secim icin 500 demek olurdu.
     *
     * @return list<array{name: string, label: string, type: string, rules: list<string>, help?: string, options?: list<string>, default?: string, identity?: bool}>
     */
    private function credentialFields(): array
    {
        try {
            return app(MarketplaceManager::class)->driver($this->marketplace())->credentialFields();
        } catch (MarketplaceException) {
            return [];
        }
    }

    /**
     * Duzenlemede pazaryeri istekten degil KAYITTAN okunur; degistirilemez.
     */
    private function marketplace(): string
    {
        $connection = $this->connection();

        return $connection === null
            ? $this->string('marketplace')->toString()
            : $connection->marketplace;
    }

    /**
     * @param  array<string, mixed>  $existing
     */
    private function secret(string $key, array $existing): string
    {
        return $this->filled($key)
            ? $this->string($key)->toString()
            : (string) ($existing[$key] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredMarketplaces(): array
    {
        $drivers = config('marketplaces.drivers');

        return is_array($drivers) ? $drivers : [];
    }

    private function connection(): ?ChannelConnection
    {
        $connection = $this->route('connection');

        return $connection instanceof ChannelConnection ? $connection : null;
    }
}
