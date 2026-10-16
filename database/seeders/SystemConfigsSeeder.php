<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// DB-001 · Claves Parte 2 §3 con sus defectos. Idempotente por config_key.
// Sin PII: responsible_* quedan NULL hasta el instalador.

class SystemConfigsSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['business_name', null, 'string', null], ['country', 'CL', 'string', null],
            ['currency_code', 'CLP', 'string', null], ['currency_decimals', '0', 'int', null],
            ['timezone', 'America/Santiago', 'string', null], ['locale', 'es-CL', 'string', null],
            ['responsible_name', null, 'string', null], ['responsible_email', null, 'string', null],
            ['responsible_phone', null, 'string', null], ['is_setup_completed', '0', 'bool', null],
            ['is_demo', '0', 'bool', null], ['fiscal_id', null, 'string', null],
            ['fiscal_name', null, 'string', null], ['commercial_activity', null, 'string', null],
            ['fiscal_address', null, 'string', null], ['fiscal_commune', null, 'string', null],
            ['prices_include_tax', '1', 'bool', null], ['cash_rounding_step', '1', 'int', null],
            ['receipt_header', null, 'string', null], ['receipt_footer', null, 'string', null],
            ['receipt_paper', '80mm', 'string', null], ['deployment_profile', 'shared_hosting', 'string', null],
            ['default_max_discount_percent', '100', 'decimal', null], ['discount_caps_apply_to_promotions', '0', 'bool', null],
            ['discount_stacking_mode', 'best_of', 'string', null], ['block_sale_below_cost', '1', 'bool', null],
            ['override_min_authority', '50', 'int', null], ['pin_length', '4', 'int', null],
            ['pin_max_attempts', '5', 'int', null], ['cost_method', 'average', 'string', null],
            ['stock_adjust_pin_value_threshold', '0', 'decimal', null], ['sales_flow_mode', 'both', 'string', null],
            ['ticket_reserves_stock', '1', 'bool', null], ['ticket_expiry_minutes', '240', 'int', null],
            ['held_sale_expiry_hours', '24', 'int', null], ['seller_code_policy', 'optional', 'string', null],
            ['edit_ticket_at_cash', '0', 'bool', null], ['allow_negative_stock', '1', 'bool', null],
            ['return_window_days', '30', 'int', null], ['reservation_sweep_seconds', '60', 'int', null],
            ['cash_blind_close', '1', 'bool', null], ['cash_difference_tolerance', '0', 'decimal', null],
            ['cash_movement_pin_threshold', '0', 'decimal', null], ['cash_session_max_hours', '16', 'int', null],
            ['fiscal_adapter', 'none', 'string', null], ['fiscal_provider_config', null, 'json', 'fiscal'],
            ['fiscal_authority_confirmed', '0', 'bool', 'fiscal'], ['fiscal_failure_policy', 'allow_pending', 'string', 'fiscal'],
            ['fiscal_default_document', 'boleta', 'string', 'fiscal'], ['fiscal_backup_destination', 'none', 'string', null],
            ['fiscal_backup_path', null, 'string', null], ['fiscal_replica_max_lag_hours', '24', 'int', null],
            ['content_source_channel_id', null, 'int', null], ['channel_dry_run', '0', 'bool', null],
            ['channel_poll_minutes', '15', 'int', null], ['channel_poll_overlap_minutes', '10', 'int', null],
            ['channel_reconcile_time', '03:00', 'string', null], ['channel_stock_debounce_seconds', '60', 'int', null],
            ['webhook_max_body_kb', '1024', 'int', null], ['webhook_valid_warn_per_min', '600', 'int', null],
            ['webhook_invalid_sig_limit_per_min', '30', 'int', null], ['integration_red_connection_minutes', '15', 'int', null],
            ['integration_red_pending_minutes', '30', 'int', null], ['integration_red_reconcile_hours', '36', 'int', null],
            ['report_refresh_minutes', '30', 'int', null], ['marketing_notice_text', null, 'text', null],
            ['marketing_notice_version', '1', 'string', null], ['password_min_length', '15', 'int', null],
            ['session_idle_minutes_pos', '30', 'int', null], ['session_idle_minutes_admin', '60', 'int', null],
            ['session_absolute_hours', '12', 'int', null], ['mfa_required_roles', '["central_admin"]', 'json', null],
            ['bulk_export_alert_threshold', '500', 'int', null], ['retention_webhook_ok_hours', '72', 'int', null],
            ['retention_webhook_failed_days', '14', 'int', null], ['retention_drafts_days', '30', 'int', null],
            ['retention_sync_events_days', '30', 'int', null], ['retention_shipping_days', '180', 'int', null],
            ['retention_audit_months', '24', 'int', null], ['retention_export_files_days', '7', 'int', null],
            ['retention_fiscal_pdf_days', '90', 'int', null], ['customer_inactive_months', '0', 'int', null],
            ['backup_time', '02:00', 'string', null], ['backup_keep_daily', '7', 'int', null],
            ['backup_keep_weekly', '4', 'int', null], ['backup_destination', 'local', 'string', null],
            ['central_heartbeat_enabled', '1', 'bool', null], ['license_stale_warning_days', '7', 'int', null],
        ];
        foreach ($defs as [$key, $value, $type, $module]) {
            DB::table('system_configs')->updateOrInsert(
                ['config_key' => $key],
                ['config_value' => $value, 'value_type' => $type, 'module_code' => $module,
                 'is_secret' => $key === 'fiscal_provider_config' ? 1 : 0,
                 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
