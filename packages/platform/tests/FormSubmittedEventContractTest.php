<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Events\FormSubmitted;
use Nreljed\Platform\Services\FormSubmissionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Contract test for the form submission event seam.
 *
 * The event is the only supported way for a feature to react to a stored
 * submission, so its shape and its dispatch point are pinned here.
 *
 * @coversNothing
 */
final class FormSubmittedEventContractTest extends TestCase
{
    public function test_event_carries_the_form_key_submission_and_payload(): void
    {
        $reflection = new ReflectionClass(FormSubmitted::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        $this->assertCount(3, $parameters);
        $this->assertSame('formKey', $parameters[0]->getName());
        $this->assertSame('submission', $parameters[1]->getName());
        $this->assertSame('payload', $parameters[2]->getName());
    }

    public function test_event_is_dispatchable(): void
    {
        $this->assertTrue(
            method_exists(FormSubmitted::class, 'dispatch'),
            'the event must be dispatchable so listeners can subscribe to it',
        );
    }

    public function test_service_announces_stored_submissions(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(FormSubmissionService::class))->getFileName());

        $this->assertStringContainsString('event(new FormSubmitted(', $source);
    }

    public function test_a_failing_listener_cannot_break_the_visitor_redirect(): void
    {
        $method = new ReflectionMethod(FormSubmissionService::class, 'announce');

        $this->assertTrue($method->isProtected(), 'announce stays an internal seam');

        $source = (string) file_get_contents((new ReflectionClass(FormSubmissionService::class))->getFileName());

        $this->assertStringContainsString('catch (\Throwable $e)', $source);
        $this->assertStringContainsString('[FormSubmissions] A FormSubmitted listener failed', $source);
    }
}
