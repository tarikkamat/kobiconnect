<?php

declare(strict_types=1);

namespace App\Http\Requests\Channels;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ön eşleşme kararı: tek satır da toplu seçim de aynı gövdeyi gönderir.
 *
 * `references` pazaryeri tarafındaki dayanaklardır (normalize SKU); burada
 * yalnizca sekli dogrulanir, kimin hangi urune ait oldugu karar yazilirken
 * cozulur (DecideMatches).
 */
class MatchDecisionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Ust sinir keyfi degil: tek istekte yuzlerce karar gonderen bir
            // arayuz kazasi tum kuyrugu tek seferde ilerletmemeli.
            'references' => ['required', 'array', 'min:1', 'max:250'],
            'references.*' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'references.required' => 'Karar verilecek öneri seçilmedi.',
            'references.max' => 'Tek seferde en fazla 250 öneri işlenebilir.',
        ];
    }

    /**
     * @return list<string>
     */
    public function references(): array
    {
        /** @var list<string> $references */
        $references = $this->validated()['references'];

        return array_values(array_unique($references));
    }
}
