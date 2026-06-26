<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModuleBoundaryRuleTestCase;

uses(ModuleBoundaryRuleTestCase::class);

it('flags a use of a class from a module not declared in composer require', function (): void {
    $this->analyse(
        [FixturePaths::saleFile('DisallowedUse.php')],
        [[
            'Module boundary violation: "acme/sale" is not allowed to use "Acme\Catalog\Product" (from module "acme/catalog"). Add "acme/catalog" to the require section of acme/sale/composer.json to allow this dependency.',
            7,
        ]],
    );
});

it('allows a use of a class from a module declared in composer require', function (): void {
    $this->analyse([FixturePaths::saleFile('AllowedUse.php')], []);
});

it('ignores files that do not belong to any module', function (): void {
    $this->analyse([FixturePaths::outsideFile('Plain.php')], []);
});
