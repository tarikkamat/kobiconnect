<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ActionCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('call-action')]
#[Description('Bir KobiConnect action\'ini calistirir. Okuma action\'lari ekran verisini dondurur, yazma action\'lari kaydi degistirir. Yazmadan once describe-action ile alanlari dogrula; kullanici acikca istemediyse silme (destroy) action\'lari cagirma.')]
class CallActionTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'action' => ['required', 'string'],
            'arguments' => ['nullable', 'array'],
        ]);

        try {
            return Response::json(ActionCatalog::call(
                $validated['action'],
                $validated['arguments'] ?? [],
            ));
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage().' — list-actions ile mevcut adlari gor.');
        }
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Calistirilacak action adi, or. "stock.update".')->required(),
            'arguments' => $schema->object()->description('Yol parametreleri ve alanlar tek bir nesnede, or. {"variant": 12, "warehouse": 1, "on_hand": 40, "reason": "sayim"}. Okuma action\'larinda filtreler de buraya girer.'),
        ];
    }
}
