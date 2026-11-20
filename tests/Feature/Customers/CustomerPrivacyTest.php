<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Enmascarado por defecto, revelado autorizado y auditoría de lectura
 * (RN-61 · PRV-02/03/06).
 */
class CustomerPrivacyTest extends CustomerTestCase
{
    private const EMAIL = 'privado-cliente@example.test';

    private const PHONE = '+56988887777';

    private const RUT = '12345678-5';

    private const BUSINESS = 'Giro Privado';

    private const ADDRESS = 'Calle Privada 123';

    private function privateCustomer(): Customer
    {
        return $this->createCustomer([
            'name' => 'Cliente Privado',
            'trade_name' => 'Fantasia Privada',
            'rut' => self::RUT,
            'email' => self::EMAIL,
            'phone' => self::PHONE,
            'business_activity' => self::BUSINESS,
            'fiscal_address' => self::ADDRESS,
            'fiscal_commune' => 'Comuna Privada',
            'fiscal_city' => 'Ciudad Privada',
        ]);
    }

    private function assertNoClearPii(string $content): void
    {
        foreach ([self::EMAIL, self::PHONE, self::RUT, '12345678', self::BUSINESS, self::ADDRESS, 'Comuna Privada', 'Ciudad Privada'] as $value) {
            $this->assertStringNotContainsString($value, $content, "Se filtró PII en claro: {$value}");
        }
    }

    public function test_listado_y_ficha_enmascaran_y_no_envian_campos_sensibles(): void
    {
        $customer = $this->privateCustomer();
        $viewer = $this->makeUser([
            'name' => 'Visor Clientes',
            'global_role_id' => $this->makeRoleWithPermissions(['customers.view'])->id,
        ]);

        $list = $this->actingAs($viewer)
            ->get('/customers')
            ->assertOk();

        $this->assertNoClearPii((string) $list->getContent());

        $list->assertInertia(function (Assert $page) use ($customer): void {
            $props = $page->toArray()['props'];
            $row = collect($props['customers']['data'])->firstWhere('id', $customer->id);

            $this->assertNotNull($row);
            foreach ([
                'rut', 'fiscal_id_encrypted', 'fiscal_id_hash', 'email', 'phone',
                'business_activity', 'fiscal_address', 'fiscal_commune', 'fiscal_city',
            ] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $row);
            }

            $this->assertNotSame(self::RUT, $row['rut_masked']);
            $this->assertNotSame(self::EMAIL, $row['email_masked']);
            $this->assertNotSame(self::PHONE, $row['phone_masked']);
        });

        $detail = $this->actingAs($viewer)
            ->get("/customers/{$customer->id}")
            ->assertOk();

        $this->assertNoClearPii((string) $detail->getContent());

        $detail->assertInertia(function (Assert $page): void {
            $props = $page->toArray()['props'];
            $row = $props['customer'];

            foreach ([
                'rut', 'fiscal_id_encrypted', 'fiscal_id_hash', 'email', 'phone',
                'business_activity', 'fiscal_address', 'fiscal_commune', 'fiscal_city',
            ] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $row);
            }

            $this->assertTrue($row['has_rut']);
            $this->assertTrue($row['has_email']);
            $this->assertTrue($row['has_phone']);
            $this->assertTrue($row['has_business_activity']);
            $this->assertTrue($row['has_fiscal_address']);
            $this->assertTrue($row['has_fiscal_commune']);
            $this->assertTrue($row['has_fiscal_city']);
        });
    }

    public function test_busqueda_por_nombre_no_filtra_pii(): void
    {
        $this->privateCustomer();
        $viewer = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions(['customers.view'])->id,
        ]);

        $response = $this->actingAs($viewer)
            ->get('/customers?q=Cliente')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('customers.total', 1));

        $this->assertNoClearPii((string) $response->getContent());
    }

    public function test_revelar_exige_view_sensitive_y_manage_no_implica_revelar(): void
    {
        $customer = $this->privateCustomer();

        $manageOnly = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions(['customers.view', 'customers.manage'])->id,
        ]);

        $response = $this->actingAs($manageOnly)
            ->get("/customers/{$customer->id}/reveal")
            ->assertForbidden();

        $this->assertStringNotContainsString(self::EMAIL, (string) $response->getContent());
        $this->assertStringNotContainsString(self::RUT, (string) $response->getContent());

        $viewerOnly = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions(['customers.view'])->id,
        ]);

        $this->actingAs($viewerOnly)
            ->get("/customers/{$customer->id}/reveal")
            ->assertForbidden();
    }

    public function test_revelado_autorizado_entrega_datos_y_audita_sin_valores(): void
    {
        $customer = $this->privateCustomer();
        $revealer = $this->makeUser([
            'global_role_id' => $this->makeRoleWithPermissions([
                'customers.view', 'customers.view_sensitive',
            ])->id,
        ]);

        $response = $this->actingAs($revealer)
            ->get("/customers/{$customer->id}/reveal")
            ->assertOk()
            ->assertJsonPath('customer_id', $customer->id)
            ->assertJsonPath('rut', self::RUT)
            ->assertJsonPath('email', self::EMAIL)
            ->assertJsonPath('phone', self::PHONE)
            ->assertJsonPath('business_activity', self::BUSINESS)
            ->assertJsonPath('fiscal_address', self::ADDRESS)
            ->assertJsonPath('fiscal_commune', 'Comuna Privada')
            ->assertJsonPath('fiscal_city', 'Ciudad Privada');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );

        $this->assertStringNotContainsString('••••', (string) $response->getContent());

        $audit = DB::table('audit_logs')
            ->where('event', 'customers.revealed')
            ->where('auditable_type', 'customers')
            ->where('auditable_id', $customer->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('customers', $audit->module_code);
        $this->assertSame(1, (int) $audit->is_personal_data_access);
        $this->assertNull($audit->old_values);
        $this->assertNull($audit->new_values);
    }

    public function test_formulario_de_edicion_no_precarga_valores_sensibles(): void
    {
        $customer = $this->privateCustomer();

        $this->actingAs($this->admin)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $row = $page->toArray()['props']['customer'];

                $this->assertSame('••••••••-•', $row['rut_masked']);
                $this->assertSame('•••@•••', $row['email_masked']);
                $this->assertSame('••••••••', $row['phone_masked']);

                foreach ([
                    'rut', 'email', 'phone', 'business_activity', 'fiscal_address',
                    'fiscal_commune', 'fiscal_city', 'fiscal_id_encrypted', 'fiscal_id_hash',
                ] as $forbidden) {
                    $this->assertArrayNotHasKey($forbidden, $row);
                }
            });
    }
}
