---
paths:
  - routes/tenant.php
  - routes/web.php
---

# Routes

## Auth tables live in the tenant schema
users/sessions/password_reset_tokens/passkeys/roles are tenant-schema tables (database/migrations/tenant/0001_*). Central has no users — but the login/register/password screens themselves ARE central, see below.
Fortify registers its own routes, so config/fortify.php carries domain `{tenant}.CENTRAL_DOMAIN` plus the same tenancy middleware stack as bootstrap/app.php. Panel routes live in routes/tenant.php; routes/settings.php is an empty stub kept only because routes/web.php still requires it.
Every tenant route therefore has a `{tenant}` domain parameter. App\Listeners\ConfigureTenantHost supplies it via URL::defaults so route() needs no argument — it is registered twice: as a TenancyInitialized listener (queue/console/tests) and as route middleware. The middleware copy is not redundant: Wayfinder greps middleware handle() bodies for `URL::defaults` and only then marks the param optional in the generated TypeScript.

## Giris, kayit ve parola sifirlama CENTRAL'dir
Kullanicilar tenant semasinda yasar ama giris ekranlari central host'tadir: /login, /register, /forgot-password, /reset-password/{token} (route adlari `central.*` — `login`/`password.*` adlari Fortify'in tenant route'larinindir, EZME).

Tenant e-postadan cozulur: App\Actions\Onboarding\LocateTenantByEmail her tenant semasinda tek sorgu calistirir, is bulunan tenant'in `run()` baglaminda yapilir. Auth::login ve token yazma MUTLAKA tenant baglaminda olmali (remember_token / password_reset_tokens tenant tablolaridir).

Sifirlama baglantisi ResetPassword::createUrlUsing ile central'a yonlendirilir (FortifyServiceProvider); Fortify'in /{tenant}/... esdegerleri calisir durumda birakildi.

2FA merkezi giriste atlanmaz: `login.id` + `login.remember` session'a yazilip tenant'in two-factor.login ekranina yonlendirilir.

Giristen once session()->invalidate() sart — ScopeSessions'in eski `_tenant_id` damgasi yeni panelde 403 uretir.
