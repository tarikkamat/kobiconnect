---
paths:
  - 'app/Marketplaces/**'
---

# Marketplaces

## Kimlik alanları sürücünün credentialFields() bildirimidir
Bağlantı formunun alanları tek yerde tanımlıdır: `MarketplaceDriver::credentialFields()`. Üç tüketici de oradan okur — ConnectionRequest (kurallar + `identity` alanı `external_seller_id`'ye aynalanır), ConnectionController (`marketplaces[].fields`, `rules` STRIPLENIR) ve connection-drawer.tsx (render). Arayüzde veya request'te `if ($marketplace === 'trendyol')` YAZMAYIN: tam olarak bu yüzden Hepsiburada seçildiğinde form boş çıkıyor ve kaydetme "api_key zorunlu" ile düşüyordu.

`type: 'secret'` alanlar prop'a hiç girmez ve formda boş bırakılınca kayıtlı değeri korur; arayüz yalnızca `credentials.secretsStored` bayrağını görür.

Sağlık sondası HÂLÂ ayrı: CheckConnectionHealth::probe() içinde pazaryeri başına bir match kolu var (ucuz "beni doğrula" çağrısı her sürücüde farklı serviste). Üçüncü pazaryerinde o match sürücünün kendi check() metoduna taşınmalı.
