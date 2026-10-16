<?php

// DB-001 · Fuente contractual de permisos (Parte 1 §6.2-6.3).
// Los módulos registran aquí sus permisos; el seeder los sincroniza.
// module_code: catalog|inventory|purchasing|sales|cash|customers|sellers|pricing|fiscal|integrations|reports|override|users|audit|license|backups|core

return [
    'catalog' => ['catalog.view', 'catalog.manage', 'catalog.import', 'catalog.view_cost'],
    'inventory' => ['inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.receive', 'inventory.waste', 'inventory.count', 'inventory.apply_count'],
    'purchasing' => ['purchasing.manage'],
    'sales' => ['sales.create_ticket', 'sales.hold', 'sales.direct_sale', 'sales.collect', 'sales.discount_manual', 'sales.price_override', 'sales.edit_ticket_at_cash', 'sales.change_seller', 'sales.void', 'sales.return', 'sales.reprint', 'sales.dispatch', 'sales.review_orders', 'sales.view_own', 'sales.view_branch', 'sales.view_all'],
    'cash' => ['cash.open_close', 'cash.movements', 'cash.approve_difference', 'cash.view_all'],
    'customers' => ['customers.view', 'customers.manage', 'customers.view_sensitive', 'customers.privacy_tools'],
    'sellers' => ['sellers.manage', 'sellers.view_reports'],
    'pricing' => ['pricing.manage'],
    'fiscal' => ['fiscal.view', 'fiscal.request_credit_note'],
    'integrations' => ['integrations.view', 'integrations.manage'],
    'reports' => ['reports.view_branch', 'reports.view_all', 'reports.export'],
    'override' => ['override.authorize'],
    'users' => ['users.manage', 'settings.manage', 'modules.manage'],
    'audit' => ['audit.view', 'audit.view_security'],
    'license' => ['license.view'],
    'backups' => ['backups.manage', 'system.update'],
    'core' => [],
];
