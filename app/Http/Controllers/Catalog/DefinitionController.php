<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DefinitionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Product::class);

        return Inertia::render('catalog/definitions/index');
    }
}
