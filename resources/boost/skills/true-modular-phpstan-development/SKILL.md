---
name: true-modular-phpstan-development
description: >-
  Write module code that passes, and fix findings from, happenv-com/laravel-true-modular-phpstan
  (module boundary and dependency-cycle rules for a laravel-true-modular monolith). Use when PHPStan
  reports trueModular.moduleBoundary ("Module boundary violation: ... Add ... to the require section"),
  trueModular.circularDependency, trueModular.circularDependencyIncludingDev or
  trueModular.circularDependencyImport; when code in one module starts using another module's class;
  when editing a module's composer.json require / require-dev; when configuring phpstan.neon for
  app-modules (extension.neon, rules/module-cycle-imports.neon, trueModular.circularDependencies.includeDev);
  or when PHPStan does not see a relation added with hasModelExtensions() or Model::resolveRelationUsing().
---

# Laravel True Modular PHPStan

Two PHPStan extensions for a laravel-true-modular application. The **Module Boundary Enforcer** turns
the modules' `composer.json` files into rules: code in a module may use another module's classes only
if that module is listed **directly** in its own `require`, and the `require` graph must stay acyclic.
The **Dynamic Relation Resolver** types Eloquent relations registered at runtime (which is how model
extensions add relations to another module's model). There is no configuration: modules, namespaces
and allowed edges come from the same `composer.json` files the framework uses to order providers.

## When to use this skill

- Writing code in one module that touches a class of another module.
- A PHPStan run fails with a `trueModular.*` identifier.
- Adding or removing a module dependency, or a test-only (`require-dev`) one.
- Setting up `phpstan.neon` for the modules, or a separate configuration that analyses module tests.
- `$model->someRelation` from a model extension is reported as an undefined property or method.

## Setup

```bash
composer require --dev happenv-com/laravel-true-modular-phpstan
```

With `phpstan/extension-installer` it registers itself; otherwise include
`vendor/happenv-com/laravel-true-modular-phpstan/extension.neon` (or one of `rules/dynamic-relations.neon`,
`rules/module-boundary.neon`).

```neon
parameters:
    level: 6
    paths:
        - app-modules
```

- Run PHPStan from the project root: the rules look for modules under
  `<working directory>/<modules directory>`.
- The modules directory is `Application::getModulesDirectory()` (default `app-modules`). A custom
  `modulesDirectory()` set in `bootstrap/app.php` is seen only when PHPStan boots the application,
  which Larastan does. Install `larastan/larastan`; the relation resolver needs the booted app too.
- When no modules are found the rules report nothing at all. If a deliberate violation does not fail
  the run, discovery is not finding the modules.

## Module boundaries (`trueModular.moduleBoundary`)

```
Module boundary violation: "acme/sales" is not allowed to use "Acme\Crm\Models\Customer"
(from module "acme/crm"). Add "acme/crm" to the require section of acme/sales/composer.json
to allow this dependency.
```

How a reference is judged:

- The source module is the one whose directory contains the file (its `src/`, `tests/`, `database/`,
  everything). Files outside the modules directory are never checked.
- The target module is found by the longest PSR-4 namespace prefix. Classes of vendor packages, the
  host application and the module itself are always allowed.
- Allowed targets are the modules in the source module's `require`. **Only direct entries count**:
  if `sales` requires `pim` and `pim` requires `core`, `sales` still needs `core` in its own `require`
  to use a `core` class. **`require-dev` does not count either**, not even for files in the module's
  `tests/` (see the testing section).
- Checked: regular `use` imports, `new`, static calls and properties, class constants including
  `::class`, `instanceof`, `extends` / `implements` on classes, `catch`.

How to fix it, in this order of preference:

1. **The dependency is intended and points down the graph** (a feature module using a lower-level
   module): add it to the source module's `require`, then confirm the graph with `module:why` and
   that no cycle appears.

```jsonc
// app-modules/sales/composer.json
"require": {
    "acme/core": "*",
    "acme/crm": "*"
}
```

2. **It would point up the graph or close a cycle** (a low-level module needing a high-level one): do
   not add it. Invert it: put an interface or an event in the lower module, implement or listen in the
   higher one, and bind it there.

```php
// acme/crm (low level) owns the contract
namespace Acme\Crm\Contracts;

interface ResolvesCustomerCredit
{
    public function limitFor(int $customerId): int;
}

// acme/sales (depends on crm) implements and binds it
public function moduleRegistered(): void
{
    $this->app->bind(ResolvesCustomerCredit::class, SalesCreditResolver::class);
}
```

3. **Adding behaviour to another module's model**: declare a model extension in the downstream
   module (`hasModelExtensions()`), which keeps the arrow pointing from the extender to the owner.

The rule is syntactic. It does not see a class reached through a fully-qualified name in a type
declaration, a grouped `use A\{B, C};` import, a trait `use`, an interface's `extends`, an attribute,
a docblock, a class-name string or a container lookup. A clean run is not proof of no coupling, and
none of these is a way around a finding.

## Dependency cycles

| Identifier | When | Where it is reported |
| --- | --- | --- |
| `trueModular.circularDependency` | A cycle in the `require` graph | Once per run, no file or line |
| `trueModular.circularDependencyIncludingDev` | A cycle once `require-dev` edges are walked too (opt-in) | Once per run |
| `trueModular.circularDependencyImport` | The `use` line whose `require-dev` edge closes a cycle (opt-in) | On that import |

A `require` cycle also stops the application from booting (`CircularDependencyException` from the
provider sorter), so fix it by removing an edge (step 2 above), never by ignoring the finding.

Cycles through `require-dev` (a test of `a` using `b`, while `b` requires `a`) are off by default.
Turn them on in the configuration that analyses module tests:

```neon
includes:
    - vendor/happenv-com/laravel-true-modular-phpstan/rules/module-cycle-imports.neon

parameters:
    paths:
        - app-modules              # including the modules' tests/ directories
    trueModular:
        circularDependencies:
            includeDev: true
```

`module-cycle-imports.neon` only fires for an import that is in `require-dev` and not in `require`,
and only when the edge lies on a cycle. Including it in a run that excludes the tests makes it silently
report nothing. Fix a finding by giving the test a fixture its own module owns, or by moving the test
to the module that owns the subject; then drop the `require-dev` entry.

## Tests and `require-dev`

Because the boundary rule reads `require` only, a module test that uses a class of a module listed in
its `require-dev` is a `trueModular.moduleBoundary` finding too, and its message says to add the module
to `require`. Do not do that blindly: it turns a test-only dependency into a shipped one and can close
a cycle. Prefer a test that stays inside its module's `require`. If the project deliberately uses
`require-dev` for cross-module tests, ignore the identifier for test paths only:

```neon
parameters:
    ignoreErrors:
        -
            identifier: trueModular.moduleBoundary
            path: app-modules/*/tests/*
```

## Dynamic relations

Relations registered with `Model::resolveRelationUsing()` become typed properties and methods. This
includes the relations of model extensions: `hasModelExtensions(Product::class, ProductBillingExtension::class)`
registers every public method declared on the extension with a native `Relation` return type.

```php
$product->invoices;    // Illuminate\Database\Eloquent\Collection<..., Invoice>
$product->invoices();  // HasMany
$order->customer;      // Customer|null for HasOne, BelongsTo, MorphOne, HasOneThrough
```

- It only sees relations registered while PHPStan runs, so the application has to boot during
  analysis (Larastan) and the extension has to be declared on an enabled module.
- The registered closure is called with a model built without its constructor. Keep relation methods
  side-effect free: return `$this->model->hasMany(...)` and nothing else.
- Attribute accessors of a model extension (`getFormattedPriceAttribute()`) are not described by this
  package.

## Ignoring findings

Identifiers can be ignored per path (`ignoreErrors` with `identifier` and `path`). Use that for a legacy
module being untangled or for test paths as above, never to land new cross-module coupling.

Editing a module's `composer.json` re-runs these rules even with a warm result cache (PHPStan 2.3+),
as long as the rules come from the bundled `.neon` files.

## Do not

- Do not reach into a module you do not `require`, and do not rely on a transitive dependency.
- Do not add an upward or cycle-closing entry to `require` to silence a finding.
- Do not use a fully-qualified type hint, a class string or the container to get past the rule.
- Do not include `rules/module-cycle-imports.neon` in a run that does not analyse module tests.
