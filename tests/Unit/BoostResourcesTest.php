<?php

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

    expect(preg_match('/^name:\s*(\S+)\s*$/m', $frontmatter[1], $name))->toBe(1);
    expect($name[1])->toBe($skill);

    expect(preg_match('/^description:\s*(\S.*)$/m', $frontmatter[1], $description))->toBe(1);
})->with($boostSkills);
