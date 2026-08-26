<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Catalog\EvaluateDynamicCategory;
use App\Models\DynamicCategory;
use Illuminate\Console\Command;

class EvaluateDynamicCategoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:evaluate-dynamic-categories {--category= : Optional ID of specific dynamic category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate dynamic category conditions and sync matching products';

    /**
     * Execute the console command.
     */
    public function handle(EvaluateDynamicCategory $action): int
    {
        $categoryId = $this->option('category');

        $query = DynamicCategory::query();
        if ($categoryId !== null) {
            $query->where('id', (int) $categoryId);
        }

        $categories = $query->with('conditions')->get();

        if ($categories->isEmpty()) {
            $this->info('No dynamic categories found to evaluate.');

            return self::SUCCESS;
        }

        $this->info("Evaluating {$categories->count()} dynamic categories...");

        foreach ($categories as $cat) {
            $count = $action->execute($cat);
            $this->line(" - [{$cat->id}] {$cat->name}: {$count} products matched.");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
