<?php

namespace Lunar\Storefront\Console;

use Illuminate\Foundation\Console\KeyGenerateCommand;
use Laravel\Scout\Scout;
use Meilisearch\Meilisearch;

class ConfigureMeilisearchQuerySuggestions extends KeyGenerateCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storefront:meilisearch:query-suggestions';

    protected $description = 'Configure meilisearch query suggestions';

    public function handle()
    {
        if (! class_exists(Meilisearch::class) || ! version_compare(Meilisearch::VERSION, '1.0.0')) {
            $this->error('Please install the suggested Meilisearch client: meilisearch/meilisearch-php.');
            return;
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
    }
}
