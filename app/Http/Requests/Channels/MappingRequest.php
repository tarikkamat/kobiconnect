<?php

declare(strict_types=1);

namespace App\Http\Requests\Channels;

use App\Actions\Mapping\RemoteCatalog;
use App\Actions\Mapping\ValidateMapping;
use App\Models\Category;
use App\Models\ChannelConnection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sihirbazin dort kayit adimi ayni iki route parametresini tasir ve ayni iki
 * aksiyonu cozer; ortak kisim burada durur.
 *
 * Yetkilendirme burada DEGIL, controller'da (`Gate::authorize`) — mevcut kanal
 * ekranlariyla ayni desen.
 */
abstract class MappingRequest extends FormRequest
{
    public function connection(): ChannelConnection
    {
        $connection = $this->route('connection');

        abort_unless($connection instanceof ChannelConnection, 404);

        return $connection;
    }

    public function category(): Category
    {
        $category = $this->route('category');

        abort_unless($category instanceof Category, 404);

        return $category;
    }

    protected function catalog(): RemoteCatalog
    {
        return $this->container->make(RemoteCatalog::class);
    }

    protected function validation(): ValidateMapping
    {
        return $this->container->make(ValidateMapping::class);
    }

    /**
     * Eslenmis pazaryeri kategorisi; adim 1 tamamlanmadan 2-4 kaydedilemez.
     */
    protected function remoteCategoryId(): ?string
    {
        return $this->validation()
            ->categoryMapping($this->connection(), $this->category())
            ?->remote_category_id;
    }
}
