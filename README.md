# laravel-true-modular-phpstan

PHPStan extensions for [laravel-true-modular](https://github.com/happenv-com/laravel-true-modular)
modular monoliths. Ships two independent extensions:

| Extension | What it does |
| --- | --- |
| **Dynamic Relation Resolver** | Teaches PHPStan about Eloquent relations registered at runtime via `Model::resolveRelationUsing()` (e.g. relations one module adds to another module's model). Such relations are normally invisible to static analysis — this extension makes `$model->relation` and `$model->relation()` fully typed. |
| **Module Boundary Enforcer** | Fails analysis when a module references a class from another module that is **not** declared in its `composer.json` `require`. Also detects circular dependencies between modules. |

Requires PHP 8.4+, PHPStan 2.x and Laravel 12/13. Designed for the
`app-modules/<name>` and `vendor/<vendor>/<name>` module layout used by
`laravel-true-modular`.

## Installation

```bash
composer require --dev happenv-com/laravel-true-modular-phpstan
```

The package is a `phpstan-extension`. If you use
[`phpstan/extension-installer`](https://github.com/phpstan/phpstan-extension-installer)
(recommended), both extensions are registered automatically — nothing else to do:

```bash
composer require --dev phpstan/extension-installer
```

Make sure the plugin is allowed in your root `composer.json`:

```json
{
    "config": {
        "allow-plugins": {
            "phpstan/extension-installer": true
        }
    }
}
```

### Manual registration (without extension-installer)

Include the bundled config in your `phpstan.neon`:

```neon
includes:
    - vendor/happenv-com/laravel-true-modular-phpstan/extension.neon
```

Or cherry-pick a single extension:

```neon
includes:
    - vendor/happenv-com/laravel-true-modular-phpstan/rules/dynamic-relations.neon
    - vendor/happenv-com/laravel-true-modular-phpstan/rules/module-boundary.neon
```

> **Larastan recommended.** The Dynamic Relation Resolver reflects your Eloquent
> models, so install [`larastan/larastan`](https://github.com/larastan/larastan)
> for accurate results. With `extension-installer` it is wired up automatically.

## Configuration

### Dynamic Relation Resolver

Zero configuration. Once installed it automatically types any relation registered
through Laravel's relation resolver, for example:

```php
// In some module's service provider initialize()/boot():
User::resolveRelationUsing('orders', fn (User $user) => $user->hasMany(Order::class));
```

```php
$user->orders;   // PHPStan now sees: Illuminate\Database\Eloquent\Collection<int, Order>
$user->orders(); // PHPStan now sees: Illuminate\Database\Eloquent\Relations\HasMany
```

To-one relations (`hasOne`, `belongsTo`, `morphOne`, `hasOneThrough`) are typed as
`Related|null`; to-many relations as `Collection<int, Related>`.

### Module Boundary Enforcer

This extension **must** know your module vendor prefix. Set it under
`parameters.moduleBoundary` in `phpstan.neon`:

```neon
parameters:
    moduleBoundary:
        # Vendor segment of your module package names: acme/crm, acme/sales, ...
        vendor: 'acme'

        # Project root used to locate composer.json files (default shown).
        baseDir: %currentWorkingDirectory%

        # Master switch for the boundary check.
        enabled: true

        # Report circular dependencies between modules.
        detectCircularDependencies: true
```

> If `vendor` is left empty (the default), the boundary check matches no modules
> and is effectively a no-op — so installing the package never breaks an existing
> build until you opt in by setting `vendor`.

#### How it works

For every module under `app-modules/<name>` or `vendor/<vendor>/<name>`, the
enforcer reads that module's `composer.json` and treats its `require` entries
beginning with `<vendor>/` as the **allowed** cross-module dependencies. A module
may always reference its own classes and classes from vendor packages outside your
module vendor prefix.

Violations look like:

```
Module boundary violation: "acme/sales" is not allowed to use "Acme\Crm\Models\Customer"
(from module "acme/crm"). Add "acme/crm" to the require section of acme/sales/composer.json
to allow this dependency.
```

Both `use` statements and inline references (`new`, static calls, `instanceof`,
`extends`/`implements`, `catch`, class constants) are checked.

Circular dependencies are reported once at the end of analysis:

```
Circular dependency detected between modules: acme/sales → acme/crm → acme/sales. ...
```

#### Error identifiers

You can ignore findings by identifier in `phpstan.neon`:

| Identifier | Meaning |
| --- | --- |
| `trueModular.moduleBoundary` | Disallowed cross-module reference |
| `trueModular.circularDependency` | Cycle between modules |

```neon
parameters:
    ignoreErrors:
        - identifier: trueModular.moduleBoundary
          path: app-modules/legacy/*
```

To turn a check off entirely, set `moduleBoundary.enabled: false` and/or
`moduleBoundary.detectCircularDependencies: false`.

## Example `phpstan.neon`

```neon
includes:
    # auto-included by extension-installer; shown here for the manual case
    - vendor/happenv-com/laravel-true-modular-phpstan/extension.neon

parameters:
    level: 6
    paths:
        - app-modules
    moduleBoundary:
        vendor: 'acme'
```

## License

MIT
