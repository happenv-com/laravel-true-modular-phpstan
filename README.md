# laravel-true-modular-phpstan

PHPStan extensions for [laravel-true-modular](https://github.com/happenv-com/laravel-true-modular)
modular monoliths. Ships two independent, **zero-config** extensions:

| Extension | What it does |
| --- | --- |
| **Dynamic Relation Resolver** | Teaches PHPStan about Eloquent relations registered at runtime via `Model::resolveRelationUsing()` (e.g. relations one module adds to another module's model). Such relations are normally invisible to static analysis — this extension makes `$model->relation` and `$model->relation()` fully typed. |
| **Module Boundary Enforcer** | Fails analysis when a module references a class from another module that is **not** declared in its `composer.json` `require`. Also detects circular dependencies between modules. |

Requires PHP 8.3+, PHPStan 2.x and `laravel-true-modular` (Laravel 12/13). It reuses
the framework's own module discovery, so it understands your modules out of the box —
no PHPStan parameters to set.

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

## Zero configuration

Neither extension needs any `parameters` in `phpstan.neon`. The Module Boundary
Enforcer discovers modules through `laravel-true-modular`'s own `ModuleRegistry`,
which means it automatically honours:

- the configured module composer type (`Application::moduleComposerType()`, default
  `true-module`), and
- the configured modules directory (`Application::modulesDirectory()`, default
  `app-modules`).

A "module" is any package under that directory whose `composer.json` declares the
module type. Allowed cross-module dependencies are read straight from each module's
`composer.json` `require` section — exactly the same source of truth the framework
uses to order service providers at runtime. There is no vendor prefix or path to
configure.

> If no modules are found (e.g. the directory does not exist), both rules silently
> do nothing, so installing the package never breaks an unrelated build.

## What it reports

### Dynamic Relation Resolver

Once installed it automatically types any relation registered through Laravel's
relation resolver, for example:

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

For every module the enforcer reads its `composer.json` and treats its `require`
entries that resolve to **other known modules** as the allowed cross-module
dependencies. A module may always reference its own classes and any class from a
package that is not a module (vendor libraries, the host application, …).

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

## Example `phpstan.neon`

```neon
includes:
    # auto-included by extension-installer; shown here for the manual case
    - vendor/happenv-com/laravel-true-modular-phpstan/extension.neon

parameters:
    level: 6
    paths:
        - app-modules
```

That's the whole configuration — point PHPStan at your modules and run it.

## Development

```bash
composer install
vendor/bin/pest        # test suite (Pest 4)
vendor/bin/pint        # code style
```

The test suite covers both extensions:

- **Dynamic relations** — boots an in-memory Eloquent connection, registers
  relations via `Model::resolveRelationUsing()`, and asserts the resolver exposes
  them with the correct method/property types.
- **Module boundary** — uses PHPStan's `RuleTestCase` against fixture modules under
  `tests/Fixtures` to assert allowed/disallowed cross-module references and circular
  dependency detection.

## License

MIT
