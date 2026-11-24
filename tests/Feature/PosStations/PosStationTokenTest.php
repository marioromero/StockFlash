<?php

namespace Tests\Feature\PosStations;

use App\Models\PosStation;
use App\Support\AuditLogger;
use App\Support\PosStations\PosStationResolver;
use App\Support\PosStations\PosStationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Identidad de dispositivo POS-02: token aleatorio de 256 bits, hash SHA-256,
 * cookie HttpOnly/SameSite=Strict, rotación, revocación e invalidez.
 */
class PosStationTokenTest extends PosStationTestCase
{
    public function test_bind_genera_token_de_256_bits_y_solo_guarda_su_hash(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'BIND-POS']);

        $response = $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/bind")
            ->assertRedirect(route('settings.pos-stations.show', ['station' => $station->id]));

        $cookie = $this->cookieFrom($response, PosStationResolver::COOKIE_NAME);

        $this->assertNotNull($cookie, 'La vinculación debe emitir la cookie de dispositivo.');
        $raw = (string) $cookie->getValue();

        $this->assertSame(64, strlen($raw));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $raw);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
        $this->assertFalse($cookie->isSecure());

        $hash = (string) DB::table('pos_stations')->where('id', $station->id)->value('device_token_hash');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(PosStationToken::hash($raw), $hash);
        $this->assertNotSame($raw, $hash);

        $row = (string) json_encode(DB::table('pos_stations')->where('id', $station->id)->first());
        $this->assertStringNotContainsString($raw, $row);

        $audit = DB::table('audit_logs')
            ->where('event', 'pos_stations.token_bound')
            ->where('auditable_id', $station->id)
            ->first();

        $this->assertNotNull($audit);
        $payload = (string) $audit->new_values;
        $this->assertStringContainsString('"token_rotated":true', $payload);
        $this->assertStringNotContainsString($raw, $payload);
        $this->assertStringNotContainsString($hash, $payload);

        $this->assertStringNotContainsString($raw, (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString($raw, (string) $response->getContent());

        $this->actingAs($this->admin)
            ->get("/settings/pos-stations/{$station->id}")
            ->assertOk()
            ->assertDontSee($raw, false)
            ->assertDontSee($hash, false);

        $this->assertNull($station->refresh()->last_seen_at);
    }

    public function test_cookie_usa_secure_en_https(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'HTTPS-POS']);

        $response = $this->actingAs($this->admin)
            ->post("https://localhost/settings/pos-stations/{$station->id}/bind")
            ->assertRedirect();

        $cookie = $this->cookieFrom($response, PosStationResolver::COOKIE_NAME);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
    }

    public function test_dos_vinculaciones_generan_tokens_distintos_e_invalidan_el_anterior(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'REBIND-POS']);

        $first = $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        );
        $rawFirst = (string) $first?->getValue();
        $hashFirst = (string) DB::table('pos_stations')->where('id', $station->id)->value('device_token_hash');

        $second = $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        );
        $rawSecond = (string) $second?->getValue();
        $hashSecond = (string) DB::table('pos_stations')->where('id', $station->id)->value('device_token_hash');

        $this->assertNotSame($rawFirst, $rawSecond);
        $this->assertNotSame($hashFirst, $hashSecond);
        $this->assertSame(PosStationToken::hash($rawSecond), $hashSecond);

        $this->assertNull($this->resolve($rawFirst), 'El token anterior debe quedar invalidado.');
        $this->assertSame($station->id, $this->resolve($rawSecond)?->id);
    }

    public function test_bind_exige_estacion_activa(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'INACTIVE-BIND', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/bind")
            ->assertSessionHasErrors('station');

        $this->assertNull($station->refresh()->getAttribute('device_token_hash'));
        $this->assertDatabaseMissing('audit_logs', ['event' => 'pos_stations.token_bound']);
    }

    public function test_bind_exige_sucursal_activa(): void
    {
        $branch = $this->makeBranch(['is_active' => false]);
        $station = $this->insertStation($branch->id, ['code' => 'INACTIVE-BRANCH-BIND']);

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/bind")
            ->assertSessionHasErrors('station');

        $this->assertNull($station->refresh()->getAttribute('device_token_hash'));
        $this->assertDatabaseMissing('audit_logs', ['event' => 'pos_stations.token_bound']);
    }

    public function test_revoke_limpia_hash_y_expira_cookie(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'REVOKE-POS']);

        $raw = (string) $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        )?->getValue();

        $response = $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/revoke")
            ->assertRedirect();

        $forget = $this->cookieFrom($response, PosStationResolver::COOKIE_NAME);
        $this->assertNotNull($forget);
        $this->assertSame('/', $forget->getPath());
        $this->assertLessThan(time(), $forget->getExpiresTime());

        $this->assertNull($station->refresh()->getAttribute('device_token_hash'));
        $this->assertNull($this->resolve($raw));

        $audit = DB::table('audit_logs')
            ->where('event', 'pos_stations.token_revoked')
            ->where('auditable_id', $station->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('"token_revoked":true', (string) $audit->new_values);
    }

    public function test_desactivar_invalida_el_token(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'DEACT-POS']);

        $raw = (string) $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        )?->getValue();

        $this->actingAs($this->admin)
            ->post("/settings/pos-stations/{$station->id}/deactivate")
            ->assertRedirect();

        $this->assertNull($station->refresh()->getAttribute('device_token_hash'));
        $this->assertNull($this->resolve($raw));

        $audit = DB::table('audit_logs')
            ->where('event', 'pos_stations.deactivated')
            ->where('auditable_id', $station->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('"token_invalidated":true', (string) $audit->new_values);
    }

    public function test_cambio_de_sucursal_invalida_el_token(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $station = $this->insertStation($branchA->id, ['code' => 'MOVE-TOKEN']);

        $raw = (string) $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        )?->getValue();

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'name' => $station->name,
                'branch_id' => $branchB->id,
            ])
            ->assertRedirect();

        $station->refresh();
        $this->assertSame($branchB->id, (int) $station->branch_id);
        $this->assertNull($station->getAttribute('device_token_hash'));
        $this->assertNull($this->resolve($raw));

        $audit = DB::table('audit_logs')
            ->where('event', 'pos_stations.updated')
            ->where('auditable_id', $station->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('"token_invalidated":true', (string) $audit->new_values);
    }

    public function test_cambio_sin_sucursal_no_invalida_el_token(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'KEEP-TOKEN']);

        $raw = (string) $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        )?->getValue();
        $hash = (string) DB::table('pos_stations')->where('id', $station->id)->value('device_token_hash');

        $this->actingAs($this->admin)
            ->patch("/settings/pos-stations/{$station->id}", [
                'name' => 'Nombre Renombrado',
                'printer_profile' => 'a4',
            ])
            ->assertRedirect();

        $this->assertSame($hash, (string) DB::table('pos_stations')->where('id', $station->id)->value('device_token_hash'));
        $this->assertSame($station->id, $this->resolve($raw)?->id);
    }

    public function test_resolver_falla_sin_cookie_incorrecta_inactiva_eliminada_o_sucursal_inactiva(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'RESOLVE-POS']);
        $raw = (string) $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        )?->getValue();

        $this->assertNull(app(PosStationResolver::class)->resolveFromRequest(Request::create('/', 'GET')));
        $this->assertNull($this->resolve('garbage'));
        $this->assertNull($this->resolve(str_repeat('a', 64)), 'Un token válido de formato sin estación falla.');
        $this->assertNull(app(PosStationResolver::class)->resolveFromRawToken('short'));

        $this->assertSame($station->id, $this->resolve($raw)?->id);
        $this->assertSame($station->id, app(PosStationResolver::class)->resolveFromRawToken($raw, $branch->id)?->id);
        $this->assertNull(
            app(PosStationResolver::class)->resolveFromRawToken($raw, $branch->id + 1),
            'Un contexto de otra sucursal no debe resolver el token.',
        );

        DB::table('pos_stations')->where('id', $station->id)->update(['is_active' => 0]);
        $this->assertNull($this->resolve($raw));
        DB::table('pos_stations')->where('id', $station->id)->update(['is_active' => 1]);

        DB::table('branches')->where('id', $branch->id)->update(['is_active' => 0]);
        $this->assertNull($this->resolve($raw));
        DB::table('branches')->where('id', $branch->id)->update(['is_active' => 1]);

        DB::table('branches')->where('id', $branch->id)->update(['deleted_at' => now()]);
        $this->assertNull($this->resolve($raw));
        DB::table('branches')->where('id', $branch->id)->update(['deleted_at' => null]);

        DB::table('pos_stations')->where('id', $station->id)->update(['deleted_at' => now()]);
        $this->assertNull($this->resolve($raw));
    }

    public function test_resolver_no_autentica_ni_actualiza_last_seen(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'NO-AUTH-POS']);
        $raw = (string) $this->cookieFrom(
            $this->actingAs($this->admin)->post("/settings/pos-stations/{$station->id}/bind"),
            PosStationResolver::COOKIE_NAME,
        )?->getValue();

        $audits = DB::table('audit_logs')->count();

        // Sin actingAs: el resolver identifica una estación, no a un usuario.
        $resolved = $this->resolve($raw);

        $this->assertSame($station->id, $resolved?->id);
        $this->assertNull($resolved?->last_seen_at);
        $this->assertNull($station->refresh()->last_seen_at);
        $this->assertSame($audits, DB::table('audit_logs')->count());
    }

    public function test_fallo_de_auditoria_revierte_bind_y_revoke(): void
    {
        $branch = $this->makeBranch();
        $station = $this->insertStation($branch->id, ['code' => 'AUDIT-TOKEN-POS']);

        $this->mock(AuditLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Auditoría caída.'));
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post("/settings/pos-stations/{$station->id}/bind");
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría caída.', $exception->getMessage());
        }

        $this->assertNull($station->refresh()->getAttribute('device_token_hash'));

        $hash = PosStationToken::hash(PosStationToken::generate());
        DB::table('pos_stations')->where('id', $station->id)->update(['device_token_hash' => $hash]);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->admin)
                ->post("/settings/pos-stations/{$station->id}/revoke");
            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException) {
        }

        $this->assertSame($hash, (string) DB::table('pos_stations')->where('id', $station->id)->value('device_token_hash'));
    }

    private function resolve(string $rawToken): ?PosStation
    {
        $request = Request::create('/', 'GET', [], [PosStationResolver::COOKIE_NAME => $rawToken]);

        return app(PosStationResolver::class)->resolveFromRequest($request);
    }
}
