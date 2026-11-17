<?php

namespace Tests\Feature\Core;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Authorization\AuthorizationDeniedException;
use App\Support\Authorization\PinAuthorizationService;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Feature\Users\UserTestCase;

/**
 * CORE-11 / SEC-12 / RN-04–05 — servicio interno de autorización mediante PIN.
 */
class PinAuthorizationServiceTest extends UserTestCase
{
    public function test_global_authorizer_with_override_permission_creates_one_approval(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $result = (new PinAuthorizationService())->authorize(
            'price_override',
            $branch->id,
            $authorizer,
            '1234',
            referenceType: 'ticket',
            referenceId: 77,
            oldValue: '1000',
            newValue: '900',
        );

        $this->assertArrayHasKey('authorization_override_id', $result);
        $this->assertSame(1, DB::table('authorization_overrides')->count());

        $row = DB::table('authorization_overrides')->where('id', $result['authorization_override_id'])->first();

        $this->assertNotNull($row);
        $this->assertSame('price_override', $row->type);
        $this->assertSame((int) $this->admin->id, (int) $row->requested_by_user_id);
        $this->assertSame((int) $authorizer->id, (int) $row->authorized_by_user_id);
        $this->assertSame((int) $branch->id, (int) $row->branch_id);
        $this->assertSame('ticket', $row->reference_type);
        $this->assertSame(77, (int) $row->reference_id);
        $this->assertSame('1000', $row->old_value);
        $this->assertSame('900', $row->new_value);
    }

    public function test_branch_assigned_authorizer_uses_role_from_that_branch(): void
    {
        $branch = $this->makeBranch();
        $requester = $this->makeUser(['global_role_id' => null]);
        DB::table('branch_user')->insert([
            'user_id' => $requester->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('seller')->id,
        ]);

        $authorizer = $this->makeUser([
            'global_role_id' => null,
            'pin_hash' => Hash::make('4321'),
        ]);
        DB::table('branch_user')->insert([
            'user_id' => $authorizer->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('supervisor')->id,
        ]);

        $this->actingAs($requester);

        $result = (new PinAuthorizationService())->authorize(
            'discount_over_cap',
            $branch->id,
            $authorizer->id,
            '4321',
        );

        $this->assertSame(1, DB::table('authorization_overrides')->count());
        $this->assertSame(
            (int) $authorizer->id,
            (int) DB::table('authorization_overrides')
                ->where('id', $result['authorization_override_id'])
                ->value('authorized_by_user_id'),
        );
    }

