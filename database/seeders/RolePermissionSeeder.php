<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $memberPermissions = [
            'view dashboard',
            'view members',
            'create members',
            'edit members',
            'suspend members',
            'delete members',
            'export members',
            'access administration',
            'manage roles',
            'manage permissions',
        ];

        $contributionPermissions = [
            'view contributions',
            'create contributions',
            'edit contributions',
            'delete contributions',
            'submit contribution payments',
            'validate contribution payments',
            'reject contribution payments',
            'view all contribution payments',
            'view own contribution payments',
            'manage contribution plans',
        ];

        $fundPermissions = [
            'view funds',
            'view fund movements',
            'create fund movements',
            'edit fund movements',
            'delete fund movements',
            'transfer funds',
            'manage fund accounts',
            'manage allocation rules',
        ];

        $projectPermissions = [
            'view projects',
            'view all projects',
            'create projects',
            'edit own projects',
            'edit all projects',
            'delete projects',
            'submit projects',
            'review projects',
            'approve projects',
            'reject projects',
            'submit projects to vote',
            'fund projects',
            'manage project documents',
        ];

        $votePermissions = [
            'view votes',
            'view all votes',
            'create votes',
            'manage votes',
            'cast votes',
            'close votes',
        ];

        $documentPermissions = [
            'view document library',
            'manage documents',
            'publish documents',
            'manage document access',
            'view document audit',
        ];

        $administrationPermissions = [
            'manage users',
            'manage settings',
            'manage document categories',
            'manage zones',
            'manage transaction types',
            'manage project types',
            'approve transactions',
            'view reports',
            'manage reports',
            'manage withdrawals',
        ];

        $permissions = array_merge(
            $memberPermissions,
            $contributionPermissions,
            $fundPermissions,
            $projectPermissions,
            $votePermissions,
            $documentPermissions,
            $administrationPermissions,
        );

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $treasurerContribution = array_filter($contributionPermissions, fn ($p) => $p !== 'delete contributions');
        $treasurerFund = array_filter($fundPermissions, fn ($p) => $p !== 'delete fund movements');

        $roles = [
            'super_admin' => $permissions,
            'president' => array_merge([
                'view dashboard', 'view members', 'create members', 'edit members',
                'suspend members', 'export members', 'access administration',
            ], [
                'view contributions', 'view all contribution payments',
                'view funds', 'view fund movements', 'transfer funds',
                'view all projects', 'approve projects', 'reject projects', 'submit projects to vote', 'fund projects',
                'view votes', 'view all votes', 'create votes', 'manage votes', 'cast votes', 'close votes',
                'view document library', 'manage documents', 'publish documents', 'manage document access', 'view document audit',
                'manage users', 'manage settings', 'manage document categories',
                'manage zones', 'manage transaction types',
                'manage project types', 'approve transactions', 'view reports', 'manage reports', 'manage withdrawals',
            ]),
            'vice_president' => [
                'view dashboard', 'view members', 'create members', 'edit members',
                'suspend members', 'export members',
                'view votes', 'cast votes',
                'view document library',
            ],
            'treasurer' => array_merge(
                ['view dashboard', 'view members', 'export members', 'view all projects', 'fund projects', 'view votes', 'cast votes', 'view document library', 'manage transaction types', 'approve transactions', 'view reports', 'manage reports', 'manage withdrawals'],
                $treasurerContribution,
                $treasurerFund,
            ),
            'secretary' => array_merge([
                'view dashboard', 'view members', 'create members', 'edit members', 'export members',
                'view all projects',
                'view votes', 'view all votes', 'cast votes',
                'view document library', 'manage documents', 'view reports',
            ], [
                'view contributions', 'view all contribution payments',
                'view funds', 'view fund movements',
            ]),
            'investment_committee' => [
                'view dashboard', 'view members',
                'view all projects', 'review projects', 'approve projects', 'reject projects',
                'manage project documents',
                'view votes', 'cast votes', 'view document library', 'manage documents',
                'manage project types', 'view reports',
            ],
            'solidarity_committee' => ['view dashboard', 'view members', 'view votes', 'cast votes', 'view document library'],
            'academy_manager' => ['view dashboard', 'view members', 'view votes', 'cast votes', 'view document library'],
            'member' => [
                'view dashboard', 'view members',
                'view own contribution payments', 'submit contribution payments',
                'view funds',
                'view projects', 'create projects', 'edit own projects', 'submit projects',
                'view votes', 'cast votes',
                'view document library',
            ],
            'guest' => ['view dashboard'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
