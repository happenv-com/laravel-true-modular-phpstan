<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModuleBoundaryClassReferenceRuleTestCase;

uses(ModuleBoundaryClassReferenceRuleTestCase::class);

it('flags an inline reference to a class from a module not declared in composer require', function (): void {
    $this->analyse(
        [FixturePaths::saleFile('DisallowedInline.php')],
        [[
            'Module boundary violation: "acme/sale" is not allowed to use "Acme\Catalog\Product" (from module "acme/catalog"). Add "acme/catalog" to the require section of acme/sale/composer.json to allow this dependency.',
            11,
        ]],
    );
});

it('allows an inline reference to a class from a module declared in composer require', function (): void {
    $this->analyse([FixturePaths::saleFile('AllowedInline.php')], []);
});
