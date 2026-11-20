<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Auditoría atómica de altas, cambios, estado y grupos (CORE-10 · PRV-06).
 */
class CustomerAuditTest extends CustomerTestCase
{
    public function test_creacion_audita_sin_pii(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Nombre Secreto',
            'email' => 'secreto@example.test',
            'phone' => '+56912345678',
            'rut' => '12.345.678-5',
            'business_activity' => 'Giro Secreto',
        ]);

        $audit = DB::table('audit_logs')
            ->where('event', 'customers.created')
            ->where('auditable_id', $customer->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('customers', $audit->module_code);
        $this->assertSame('customers', $audit->auditable_type);
        $this->assertNull($audit->branch_id);

        $payload = (string) $audit->new_values;
        foreach ([
            'Nombre Secreto', 'secreto@example.test', '+56912345678',
            '12345678', 'Giro Secreto',
            hash_hmac('sha256', '12345678-5', (string) config('privacy.blind_index_key')),
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $payload);
        }

        $this->assertStringContainsString('"is_active":true', $payload);
    }

    public function test_actualizacion_y_estado_auditan_solo_metadatos(): void
    {
        $customer = $this->createCustomer(['name' => 'Original']);

        $this->actingAs($this->admin)
            ->patch("/customers/{$customer->id}", [
                'name' => 'Nombre Nuevo',
                'email' => 'nuevo@example.test',
            ])
            ->assertRedirect();

        $updated = DB::table('audit_logs')
            ->where('event', 'customers.updated')
            ->where('auditable_id', $customer->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($updated);
        $this->assertStringNotContainsString('Nombre Nuevo', (string) $updated->new_values);
        $this->assertStringNotContainsString('nuevo@example.test', (string) $updated->new_values);
        $this->assertStringContainsString('"fields"', (string) $updated->new_values);

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/deactivate")
            ->assertRedirect();

        $deactivated = DB::table('audit_logs')
            ->where('event', 'customers.deactivated')
            ->where('auditable_id', $customer->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($deactivated);
        $this->assertStringContainsString('"is_active":false', (string) $deactivated->new_values);

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/activate")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customers.activated',
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_grupos_auditan_alta_edicion_y_estado(): void
    {
        $this->actingAs($this->admin)
            ->post('/customers/groups', ['code' => 'AUD', 'name' => 'Auditado'])
            ->assertRedirect();

        $group = CustomerGroup::query()->where('code', 'AUD')->firstOrFail();

        $created = DB::table('audit_logs')->where('event', 'customers.groups.created')->latest('id')->first();
        $this->assertNotNull($created);
        $this->assertSame('customer_groups', $created->auditable_type);
        $this->assertStringNotContainsString('Auditado', (string) $created->new_values);

        $this->actingAs($this->admin)
            ->patch("/customers/groups/{$group->id}", ['name' => 'Editado'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customers.groups.updated',
            'auditable_id' => $group->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/customers/groups/{$group->id}/deactivate")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customers.groups.deactivated',
            'auditable_id' => $group->id,
        ]);
    }

    public function test_fallo_de_auditoria_revierte_creacion_de_cliente(): void
    {
        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post('/customers', $this->customerPayload(['name' => 'No debe persistir']));
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría caída.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('customers', ['name' => 'No debe persistir']);
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_fallo_de_auditoria_revierte_actualizacion_y_estado(): void
    {
        $customer = $this->createCustomer(['name' => 'Original']);

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->patch("/customers/{$customer->id}", ['name' => 'Cambio no persistido']);
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $this->assertSame('Original', $customer->refresh()->name);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post("/customers/{$customer->id}/deactivate");
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $this->assertTrue($customer->refresh()->is_active);
    }

    public function test_fallo_de_auditoria_revierte_grupo(): void
    {
        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post('/customers/groups', ['code' => 'NOPE', 'name' => 'No debe persistir']);
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseMissing('customer_groups', ['code' => 'NOPE']);
        $this->assertSame(0, CustomerGroup::query()->count());
    }
}
