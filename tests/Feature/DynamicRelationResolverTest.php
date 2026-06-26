<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\DynamicRelationResolver\DynamicRelationMethodsClassReflectionExtension;
use Happenv\LaravelTrueModular\Phpstan\DynamicRelationResolver\DynamicRelationPropertiesClassReflectionExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\Models\Post;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\Models\Profile;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\VerbosityLevel;

/**
 * Boot a real (in-memory) Eloquent connection and register the relations at
 * runtime — exactly how a module extends another module's model. Native
 * reflection cannot see these; the resolver must.
 */
beforeEach(function (): void {
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    User::resolveRelationUsing('posts', fn (User $user) => $user->hasMany(Post::class));
    User::resolveRelationUsing('profile', fn (User $user) => $user->hasOne(Profile::class));
});

function userReflection(): ClassReflection
{
    return PHPStanTestCase::createReflectionProvider()->getClass(User::class);
}

describe('dynamic relation methods', function (): void {
    it('detects a relation registered at runtime', function (): void {
        $ext = new DynamicRelationMethodsClassReflectionExtension;

        expect($ext->hasMethod(userReflection(), 'posts'))->toBeTrue();
    });

    it('does not detect a method that is not a registered relation', function (): void {
        $ext = new DynamicRelationMethodsClassReflectionExtension;

        expect($ext->hasMethod(userReflection(), 'nonexistent'))->toBeFalse();
    });

    it('types the relation method by its relation class', function (): void {
        $ext = new DynamicRelationMethodsClassReflectionExtension;

        $returnType = $ext->getMethod(userReflection(), 'posts')->getVariants()[0]->getReturnType();

        expect($returnType->describe(VerbosityLevel::typeOnly()))
            ->toBe('Illuminate\Database\Eloquent\Relations\HasMany');
    });
});

describe('dynamic relation properties', function (): void {
    it('detects a relation property registered at runtime', function (): void {
        $ext = new DynamicRelationPropertiesClassReflectionExtension;

        expect($ext->hasProperty(userReflection(), 'posts'))->toBeTrue();
    });

    it('does not detect a property that is not a registered relation', function (): void {
        $ext = new DynamicRelationPropertiesClassReflectionExtension;

        expect($ext->hasProperty(userReflection(), 'nonexistent'))->toBeFalse();
    });

    it('types a to-many relation property as an Eloquent collection', function (): void {
        $ext = new DynamicRelationPropertiesClassReflectionExtension;

        $type = $ext->getProperty(userReflection(), 'posts')->getReadableType();

        expect($type->describe(VerbosityLevel::typeOnly()))
            ->toBe('Illuminate\Database\Eloquent\Collection<int, '.Post::class.'>');
    });

    it('types a to-one relation property as a nullable model', function (): void {
        $ext = new DynamicRelationPropertiesClassReflectionExtension;

        $type = $ext->getProperty(userReflection(), 'profile')->getReadableType();

        expect($type->describe(VerbosityLevel::typeOnly()))
            ->toBe(Profile::class.'|null');
    });
});