    public function test_authority_below_minimum_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->makeLowAuthorityRoleWithOverridePermission());

        $this->assertDenied(
            'authorizer_authority_insufficient',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
    }

    public function test_authorizer_without_override_permission_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('cashier'));

        $this->assertDenied(
            'authorizer_permission_denied',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_inactive_authorizer_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'), overrides: ['is_active' => false]);

        $this->assertDenied(
            'authorizer_inactive',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_soft_deleted_authorizer_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));
        $authorizer->delete();

        $this->assertDenied(
            'authorizer_not_found',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                (int) $authorizer->id,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_authorizer_without_role_in_branch_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeUser([
            'global_role_id' => null,
            'pin_hash' => Hash::make('1234'),
        ]);

        $this->assertDenied(
            'authorizer_role_missing',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_inactive_branch_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch(['is_active' => false]);
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'branch_inactive',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_requester_without_branch_access_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $requester = $this->makeUser(['global_role_id' => null]);
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));
        $this->actingAs($requester);

        $this->assertDenied(
            'branch_access_denied',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_multi_branch_authorizer_cannot_use_role_from_another_branch(): void
    {
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $authorizer = $this->makeUser([
            'global_role_id' => null,
            'pin_hash' => Hash::make('1234'),
        ]);

        DB::table('branch_user')->insert([
            'user_id' => $authorizer->id,
            'branch_id' => $branchA->id,
            'role_id' => $this->makeLowAuthorityRoleWithOverridePermission()->id,
        ]);
        DB::table('branch_user')->insert([
            'user_id' => $authorizer->id,
            'branch_id' => $branchB->id,
            'role_id' => $this->role('supervisor')->id,
        ]);

        $this->actingAs($this->admin);

        $this->assertDenied(
            'authorizer_authority_insufficient',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branchA->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_missing_pin_is_rejected_without_incrementing_attempts(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'pin_missing',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                null,
            ),
        );

        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertNull($authorizer->fresh()->pin_locked_until);
    }

    public function test_non_numeric_pin_is_rejected_without_incrementing_attempts(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'pin_format_invalid',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '12a4',
            ),
        );

        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_wrong_pin_length_is_rejected_without_incrementing_attempts(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'pin_format_invalid',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '12345',
            ),
        );

        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_wrong_hash_increments_attempts_sequentially(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));
        $service = new PinAuthorizationService();

        foreach ([1, 2, 3] as $expectedAttempts) {
            $this->assertDenied(
                'pin_invalid',
                fn () => $service->authorize('price_override', $branch->id, $authorizer, '9999'),
            );

            $fresh = $authorizer->fresh();
            $this->assertSame($expectedAttempts, (int) $fresh->pin_failed_attempts);
            $this->assertNull($fresh->pin_locked_until);
        }

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_five_failures_lock_authorizer_for_about_thirty_minutes(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));
        $service = new PinAuthorizationService();

        foreach ([1, 2, 3, 4] as $expectedAttempts) {
            $this->assertDenied(
                'pin_invalid',
                fn () => $service->authorize('price_override', $branch->id, $authorizer, '9999'),
            );

            $this->assertSame($expectedAttempts, (int) $authorizer->fresh()->pin_failed_attempts);
        }

        $this->assertDenied(
            'pin_locked',
            fn () => $service->authorize('price_override', $branch->id, $authorizer, '9999'),
        );

        $fresh = $authorizer->fresh();
        $this->assertSame(5, (int) $fresh->pin_failed_attempts);
        $this->assertNotNull($fresh->pin_locked_until);
        $this->assertTrue($fresh->pin_locked_until->between(now()->addMinutes(29), now()->addMinutes(31)));
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_locked_authorizer_rejects_even_correct_pin(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        DB::table('users')->where('id', $authorizer->id)->update([
            'pin_failed_attempts' => 3,
            'pin_locked_until' => now()->addMinutes(10),
        ]);

        $this->assertDenied(
            'pin_locked',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $fresh = $authorizer->fresh();
        $this->assertSame(3, (int) $fresh->pin_failed_attempts);
        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_expired_lock_allows_success_and_resets_counters(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        DB::table('users')->where('id', $authorizer->id)->update([
            'pin_failed_attempts' => 5,
            'pin_locked_until' => now()->subMinute(),
        ]);

        $result = (new PinAuthorizationService())->authorize(
            'price_override',
            $branch->id,
            $authorizer,
            '1234',
        );

        $fresh = $authorizer->fresh();
        $this->assertSame(0, (int) $fresh->pin_failed_attempts);
        $this->assertNull($fresh->pin_locked_until);
        $this->assertSame(1, DB::table('authorization_overrides')->count());
        $this->assertArrayHasKey('authorization_override_id', $result);
    }

    public function test_approval_persists_optional_context_and_reason_code(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $station = $this->makePosStation($branch);
        $reasonCodeId = $this->makeReasonCode('override', 'prueba_override');
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $result = (new PinAuthorizationService())->authorize(
            'void_sale',
            $branch->id,
            $authorizer,
            '1234',
            posStationId: $station,
            referenceType: 'sale',
            referenceId: 321,
            oldValue: 'pending',
            newValue: 'voided',
            reasonCodeId: $reasonCodeId,
            reason: 'Error de cobro',
        );

        $row = DB::table('authorization_overrides')->where('id', $result['authorization_override_id'])->first();

        $this->assertSame($station, (int) $row->pos_station_id);
        $this->assertSame('sale', $row->reference_type);
        $this->assertSame(321, (int) $row->reference_id);
        $this->assertSame('pending', $row->old_value);
        $this->assertSame('voided', $row->new_value);
        $this->assertSame($reasonCodeId, (int) $row->reason_code_id);
        $this->assertSame('Error de cobro', $row->reason);
    }

    public function test_pos_station_from_other_branch_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $otherBranch = $this->makeBranch();
        $station = $this->makePosStation($otherBranch);
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'pos_station_invalid',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
                posStationId: $station,
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_audit_events_do_not_contain_pin_or_hash(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'), pin: '2468');
        $service = new PinAuthorizationService();

        $this->assertDenied(
            'pin_invalid',
            fn () => $service->authorize('price_override', $branch->id, $authorizer, '1357'),
        );

        $service->authorize('price_override', $branch->id, $authorizer, '2468');

        $auditPayload = DB::table('audit_logs')
            ->get(['event', 'old_values', 'new_values'])
            ->map(fn ($row) => $row->event.' '.$row->old_values.' '.$row->new_values)
            ->implode("\n");

        $hash = (string) DB::table('users')->where('id', $authorizer->id)->value('pin_hash');

        $this->assertStringNotContainsString('1357', $auditPayload);
        $this->assertStringNotContainsString('2468', $auditPayload);
        $this->assertStringNotContainsString($hash, $auditPayload);
        $this->assertStringContainsString('authorization_override.pin_failed', $auditPayload);
        $this->assertStringContainsString('authorization_override.approved', $auditPayload);
    }

    public function test_approval_rolls_back_when_audit_fails(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $failingAudit = new class extends AuditLogger
        {
            public function record(
                string $event,
                string $auditableType,
                ?int $auditableId = null,
                ?int $branchId = null,
                ?array $old = null,
                ?array $new = null,
                string $moduleCode = 'core',
                bool $isSecurityEvent = false,
                bool $isPersonalDataAccess = false,
            ): void {
                throw new RuntimeException('Auditoría no disponible.');
            }
        };

        try {
            (new PinAuthorizationService($failingAudit))->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            );

            $this->fail('Se esperaba el fallo de auditoría.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Auditoría no disponible.', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertNull($authorizer->fresh()->pin_locked_until);
    }

    public function test_inactive_reason_code_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $reasonCodeId = $this->makeReasonCode('override', 'inactivo', isActive: 0);
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'reason_code_invalid',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
                reasonCodeId: $reasonCodeId,
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_reason_code_with_non_override_scope_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $reasonCodeId = $this->makeReasonCode('stock_adjustment', 'perdida', requiresNote: 1);
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'reason_code_invalid',
            fn () => (new PinAuthorizationService())->authorize(
                'stock_adjustment',
                $branch->id,
                $authorizer,
                '1234',
                reasonCodeId: $reasonCodeId,
                reason: 'Pérdida',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_reason_code_requiring_note_rejects_empty_reason(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $reasonCodeId = $this->makeReasonCode('override', 'requiere_nota', requiresNote: 1);
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'reason_note_required',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
                reasonCodeId: $reasonCodeId,
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'invalid_type',
            fn () => (new PinAuthorizationService())->authorize(
                'free_discount',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_request_without_session_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->assertDenied(
            'requester_unauthenticated',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_inactive_requester_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $requester = $this->makeUser(['global_role_id' => $this->role('seller')->id]);
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));
        $this->actingAs($requester);

        DB::table('users')->where('id', $requester->id)->update(['is_active' => 0]);

        $this->assertDenied(
            'requester_inactive',
            fn () => (new PinAuthorizationService())->authorize(
                'price_override',
                $branch->id,
                $authorizer,
                '1234',
            ),
        );

        $this->assertSame(0, DB::table('authorization_overrides')->count());
    }

    public function test_other_system_configs_remain_unchanged_after_failures(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $keys = ['pin_length', 'pin_max_attempts', 'override_min_authority'];
        $before = DB::table('system_configs')
            ->whereIn('config_key', $keys)
            ->pluck('config_value', 'config_key')
            ->toArray();

        $service = new PinAuthorizationService();

        $this->assertDenied(
            'pin_invalid',
            fn () => $service->authorize('price_override', $branch->id, $authorizer, '9999'),
        );
        $this->assertDenied(
            'pin_format_invalid',
            fn () => $service->authorize('price_override', $branch->id, $authorizer, '12'),
        );

        $after = DB::table('system_configs')
            ->whereIn('config_key', $keys)
            ->pluck('config_value', 'config_key')
            ->toArray();

        $this->assertSame($before, $after);
    }

    public function test_transactional_revalidation_rejects_permission_revoked_after_prevalidation(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $role = $this->role('supervisor');
        $authorizer = $this->makeAuthorizer($role);

        $permissionId = (int) DB::table('permissions')
            ->where('code', 'override.authorize')
            ->value('id');

        // La prevalidación ya pasó. La revocación ocurre al iniciar la
        // transacción del servicio y antes de sus locks: la revalidación
        // transaccional debe ser la que rechace.
        $this->revokeOnTransactionBeginning(function () use ($role, $permissionId): void {
            DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->where('permission_id', $permissionId)
                ->delete();
        });

        try {
            $this->assertDenied(
                'authorizer_permission_denied',
                fn () => (new PinAuthorizationService())->authorize(
                    'price_override',
                    $branch->id,
                    $authorizer,
                    '1234',
                ),
            );
        } finally {
            Event::forget(TransactionBeginning::class);
        }

        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertNull($authorizer->fresh()->pin_locked_until);
        $this->assertSame(
            0,
            DB::table('audit_logs')->where('event', 'authorization_override.approved')->count(),
        );
        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('event', 'authorization_override.denied')
                ->where('is_security_event', 1)
                ->where('new_values', 'like', '%authorizer_permission_denied%')
                ->count(),
        );
    }

    public function test_transactional_revalidation_rejects_branch_assignment_revoked_after_prevalidation(): void
    {
        $branch = $this->makeBranch();
        $requester = $this->makeUser(['global_role_id' => null]);
        DB::table('branch_user')->insert([
            'user_id' => $requester->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('seller')->id,
        ]);

        $authorizer = $this->makeUser([
            'global_role_id' => null,
            'pin_hash' => Hash::make('4321'),
        ]);
        DB::table('branch_user')->insert([
            'user_id' => $authorizer->id,
            'branch_id' => $branch->id,
            'role_id' => $this->role('supervisor')->id,
        ]);

        $this->actingAs($requester);

        $this->revokeOnTransactionBeginning(function () use ($authorizer, $branch): void {
            DB::table('branch_user')
                ->where('user_id', $authorizer->id)
                ->where('branch_id', $branch->id)
                ->delete();
        });

        try {
            $this->assertDenied(
                'authorizer_role_missing',
                fn () => (new PinAuthorizationService())->authorize(
                    'discount_over_cap',
                    $branch->id,
                    $authorizer,
                    '4321',
                ),
            );
        } finally {
            Event::forget(TransactionBeginning::class);
        }

        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertNull($authorizer->fresh()->pin_locked_until);
        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('event', 'authorization_override.denied')
                ->where('is_security_event', 1)
                ->where('new_values', 'like', '%authorizer_role_missing%')
                ->count(),
        );
        $this->assertSame(
            0,
            DB::table('audit_logs')->where('event', 'authorization_override.approved')->count(),
        );
    }

    public function test_transactional_revalidation_rejects_reason_code_deactivated_after_prevalidation(): void
    {
        $this->actingAs($this->admin);
        $branch = $this->makeBranch();
        $reasonCodeId = $this->makeReasonCode('override', 'revocado_en_transaccion');
        $authorizer = $this->makeAuthorizer($this->role('supervisor'));

        $this->revokeOnTransactionBeginning(function () use ($reasonCodeId): void {
            DB::table('reason_codes')->where('id', $reasonCodeId)->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);
        });

        try {
            $this->assertDenied(
                'reason_code_invalid',
                fn () => (new PinAuthorizationService())->authorize(
                    'price_override',
                    $branch->id,
                    $authorizer,
                    '1234',
                    reasonCodeId: $reasonCodeId,
                    reason: 'Motivo informado',
                ),
            );
        } finally {
            Event::forget(TransactionBeginning::class);
        }

        $this->assertSame(0, DB::table('authorization_overrides')->count());
        $this->assertSame(0, (int) $authorizer->fresh()->pin_failed_attempts);
        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('event', 'authorization_override.denied')
                ->where('is_security_event', 1)
                ->where('new_values', 'like', '%reason_code_invalid%')
                ->count(),
        );
        $this->assertSame(
            0,
            DB::table('audit_logs')->where('event', 'authorization_override.approved')->count(),
        );
    }

    /**
     * Ejecuta una sola revocación al iniciar la transacción del servicio.
     *
     * No se añaden hooks de test al código de producción: el evento real
     * `TransactionBeginning` dispara la revocación entre la prevalidación y
     * la adquisición de locks, y la revalidación real decide el rechazo.
     */
    private function revokeOnTransactionBeginning(callable $revoke): void
    {
        $revoked = false;

        Event::listen(TransactionBeginning::class, function () use (&$revoked, $revoke): void {
            if ($revoked) {
                return;
            }

            $revoked = true;
            $revoke();
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAuthorizer(Role $role, string $pin = '1234', array $overrides = []): User
    {
        return $this->makeUser(array_merge([
            'global_role_id' => $role->id,
            'pin_hash' => Hash::make($pin),
            'is_active' => true,
        ], $overrides));
    }

    private function makeLowAuthorityRoleWithOverridePermission(): Role
    {
        $roleId = (int) DB::table('roles')->insertGetId([
            'code' => 'baja_autoridad',
            'name' => 'Rol de baja autoridad',
            'authority_level' => 20,
            'is_system' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = (int) DB::table('permissions')
            ->where('code', 'override.authorize')
            ->value('id');

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);

        return Role::query()->findOrFail($roleId);
    }

    private function makePosStation(Branch $branch): int
    {
        return (int) DB::table('pos_stations')->insertGetId([
            'branch_id' => $branch->id,
            'code' => 'POS-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
            'name' => 'Estación de prueba',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeReasonCode(
        string $scope,
        string $code,
        int $requiresNote = 0,
        int $isActive = 1,
    ): int {
        return (int) DB::table('reason_codes')->insertGetId([
            'scope' => $scope,
            'code' => $code,
            'label' => 'Motivo '.$code,
            'requires_note' => $requiresNote,
            'is_active' => $isActive,
            'sort_order' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertDenied(string $reason, callable $callback): void
    {
        try {
            $callback();
        } catch (AuthorizationDeniedException $exception) {
            $this->assertSame($reason, $exception->reason);

            return;
        }

        $this->fail("Se esperaba un rechazo con motivo {$reason}.");
    }
}
