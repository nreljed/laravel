<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Enums\AuditAction;
use Nreljed\Platform\Http\Requests\ContactRequest;
use Nreljed\Platform\Models\FormSubmission;
use Nreljed\Platform\Support\CsvExporter;
use Nreljed\Platform\Support\FormRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Framework-free contract tests for the generic form-submission engine.
 *
 * The platform package deliberately does not boot a Laravel application in its
 * own CI — HTTP handling, Eloquent persistence and notification dispatch are
 * integration-tested inside consuming apps. What is pinned here is the static
 * surface every consumer depends on: the registry contract, the config catalog,
 * the CSV export's formula-injection guard, the audit vocabulary, and the
 * migration/permission wiring a deploy depends on.
 *
 * @coversNothing
 */
final class FormSubmissionsContractTest extends TestCase
{
    public function test_status_vocabulary_is_new_read_archived(): void
    {
        $this->assertSame('new', FormSubmission::STATUS_NEW);
        $this->assertSame('read', FormSubmission::STATUS_READ);
        $this->assertSame('archived', FormSubmission::STATUS_ARCHIVED);
    }

    public function test_statuses_returns_the_canonical_order(): void
    {
        $this->assertSame(['new', 'read', 'archived'], FormSubmission::statuses());
    }

    public function test_the_contact_form_declaration_is_valid(): void
    {
        $definition = $this->contactDefinition();

        $this->assertSame(
            [],
            (new FormRegistry)->violations($definition),
            'the contact entry in config/forms.php must satisfy every registry invariant',
        );
    }

    public function test_the_contact_form_declares_its_identity_over_real_fields(): void
    {
        $definition = $this->contactDefinition();

        $this->assertSame(
            ['name' => 'name', 'email' => 'email', 'summary' => 'subject'],
            $definition['identity'],
            'the denormalised inbox columns must map onto declared fields',
        );
    }

    public function test_the_inbox_excerpt_comes_from_the_long_form_field(): void
    {
        $excerpt = (new FormRegistry)->excerpt($this->contactDefinition(), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subject' => 'Billing question',
            'message' => 'How do I export last quarter costs?',
        ]);

