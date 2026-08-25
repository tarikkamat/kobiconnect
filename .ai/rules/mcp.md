---
paths:
  - 'app/Mcp/**'
---

# Mcp

## MCP kapsami route tablosundan gelir, elle yazilan tool'lardan degil
MCP sunucusu uc arac yayinlar (list-actions / describe-action / call-action). Her ekran icin ayri Tool sinifi YAZMA: ActionCatalog route tablosunu okur ve action'i ayni route'u ic istek olarak calistirarak yurutur, boylece yetki/dogrulama/prop sekli controller'da tek yerde kalir ve yeni ekran otomatik kapsanir.

Aciklama controller docblock'undan, alan listesi FormRequest::rules()'tan turer. Ajan yanlis action seciyorsa cozum manifest yazmak degil, controller'a docblock eklemektir.

Tuzak: ic istekte middleware bilerek atlanir, bu yuzden `{tenant}` route parametresini elle dusurmek zorunludur (`$route->forgetParameter('tenant')`). Normalde bunu InitializeTenancyByPath yapar; kalirsa ControllerDispatcher tenant string'ini ilk model argumanina denk getirir ve controller "string given" ile patlar. Ayrica hem substituteBindings hem substituteImplicitBindings cagrilmali.

Yeni bir ucu MCP disinda tutmak icin ActionCatalog::DENIED listesine ekle (hesap guvenligi/parola/passkey uclari orada).
