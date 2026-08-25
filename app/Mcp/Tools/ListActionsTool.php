<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ActionCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('list-actions')]
#[Description('KobiConnect panelinde calistirilabilecek tum action\'lari listeler (katalog, siparis, stok, kanal, iade, rapor, ayar ve AI uclari). Once bunu cagir; action adlari `<modul>.<eylem>` bicimindedir.')]
class ListActionsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $search = Str::lower(trim((string) $request->get('search', '')));

        $actions = array_values(array_filter(
            ActionCatalog::all(),
            fn (array $action): bool => $search === '' || str_contains(
                Str::lower($action['action'].' '.$action['path'].' '.$action['description']),
                $search,
            ),
        ));

        return Response::json(['count' => count($actions), 'actions' => $actions]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Action adi, yolu veya aciklamasinda gecen metin ile filtrele (or. "stok", "siparis", "rapor").'),
        ];
    }
}
