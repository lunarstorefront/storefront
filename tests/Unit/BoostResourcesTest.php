<?php

use Symfony\Component\Yaml\Yaml;

$boostSkills = [
    'storefront-catalog',
    'storefront-pages',
    'storefront-auth-account',
    'storefront-ui-vue',
];

it('ships a valid boost skill', function (string $skill) {
    $file = dirname(__DIR__, 2)."/resources/boost/skills/{$skill}/SKILL.md";

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    expect(preg_match('/\A---\R(.*?)\R---\R/s', $content, $frontmatter))->toBe(1);

    $parsed = Yaml::parse($frontmatter[1]);

    expect($parsed['name'])->toBe($skill);
    expect($parsed['description'])->toBeString()->not->toBeEmpty();
})->with($boostSkills);

it('ships the boost core guideline', function () {
    $file = dirname(__DIR__, 2).'/resources/boost/guidelines/core.blade.php';

    expect(file_exists($file))->toBeTrue()
        ->and(trim(file_get_contents($file)))->not->toBeEmpty();
});

it('does not mention deferred integrations in boost resources', function () {
    $directory = dirname(__DIR__, 2).'/resources/boost';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile()) {
            $files[] = $fileInfo->getPathname();
        }
    }

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        expect(stripos(file_get_contents($file), 'stripe'))->toBeFalse();
    }
});
