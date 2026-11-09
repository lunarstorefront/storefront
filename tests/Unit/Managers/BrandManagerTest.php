<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lunar\Core\Models\Brand;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Region;
use Lunar\Storefront\Contracts\BrandManager as BrandManagerContract;
use Lunar\Storefront\Managers\BrandManager;

beforeEach(function () {
    $this->manager = new BrandManager;

    $this->language = Language::factory()->create(['default' => true]);
    $currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $currency->id,
        'language_id' => $this->language->id,
    ]);
});

test('it implements the brand manager contract', function () {
    expect($this->manager)->toBeInstanceOf(BrandManagerContract::class);
});

test('it can get brand by slug', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);

    $brand->urls()->create([
        'slug' => 'acme',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $found = $this->manager->getBySlug('acme');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($brand->id);
});

test('it throws when brand slug not found', function () {
    $this->manager->getBySlug('nonexistent');
})->throws(ModelNotFoundException::class);

test('it returns paginated brands with default urls', function () {
    $brandA = Brand::factory()->create(['name' => 'Acme']);
    $brandA->urls()->create([
        'slug' => 'acme',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $brandB = Brand::factory()->create(['name' => 'Beta']);
    $brandB->urls()->create([
        'slug' => 'beta',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    // Brand without a URL should be excluded.
    $ghost = Brand::factory()->create(['name' => 'Ghost']);
    $ghost->urls()->delete();

    $result = $this->manager->getPaginated();

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result->total())->toBe(2);
});

test('it returns paginated brands ordered by name', function () {
    $brandB = Brand::factory()->create(['name' => 'Zebra']);
    $brandB->urls()->create([
        'slug' => 'zebra',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $brandA = Brand::factory()->create(['name' => 'Alpha']);
    $brandA->urls()->create([
        'slug' => 'alpha',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $result = $this->manager->getPaginated();

    expect($result->first()->name)->toBe('Alpha')
        ->and($result->last()->name)->toBe('Zebra');
});

test('it respects perPage parameter', function () {
    for ($i = 0; $i < 5; $i++) {
        $brand = Brand::factory()->create(['name' => "Brand {$i}"]);
        $brand->urls()->create([
            'slug' => "brand-{$i}",
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    $result = $this->manager->getPaginated(2);

    expect($result->perPage())->toBe(2)
        ->and($result->total())->toBe(5);
});

test('paginated brands list names starting with a letter before symbols and digits', function () {
    foreach (['#1 Games', 'zeta', '18 Studio', 'Acme', '[Bracket]'] as $i => $name) {
        Brand::factory()->create(['name' => $name])->urls()->create([
            'slug' => 'brand-'.$i,
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    $names = collect($this->manager->getPaginated()->items())->pluck('name')->all();

    expect(array_slice($names, 0, 2))->toBe(['Acme', 'zeta'])
        ->and(array_slice($names, 2))->toEqualCanonicalizing(['#1 Games', '18 Studio', '[Bracket]']);
});

test('accented names list with their letter and other characters after the alphabet, on any database', function () {
    foreach (['Øresund', 'Fox', '#1 Games', 'Édith', 'Delta', 'ünal', 'Zeta', '18 Studio'] as $i => $name) {
        Brand::factory()->create(['name' => $name])->urls()->create([
            'slug' => 'brand-'.$i,
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    $names = collect($this->manager->getPaginated()->items())->pluck('name')->all();

    // Names are ordered by name within their letter, which follows the database's collation,
    // so this pins the letter order across them rather than every position.
    expect(array_slice($names, 0, 5))->toBe(['Delta', 'Édith', 'Fox', 'ünal', 'Zeta'])
        ->and(array_slice($names, 5))->toEqualCanonicalizing(['#1 Games', '18 Studio', 'Øresund']);
});

test('every brand files under A to Z or #', function () {
    $initials = BrandManager::initials();

    expect(array_keys($initials))->toBe(range('A', 'Z'))
        ->and($initials['E'])->toContain('E', 'e', 'É', 'é', 'Ê')
        ->and(array_merge(...array_values($initials)))->not->toContain('Ø', '1', '#');
});

test('paginated brands narrow to the names starting with a letter, in either case', function () {
    foreach (['Valve', 'valkyrie', 'Acme', '18 Studio'] as $i => $name) {
        Brand::factory()->create(['name' => $name])->urls()->create([
            'slug' => 'brand-'.$i,
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    $names = fn (?string $letter) => collect($this->manager->getPaginated(150, $letter)->items())->pluck('name')->all();

    expect($names('V'))->toBe(['Valve', 'valkyrie'])
        ->and($names('v'))->toBe(['Valve', 'valkyrie'])
        ->and($names('A'))->toBe(['Acme'])
        ->and($names('Q'))->toBe([])
        ->and($names('#'))->toBe(['18 Studio']);
});

test('an unknown letter lists every brand', function () {
    foreach (['Acme', '18 Studio'] as $i => $name) {
        Brand::factory()->create(['name' => $name])->urls()->create([
            'slug' => 'brand-'.$i,
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    expect($this->manager->getPaginated(150, 'AB')->total())->toBe(2)
        ->and($this->manager->getPaginated(150, '')->total())->toBe(2)
        ->and($this->manager->getPaginated(150, null)->total())->toBe(2);
});

test('paginated brand links keep the letter in the query string', function () {
    foreach (range(1, 3) as $i) {
        Brand::factory()->create(['name' => "Va {$i}"])->urls()->create([
            'slug' => "brand-{$i}",
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    // Not from the request: a route parameter or a fixed value still carries into the links.
    expect($this->manager->getPaginated(2, 'v')->nextPageUrl())->toContain('letter=V')
        ->and($this->manager->getPaginated(2, '#')->url(1))->toContain('letter=%23')
        ->and($this->manager->getPaginated(2)->nextPageUrl())->not->toContain('letter=');
});

test('a name files under its letter with accents folded, and every other name under #', function () {
    $names = ['Évian', 'ecco', 'Øresund', '鬼谷工作室', '유진게임즈', 'ßeta', 'Ångström', 'Zed', '18 Studio', 'Łódź'];
    foreach ($names as $i => $name) {
        Brand::factory()->create(['name' => $name])->urls()->create([
            'slug' => 'brand-'.$i,
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    $letter = fn (?string $letter) => collect($this->manager->getPaginated(150, $letter)->items())->pluck('name')->all();

    expect($letter('E'))->toEqualCanonicalizing(['Évian', 'ecco'])
        ->and($letter('A'))->toBe(['Ångström'])
        ->and($letter('O'))->toBe([])
        ->and($letter('#'))->toEqualCanonicalizing(['Øresund', '鬼谷工作室', '유진게임즈', 'ßeta', '18 Studio', 'Łódź'])
        ->and(collect([...range('A', 'Z'), '#'])->sum(fn (string $l) => $this->manager->getPaginated(150, $l)->total()))
        ->toBe(count($names));

    // Folded names sit with their letter on any collation, and # comes last.
    expect(array_slice($letter(null), 0, 4))->toEqualCanonicalizing(['Ångström', 'Évian', 'ecco', 'Zed'])
        ->and(array_slice($letter(null), 0, 1))->toBe(['Ångström'])
        ->and(array_slice($letter(null), 3, 1))->toBe(['Zed']);
});
