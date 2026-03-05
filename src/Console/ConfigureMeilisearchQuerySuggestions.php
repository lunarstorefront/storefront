<?php

namespace Lunar\Storefront\Console;

use Illuminate\Console\Command;
use Laravel\Scout\Scout;
use Meilisearch\Meilisearch;

class ConfigureMeilisearchQuerySuggestions extends Command
{
    protected $signature = 'storefront:meilisearch:query-suggestions';

    protected $description = 'Configure meilisearch query suggestions';

    public function handle(): int
    {
        if (! class_exists(Meilisearch::class) || ! version_compare(Meilisearch::VERSION, '1.0.0')) {
            $this->error('Please install the suggested Meilisearch client: meilisearch/meilisearch-php.');

            return self::FAILURE;
        }

        $meilisearch = Scout::engine('meilisearch');

        $meilisearch->createIndex('storefront_query_suggestions', [
            'uid' => 'storefront_query_suggestions',
            'primaryKey' => 'signature',
        ]);

        $meilisearch->createIndex('storefront_query_suggestions')->updateSortableAttributes([
            'count',
        ]);

        $meilisearch->createIndex('storefront_query_suggestions')->updateRankingRules([
            'words',
            'count',
        ]);

        $this->info('Index created');

        return self::SUCCESS;
    }
}
