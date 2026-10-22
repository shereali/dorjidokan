<?php

/*
 * Permission catalog and default role matrix for workshop (tenant) users.
 *
 * Owners always hold every permission. The "owner_only" permissions can never be
 * granted to another role, even by editing the matrix, so a manager cannot
 * escalate themselves into billing, member or role administration.
 * Tenants may override the default matrix per role via the tenant_roles table.
 */

$all = [
    'dashboard' => ['dashboard.view'],
    'orders' => ['orders.view', 'orders.create', 'orders.edit', 'orders.delete', 'orders.assign', 'orders.status', 'orders.payments', 'orders.measure'],
    'customers' => ['customers.view', 'customers.manage', 'customers.delete', 'loyalty.manage'],
    'catalog' => ['garments.view', 'garments.manage'],
    'workforce' => ['employees.view', 'employees.manage', 'attendance.manage', 'work.manage', 'payouts.view', 'payouts.manage'],
    'stock' => ['inventory.view', 'inventory.manage', 'purchases.manage', 'sales.manage', 'rentals.manage'],
    'finance' => ['expenses.view', 'expenses.manage', 'expenses.approve', 'accounting.view', 'accounting.manage', 'reports.view', 'exports.manage'],
    'communication' => ['notifications.view', 'notifications.manage'],
    'administration' => ['billing.view', 'billing.manage', 'settings.view', 'settings.manage', 'members.manage', 'roles.manage', 'webhooks.manage', 'voice.manage', 'tenant.delete'],
];

$flat = array_merge(...array_values($all));

$ownerOnly = ['billing.manage', 'members.manage', 'roles.manage', 'webhooks.manage', 'voice.manage', 'tenant.delete'];

return [
    'groups' => $all,
    'owner_only' => $ownerOnly,

    // Roles shown in the matrix editor (owner is fixed and listed first).
    'roles' => [
        'owner' => ['name' => 'Owner', 'name_bn' => 'মালিক', 'locked' => true],
        'manager' => ['name' => 'Manager', 'name_bn' => 'ম্যানেজার', 'locked' => false],
        'master' => ['name' => 'Master / Cutter', 'name_bn' => 'মাস্টার / কাটার', 'locked' => false],
        'karigar' => ['name' => 'Karigar', 'name_bn' => 'কারিগর', 'locked' => false],
        'receptionist' => ['name' => 'Receptionist', 'name_bn' => 'রিসিপশনিস্ট', 'locked' => false],
        'accountant' => ['name' => 'Accountant', 'name_bn' => 'হিসাবরক্ষক', 'locked' => false],
    ],

    // Legacy role keys that existed before the permission matrix.
    'aliases' => ['admin' => 'owner', 'staff' => 'receptionist'],

    'defaults' => [
        'owner' => $flat,
        'manager' => array_values(array_diff($flat, $ownerOnly)),
        'master' => [
            'dashboard.view', 'orders.view', 'orders.create', 'orders.edit', 'orders.assign', 'orders.status', 'orders.measure',
            'customers.view', 'garments.view', 'employees.view', 'attendance.manage', 'work.manage', 'inventory.view', 'notifications.view',
        ],
        'karigar' => ['dashboard.view', 'orders.view', 'orders.status', 'garments.view'],
        'receptionist' => [
            'dashboard.view', 'orders.view', 'orders.create', 'orders.edit', 'orders.payments', 'orders.measure',
            'customers.view', 'customers.manage', 'loyalty.manage', 'garments.view', 'sales.manage', 'rentals.manage', 'inventory.view', 'notifications.view',
        ],
        'accountant' => [
            'dashboard.view', 'orders.view', 'orders.payments', 'customers.view', 'employees.view', 'payouts.view', 'payouts.manage',
            'expenses.view', 'expenses.manage', 'expenses.approve', 'accounting.view', 'accounting.manage', 'reports.view', 'exports.manage',
            'inventory.view', 'billing.view', 'settings.view',
        ],
    ],
];
