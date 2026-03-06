<?php

test('storefront_data_path returns the Data directory path', function () {
    $path = storefront_data_path();

    expect($path)->toBeString()
        ->and($path)->toEndWith('/Data')
        ->and(is_dir($path))->toBeTrue();
});
