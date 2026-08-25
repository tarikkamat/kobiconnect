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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('describe-action')]
#[Description('Bir action\'in HTTP metodunu, yol parametrelerini ve kabul ettigi alanlari dogrulama kurallariyla birlikte dondurur. call-action oncesi kullan.')]
class DescribeActionTool extends Tool
{
    public function handle(Request $request): Response
    {
        ['action' => $action] = $request->validate(['action' => ['required', 'string']]);

        try {
            return Response::json(ActionCatalog::describe($action));
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
            'action' => $schema->string()->description('list-actions ciktisindaki action adi, or. "orders.index".')->required(),
        ];
    }
}
