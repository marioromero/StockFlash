<?php

namespace App\Support\Customers;

use App\Models\Customer;
use App\Models\CustomerGroup;

/**
 * Serialización segura de clientes y grupos para Inertia.
 *
 * Nunca expone el RUT en claro, `fiscal_id_encrypted`, `fiscal_id_hash`,
 * correo, teléfono, giro ni domicilio tributario. Solo entrega máscaras,
 * indicadores de existencia, descuentos y estado.
 */
final class CustomerPresenter
{
    public function __construct(private readonly CustomerRut $rut) {}

    /**
     * @return array<string, mixed>
     */
    public function group(?CustomerGroup $group): ?array
    {
        if ($group === null) {
            return null;
        }

        return [
            'id' => (int) $group->id,
            'code' => (string) $group->code,
            'name' => (string) $group->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function groupRow(CustomerGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'code' => (string) $group->code,
            'name' => (string) $group->name,
            'default_discount_percent' => $this->decimal($group->default_discount_percent),
            'max_discount_percent' => $this->decimal($group->max_discount_percent),
            'is_active' => (bool) $group->is_active,
        ];
    }

    /**
     * Fila de listado: sin PII en claro.
     *
     * @return array<string, mixed>
     */
    public function listRow(Customer $customer): array
    {
        $group = $customer->relationLoaded('group') ? $customer->group : null;

        return [
            'id' => (int) $customer->id,
            'type' => (string) $customer->type,
            'name' => (string) $customer->name,
            'trade_name' => $customer->trade_name,
            'rut_masked' => $this->rut->maskRut($this->hasRut($customer)),
            'has_rut' => $this->hasRut($customer),
            'email_masked' => $this->rut->maskEmail($customer->email),
            'phone_masked' => $this->rut->maskPhone($customer->phone),
            'group' => $this->group($group),
            'discounts' => $this->discounts($customer, $group),
            'is_active' => (bool) $customer->is_active,
            'is_blocked' => $customer->blocked_at !== null,
            'is_anonymized' => $customer->anonymized_at !== null,
            'is_selectable' => $this->isSelectable($customer),
            'origin' => (string) $customer->origin,
            'created_at' => $customer->created_at?->toIso8601String(),
        ];
    }

    /**
     * Ficha: sin PII en claro; solo indicadores y máscaras.
     *
     * @return array<string, mixed>
     */
    public function detail(Customer $customer): array
    {
        $group = $customer->relationLoaded('group') ? $customer->group : null;

        return [
            'id' => (int) $customer->id,
            'type' => (string) $customer->type,
            'name' => (string) $customer->name,
            'trade_name' => $customer->trade_name,
            'rut_masked' => $this->rut->maskRut($this->hasRut($customer)),
            'has_rut' => $this->hasRut($customer),
            'email_masked' => $this->rut->maskEmail($customer->email),
            'has_email' => $customer->email !== null && $customer->email !== '',
            'phone_masked' => $this->rut->maskPhone($customer->phone),
            'has_phone' => $customer->phone !== null && $customer->phone !== '',
            'has_business_activity' => $customer->business_activity !== null && $customer->business_activity !== '',
            'has_fiscal_address' => $customer->fiscal_address !== null && $customer->fiscal_address !== '',
            'has_fiscal_commune' => $customer->fiscal_commune !== null && $customer->fiscal_commune !== '',
            'has_fiscal_city' => $customer->fiscal_city !== null && $customer->fiscal_city !== '',
            'group' => $this->group($group),
            'discounts' => $this->discounts($customer, $group),
            'is_active' => (bool) $customer->is_active,
            'is_blocked' => $customer->blocked_at !== null,
            'is_anonymized' => $customer->anonymized_at !== null,
            'is_selectable' => $this->isSelectable($customer),
            'origin' => (string) $customer->origin,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     own_default: ?string,
     *     own_max: ?string,
     *     group_default: ?string,
     *     group_max: ?string,
     *     effective_default: ?string,
     *     effective_max: ?string
     * }
     */
    public function discounts(Customer $customer, ?CustomerGroup $group): array
    {
        $ownDefault = $this->decimal($customer->default_discount_percent);
        $ownMax = $this->decimal($customer->max_discount_percent);
        $groupDefault = $this->decimal($group?->default_discount_percent);
        $groupMax = $this->decimal($group?->max_discount_percent);

        return [
            'own_default' => $ownDefault,
            'own_max' => $ownMax,
            'group_default' => $groupDefault,
            'group_max' => $groupMax,
            'effective_default' => $ownDefault ?? $groupDefault,
            'effective_max' => $ownMax ?? $groupMax,
        ];
    }

    public function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = (string) $value;

        if (! str_contains($text, '.')) {
            return $text.'.00';
        }

        [$integer, $decimals] = explode('.', $text, 2);

        return $integer.'.'.str_pad(substr($decimals, 0, 2), 2, '0');
    }

    private function hasRut(Customer $customer): bool
    {
        return $customer->fiscal_id_hash !== null && $customer->fiscal_id_hash !== '';
    }

    private function isSelectable(Customer $customer): bool
    {
        return (bool) $customer->is_active
            && $customer->blocked_at === null
            && $customer->anonymized_at === null;
    }
}
