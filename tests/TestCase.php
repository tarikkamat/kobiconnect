<?php

namespace Tests;

use App\Models\License;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sabit test tenant'i. Tenant id'si AYNI ZAMANDA URL slug'idir
     * (PathTenantResolver `tenancy()->find($id)` ile cozer):
     * http://app.kobiconnect.test/test/dashboard
     */
    public const string TENANT_ID = 'test';

    protected ?Tenant $tenant = null;

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Auth artik tenant semasinda yasiyor (BACKEND-PLAN.md §4.1), bu yuzden her
     * feature testi tenant baglaminda baslar. Tenant semasi surec basina bir kez
     * provision edilir — RefreshDatabase'in transaction'i central baglantidadir
     * ve `CREATE SCHEMA`, ayri bir PDO olan tenant baglantisindan gorunmez.
     */
    /**
     * Pazaryeri entegrasyonunda test suretiyle disariya cikmak kabul edilemez:
     * yavas, kirilgan ve sahte kimlik bilgilerini ucuncu tarafa sizdirir.
     * Faked olmayan her istek burada YUKSEK SESLE patlar.
     */
    protected function preventStrayHttp(): void
    {
        Http::preventStrayRequests();
    }

    public function initializeTestTenancy(): void
    {
        $this->preventStrayHttp();

        $this->tenant = Tenant::find(static::TENANT_ID);

        if ($this->tenant === null) {
            // migrate:fresh `tenants` tablosunu bosaltir ama semayi birakir.
            DB::connection('central')->statement('DROP SCHEMA IF EXISTS "tenant'.static::TENANT_ID.'" CASCADE');

            // Path tanimlamada `domains` tablosu KULLANILMAZ; tenant id'si
            // dogrudan URL segmentidir.
            $this->tenant = Tenant::create(['id' => static::TENANT_ID]);
        }

        tenancy()->initialize($this->tenant);

        $this->truncateTenantTables();
    }

    /**
     * Panel route'lari `license` middleware'i ile korunur; lisanssiz tenant 402
     * alir. "Aktif lisansli tenant" normal durumdur, bu yuzden panel testleri
     * bunu beforeEach'te cagirir.
     *
     * Bilerek OPT-IN: harness kosulsuz lisans yaratsaydi, lisansin YOKLUGUNU
     * assert eden testleri (ve licenses.tenant_id unique kisitini) kirardi.
     */
    public function grantActiveLicense(): License
    {
        $existing = License::where('tenant_id', static::TENANT_ID)->first();

        if ($existing !== null) {
            return $existing;
        }

        return License::factory()->forTenant($this->tenant)->create();
    }

    /**
     * EndTenancyAfterRequest terminating middleware'i her istekten sonra
     * kosulsuz `tenancy()->end()` cagirir. Test govdesi istekten sonra da
     * tenant baglaminda devam etmeli.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        if ($this->tenant !== null && ! tenancy()->initialized) {
            tenancy()->initialize($this->tenant);
        }

        return $response;
    }

    /**
     * Tenant semasi surecler arasinda yasadigi icin testler arasi izolasyonu
     * transaction yerine truncate ile sagliyoruz: tenant baglantisi her
     * `initialize()`/`end()` cifti arasinda purge edildigi icin bir transaction
     * istek sinirini asamaz.
     */
    private function truncateTenantTables(): void
    {
        $schema = (string) DB::connection('tenant')->getConfig('search_path');

        $tables = array_diff(
            Schema::connection('tenant')->getTableListing($schema, schemaQualified: false),
            ['migrations'],
        );

        if ($tables === []) {
            return;
        }

        DB::connection('tenant')->statement(
            'TRUNCATE TABLE "'.implode('", "', $tables).'" RESTART IDENTITY CASCADE',
        );
    }
}
