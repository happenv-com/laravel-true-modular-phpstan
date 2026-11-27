<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes\ModelExtensionAttributeProperties;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskSchedulingExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModelExtensionFixtures;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\VerbosityLevel;

/**
 * The adapter that turns a mapped model extension accessor into a property PHPStan
 * can see. The type has to come from the accessor's own signature: it is the only
 * source of truth there is, and a property typed from anywhere else would drift the
 * moment the accessor did.
 */
beforeEach(function (): void {
    ModelExtensionFixtures::boot();
});

afterEach(function (): void {
    ModelExtensionFixtures::shutDown();
});

function attributeProperties(): ModelExtensionAttributeProperties
{
    return new ModelExtensionAttributeProperties(PHPStanTestCase::createReflectionProvider());
}

function modelReflection(string $className): ClassReflection
{
    return PHPStanTestCase::createReflectionProvider()->getClass($className);
}

function remainingHours(): PropertyReflection
{
    return attributeProperties()->getProperty(modelReflection(Task::class), 'remaining_hours');
}

describe('claiming properties', function (): void {
    it('claims an attribute a module contributes to another module\'s model', function (): void {
        expect(attributeProperties()->hasProperty(modelReflection(Task::class), 'remaining_hours'))->toBeTrue();
    });

    it('claims attributes contributed by several modules to one model', function (): void {
        $properties = attributeProperties();

        expect($properties->hasProperty(modelReflection(Task::class), 'remaining_hours'))->toBeTrue()
            ->and($properties->hasProperty(modelReflection(Task::class), 'billable_amount'))->toBeTrue();
    });

    it('claims nothing for an attribute no module contributes', function (): void {
        expect(attributeProperties()->hasProperty(modelReflection(Task::class), 'no_such_attribute'))->toBeFalse();
    });

    it('claims nothing on a model the extension does not touch', function (): void {
        expect(attributeProperties()->hasProperty(modelReflection(Project::class), 'remaining_hours'))->toBeFalse();
    });

    it('leaves a relation added by a model extension to the Dynamic Relation Resolver', function (): void {
        expect(attributeProperties()->hasProperty(modelReflection(Project::class), 'milestones'))->toBeFalse();
    });

    it('claims nothing when no application has filled the module registry', function (): void {
        ModelExtensionFixtures::shutDown();

        expect(attributeProperties()->hasProperty(modelReflection(Task::class), 'remaining_hours'))->toBeFalse();
    });

    it('refuses to describe a property it never claimed', function (): void {
        expect(fn (): PropertyReflection => attributeProperties()->getProperty(
            modelReflection(Task::class),
            'no_such_attribute',
        ))->toThrow(ShouldNotHappenException::class, 'no_such_attribute');
    });
});

describe('the property', function (): void {
    it('is typed from the accessor that produces it', function (): void {
        $accessor = PHPStanTestCase::createReflectionProvider()
            ->getClass(TaskSchedulingExtension::class)
            ->getNativeMethod('getRemainingHoursAttribute')
            ->getVariants()[0]
            ->getReturnType()
            ->describe(VerbosityLevel::precise());

        $property = remainingHours();

        expect($property->getReadableType()->describe(VerbosityLevel::precise()))->toBe($accessor)
            ->and($property->getWritableType()->describe(VerbosityLevel::precise()))->toBe($accessor);
    });

    it('honours the accessor\'s PHPDoc return type', function (): void {
        $property = attributeProperties()->getProperty(modelReflection(Task::class), 'elapsed_hours');

        expect($property->getReadableType()->describe(VerbosityLevel::precise()))->toBe('int<0, max>');
    });

    it('belongs to the model that asked for it', function (): void {
        expect(remainingHours()->getDeclaringClass()->getName())->toBe(Task::class);
    });

    it('is public, readable and not static', function (): void {
        $property = remainingHours();

        expect($property->isPublic())->toBeTrue()
            ->and($property->isPrivate())->toBeFalse()
            ->and($property->isStatic())->toBeFalse()
            ->and($property->isReadable())->toBeTrue()
            ->and($property->canChangeTypeAfterAssignment())->toBeFalse()
            ->and($property->getDocComment())->toBeNull();
    });

    /**
     * Writable on purpose, unlike the relation property it is modelled on: the
     * attribute bag accepts assignment, so read-only would ADD errors rather than
     * remove them.
     */
    it('is writable, because the attribute bag accepts assignment', function (): void {
        expect(remainingHours()->isWritable())->toBeTrue();
    });

    it('is neither deprecated nor internal', function (): void {
        $property = remainingHours();

        expect($property->isDeprecated()->no())->toBeTrue()
            ->and($property->getDeprecatedDescription())->toBeNull()
            ->and($property->isInternal()->no())->toBeTrue();
    });
});
