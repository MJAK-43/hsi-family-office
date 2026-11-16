<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentAdministrationSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::role('super_admin')->value('id');
        foreach (DocumentCategory::DEFAULTS as $category) {
            DocumentCategory::firstOrCreate(
                ['slug' => Str::slug($category['name'])],
                [...$category, 'is_active' => true, 'created_by' => $adminId],
            );
        }

        foreach ([
            ['group' => 'general', 'key' => 'organization_name', 'label' => 'Nom de l’organisation', 'value' => 'HSI Family Office', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'default_currency', 'label' => 'Devise par défaut', 'value' => 'USD', 'type' => 'string', 'is_public' => true],
            ['group' => 'documents', 'key' => 'document_max_size_mb', 'label' => 'Taille maximale des documents (Mo)', 'value' => '20', 'type' => 'integer', 'is_public' => false],
            ['group' => 'documents', 'key' => 'document_default_retention_days', 'label' => 'Rétention documentaire par défaut (jours)', 'value' => '3650', 'type' => 'integer', 'is_public' => false],
            ['group' => 'security', 'key' => 'session_timeout_minutes', 'label' => 'Expiration de session (minutes)', 'value' => '120', 'type' => 'integer', 'is_public' => false],
        ] as $setting) {
            ApplicationSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
