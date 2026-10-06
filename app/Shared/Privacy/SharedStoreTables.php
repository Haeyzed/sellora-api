<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;

/**
 * The store tables that belong to no single domain: sign-in tokens, roles and permissions, the activity and audit logs, uploaded file records, and framework bookkeeping.
 */
final class SharedStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->excludeTable('migrations', 'Framework bookkeeping of the database structure, not store data.');
        $registry->excludeTable('personal_access_tokens', 'Sign-in tokens are secrets.');
        $registry->excludeTable('idempotency_keys', 'A short-lived replay cache of API responses, which can contain tokens.');

        $registry->table('roles', include: ['id', 'public_id', 'name', 'guard_name', 'created_at', 'updated_at']);
        $registry->table('permissions', include: ['id', 'name', 'guard_name', 'created_at', 'updated_at']);
        $registry->table('role_has_permissions', include: ['permission_id', 'role_id']);
        $registry->table('model_has_roles', include: ['role_id', 'model_type', 'model_id']);
        $registry->table('model_has_permissions', include: ['permission_id', 'model_type', 'model_id']);

        $registry->table(
            'activity_log',
            include: ['id', 'log_name', 'description', 'subject_type', 'subject_id', 'event', 'causer_type', 'causer_id', 'created_at', 'updated_at'],
            redact: [
                'attribute_changes' => 'Changes recorded on a model can include secret values.',
                'properties' => 'Details logged with an activity can include secret values.',
            ],
        );

        $registry->table(
            'audits',
            include: ['id', 'user_type', 'user_id', 'event', 'auditable_type', 'auditable_id', 'url', 'ip_address', 'user_agent', 'tags', 'created_at', 'updated_at'],
            redact: [
                'old_values' => 'Audited values can include secret values, such as a password hash.',
                'new_values' => 'Audited values can include secret values, such as a password hash.',
            ],
        );

        $registry->table('media', include: [
            'id', 'model_type', 'model_id', 'uuid', 'collection_name', 'name', 'file_name', 'mime_type', 'disk', 'conversions_disk',
            'size', 'manipulations', 'custom_properties', 'generated_conversions', 'responsive_images', 'order_column', 'created_at', 'updated_at',
        ]);
    }
}