        $this->assertSame(
            'How do I export last quarter costs?',
            $excerpt,
            'the preview must show the message body, never repeat the summary column',
        );
    }

    public function test_the_inbox_excerpt_is_null_without_a_textarea_field(): void
    {
        $definition = ['fields' => [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
        ]];

        $this->assertNull((new FormRegistry)->excerpt($definition, ['title' => 'Hello']));
    }

    public function test_the_inbox_excerpt_is_null_when_the_body_is_empty(): void
    {
        $this->assertNull((new FormRegistry)->excerpt($this->contactDefinition(), [
            'message' => '   ',
        ]));

        $this->assertNull((new FormRegistry)->excerpt($this->contactDefinition(), []));
    }

    public function test_the_contact_form_validation_rules_are_the_declared_fields(): void
    {
        $rules = (new FormRegistry)->rules($this->contactDefinition());

        $this->assertContains('required', $rules['name']);
        $this->assertContains('max:120', $rules['name']);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('max:190', $rules['email']);
        $this->assertContains('nullable', $rules['subject']);
        $this->assertContains('max:190', $rules['subject']);
        $this->assertContains('required', $rules['message']);
        $this->assertContains('min:10', $rules['message']);
        $this->assertContains('max:5000', $rules['message']);
    }

    public function test_the_honeypot_stays_out_of_the_validation_rules(): void
    {
        $definition = $this->contactDefinition();

        $this->assertArrayNotHasKey(
            $definition['honeypot'],
            (new FormRegistry)->rules($definition),
            'the honeypot must stay absent from validation rules so a bot cannot '
            .'learn which field trapped it',
        );
    }

    public function test_the_legacy_contact_request_has_not_drifted_from_the_registry(): void
    {
        $this->assertSame(
            (new FormRegistry)->rules($this->contactDefinition()),
            (new ContactRequest)->rules(),
            'ContactRequest is retained for back-compat and must stay identical to the '
            .'registry-built rules for the contact form',
        );
    }

    public function test_form_submissions_exports_are_guarded_against_csv_formula_injection(): void
    {
        $this->assertSame(
            "'=1+1",
            CsvExporter::escapeCell('=1+1'),
            'a cell starting with = must be neutralised (CWE-1236)',
        );

        foreach (CsvExporter::FORMULA_PREFIXES as $prefix) {
            $this->assertSame(
                "'".$prefix.'danger',
                CsvExporter::escapeCell($prefix.'danger'),
                "a cell starting with [{$prefix}] must be neutralised",
            );
        }
    }

    public function test_form_submissions_exports_preserve_real_numbers_including_negatives(): void
    {
        $this->assertSame('-42', CsvExporter::escapeCell(-42));
        $this->assertSame('-42.5', CsvExporter::escapeCell(-42.5));
        $this->assertSame('0', CsvExporter::escapeCell(0));
        $this->assertSame('1234', CsvExporter::escapeCell('1234'));
    }

    public function test_form_submissions_exports_render_nulls_booleans_and_datetimes(): void
    {
        $this->assertSame('', CsvExporter::escapeCell(null));
        $this->assertSame('', CsvExporter::escapeCell(''));
        $this->assertSame('1', CsvExporter::escapeCell(true));
        $this->assertSame('0', CsvExporter::escapeCell(false));
        $this->assertSame('2026-01-02 03:04:05', CsvExporter::escapeCell(new \DateTimeImmutable('2026-01-02 03:04:05')));
    }

    public function test_data_export_and_submission_deletion_are_auditable(): void
    {
        $this->assertSame('data_exported', AuditAction::DataExported->value);
        $this->assertSame('info', AuditAction::DataExported->severity());
        $this->assertSame('form_submission_deleted', AuditAction::FormSubmissionDeleted->value);
        $this->assertSame('warning', AuditAction::FormSubmissionDeleted->severity());
    }

    public function test_manage_submissions_is_a_catalog_permission_excluded_from_editor_and_author(): void
    {
        $roles = $this->rolesConfig();

        $slugs = array_column($roles['base_permissions'], 'slug');
        $this->assertContains('manage-submissions', $slugs);

        foreach (['editor', 'author'] as $role) {
            $this->assertArrayHasKey($role, $roles['base_roles']);
            $this->assertContains(
                '!manage-submissions',
                $roles['base_roles'][$role]['permissions'],
                "the universal inbox must not be granted to [{$role}] via the '*' wildcard",
            );
        }
    }

    public function test_the_form_submissions_migration_ships_with_the_package(): void
    {
        $path = dirname(__DIR__).'/database/migrations/2026_10_19_000001_create_form_submissions_table.php';

        $this->assertFileExists($path);

        $migration = (string) file_get_contents($path);

        foreach (['form_key', 'name', 'email', 'summary', 'payload', 'status', 'ip', 'user_agent', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'consent_at', 'read_at'] as $column) {
            $this->assertStringContainsString(
                "'{$column}'",
                $migration,
                "the migration must declare the [{$column}] column",
            );
        }

        $this->assertStringContainsString("'contact'", $migration);
    }

    public function test_the_legacy_contact_routes_are_preserved_by_the_generic_controller(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__).'/routes/web.php');

        $this->assertStringContainsString('FormSubmissionController', $routes);
        $this->assertStringNotContainsString(
            'ContactSubmissionController',
            $routes,
            'the per-form routes must be served by the generic controller',
        );

        foreach (["prefix('/contact')", "prefix('/submissions')", "defaults('form_key', 'contact')"] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }
    }

    public function test_every_declared_form_is_collectable_at_the_generic_endpoint(): void
    {
        $publicRoutes = (string) file_get_contents(dirname(__DIR__).'/src/PublicSite/Routes/web.php');

        $this->assertStringContainsString("'/forms/{form_key}'", $publicRoutes);
        $this->assertStringContainsString("->name('forms.store')", $publicRoutes);
        $this->assertStringContainsString("->name('contact.store')", $publicRoutes);
        $this->assertStringContainsString("->defaults('form_key', 'contact')", $publicRoutes);
    }

    /**
     * @return array<string, mixed>
     */
    private function contactDefinition(): array
    {
        $config = include dirname(__DIR__).'/config/forms.php';

        $this->assertIsArray($config);
        $this->assertArrayHasKey('forms', $config);
        $this->assertArrayHasKey('contact', $config['forms']);

        return $config['forms']['contact'];
    }

    /**
     * @return array<string, mixed>
     */
    private function rolesConfig(): array
    {
        $config = include dirname(__DIR__).'/config/roles.php';

        $this->assertIsArray($config);

        return $config;
    }
}
