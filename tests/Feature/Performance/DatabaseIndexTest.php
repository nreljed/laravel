<?php

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_have_status_published_at_index(): void
    {
        $this->assertTrue(
            Schema::hasIndex('posts', 'posts_status_published_at_index'),
            'Missing composite index on posts(status, published_at).'
        );
    }

    public function test_posts_have_user_foreign_key_index(): void
    {
        $this->assertTrue(Schema::hasIndex('posts', 'posts_user_id_index'));
    }

    public function test_notifications_have_unread_meta_index(): void
    {
        $this->assertTrue(Schema::hasIndex('notifications', 'notifications_unread_meta_index'));
    }

    public function test_settings_key_is_uniquely_indexed(): void
    {
        $this->assertTrue(Schema::hasIndex('settings', 'settings_key_unique'));
    }

    public function test_audit_logs_have_action_and_user_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('audit_logs', 'audit_logs_action_index'));
        $this->assertTrue(Schema::hasIndex('audit_logs', 'audit_logs_user_id_index'));
    }

    public function test_roles_and_permissions_have_slug_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('roles', 'roles_slug_unique'));
        $this->assertTrue(Schema::hasIndex('permissions', 'permissions_slug_unique'));
    }
}
