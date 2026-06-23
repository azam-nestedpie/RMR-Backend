<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Roles ─────────────────────────────────────────────────────────
        $roles = [
            ['id' => 1, 'name' => 'Rater',             'description' => 'Can give ratings and manage own connections.'],
            ['id' => 2, 'name' => 'Representative',                'description' => 'Can request ratings and view own feedback.'],
            ['id' => 3, 'name' => 'Manager of Raters',  'description' => 'Manages team of raters and views rater activity.'],
            ['id' => 4, 'name' => 'Manager of Representatives',    'description' => 'Manages team of reps and requests on-behalf.'],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['id' => $r['id']], array_merge($r, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // ── 2. Permissions ────────────────────────────────────────────────────
        $permissions = [
            // Core
            'profile.view.self', 'profile.update.self',
            'connections.manage', 'notifications.view',
            'send connection request', 'accept connection request',
            'reject connection request', 'disconnect connection',
            // Rating Actions
            'ratings.submit', 'ratings.request.self',
            'ratings.view.given', 'ratings.view.received',
            'send rating request',
            // Team & Manager
            'team.view.members', 'team.manage.members',
            'team.view.connections', 'team.ratings.request_on_behalf',
            // Analytics & Dashboard
            'dashboard.view.self', 'dashboard.view.team', 'reports.export',
        ];

        $permMap = [];
        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['permission' => $perm],
                ['permission' => $perm, 'created_at' => now(), 'updated_at' => now()]
            );
            $permMap[$perm] = DB::table('permissions')->where('permission', $perm)->value('id');
        }

        // ── 3. Role-Permission Mapping ────────────────────────────────────────
        $mapping = [
            1 => [ // Rater
                'profile.view.self', 'profile.update.self', 'connections.manage',
                // 'ratings.view.received', // ✅ ADD THIS
                'send connection request', 'accept connection request', 'reject connection request', 'disconnect connection',
                'notifications.view', 'ratings.submit', 'ratings.view.given',
                //  'send rating request',
                'dashboard.view.self',
            ],
            2 => [ // Rep
                'profile.view.self', 'profile.update.self', 'connections.manage',
                'send connection request', 'accept connection request', 'reject connection request', 'disconnect connection',
                'notifications.view', 'ratings.request.self', 'ratings.view.received', 'send rating request', 'dashboard.view.self',
            ],
            3 => [ // Manager of Raters
                'profile.view.self', 'profile.update.self', 'connections.manage',
                'send connection request', 'accept connection request', 'reject connection request', 'disconnect connection',
                'notifications.view', 'team.view.members',
                'team.view.connections', 'team.ratings.request_on_behalf',
                // 'send rating request',
                'dashboard.view.team', 'reports.export',
            ],
            4 => [ // Manager of Reps
                'profile.view.self', 'profile.update.self', 'connections.manage',
                'send connection request', 'accept connection request', 'reject connection request', 'disconnect connection',
                'notifications.view', 'team.view.members',
                'team.manage.members', 'team.view.connections', 'team.ratings.request_on_behalf',
                'send rating request', 'dashboard.view.team', 'reports.export',
            ],

        ];

        foreach ($mapping as $roleId => $perms) {
            foreach ($perms as $perm) {
                if (isset($permMap[$perm])) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $permMap[$perm]],
                        ['role_id' => $roleId, 'permission_id' => $permMap[$perm]]
                    );
                }
            }
        }
    }
}
