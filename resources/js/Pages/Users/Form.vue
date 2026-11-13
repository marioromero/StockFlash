<template>
    <div class="sf-page">
        <header class="sf-header">
            <div class="sf-brand">
                <span class="stock">Stock</span><span class="flash">Flash</span>
            </div>
            <div class="sf-user">
                <Link href="/dashboard" class="sf-button sf-button-secondary">Volver al dashboard</Link>
            </div>
        </header>

        <main class="sf-main">
            <div class="sf-card">
                <h1 class="sf-title">{{ isEditing ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
                <p class="sf-subtitle">
                    {{ isEditing
                        ? 'Actualice los datos, el acceso y las asignaciones del usuario. La contraseña y el PIN solo se cambian si se informan.'
                        : 'Cree una cuenta con contraseña temporal. El usuario deberá cambiarla en su primer ingreso.' }}
                </p>

                <div v-if="clientError" class="sf-message sf-error" role="alert">{{ clientError }}</div>
                <div v-if="generalError" class="sf-message sf-error" role="alert">{{ generalError }}</div>

                <form class="sf-form" @submit.prevent="submit">
                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Datos personales</legend>

                        <div class="sf-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="name">Nombre *</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    maxlength="150"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': errorFor('name') }"
                                    required
                                />
                                <p v-if="errorFor('name')" class="sf-error-text">{{ errorFor('name') }}</p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="email">Correo electrónico *</label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    maxlength="150"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': errorFor('email') }"
                                    autocomplete="off"
                                    required
                                />
                                <p class="sf-hint">Se guarda en minúsculas y es único incluso frente a cuentas eliminadas.</p>
                                <p v-if="errorFor('email')" class="sf-error-text">{{ errorFor('email') }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Credenciales</legend>

                        <div class="sf-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="password">
                                    {{ isEditing ? 'Nueva contraseña (opcional)' : 'Contraseña temporal *' }}
                                </label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    minlength="15"
                                    maxlength="64"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': errorFor('password') }"
                                    autocomplete="new-password"
                                    :required="!isEditing"
                                />
                                <p class="sf-hint">
                                    Entre 15 y 64 caracteres, sin reglas artificiales de composición. Al informarla, el usuario deberá cambiarla en su próximo ingreso y sus sesiones vigentes se invalidarán.
                                </p>
                                <p v-if="errorFor('password')" class="sf-error-text">{{ errorFor('password') }}</p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="pin">{{ isEditing ? 'Nuevo PIN (opcional)' : 'PIN (opcional)' }}</label>
                                <input
                                    id="pin"
                                    v-model="form.pin"
                                    type="password"
                                    inputmode="numeric"
                                    autocomplete="new-password"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': errorFor('pin') }"
                                    @input="onPinInput"
                                />
                                <p class="sf-hint">
                                    Debe tener exactamente la cantidad de dígitos configurada por el sistema. Se guarda solo como hash y nunca se muestra.
                                </p>
                                <p v-if="isEditing" class="sf-hint">
                                    Estado actual: <strong>{{ pinConfigured ? 'PIN configurado' : 'PIN no configurado' }}</strong>
                                </p>
                                <label v-if="isEditing && pinConfigured" class="sf-check">
                                    <input v-model="form.pin_clear" type="checkbox" @change="onPinClearChange" />
                                    <span>Quitar el PIN configurado</span>
                                </label>
                                <p v-if="errorFor('pin')" class="sf-error-text">{{ errorFor('pin') }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Tipo de acceso</legend>

                        <div class="sf-field">
                            <label class="sf-label" for="global_role_id">Rol global</label>
                            <select id="global_role_id" v-model="form.global_role_id" class="sf-input">
                                <option value="">Usuario por sucursal (sin rol global)</option>
                                <option v-for="role in catalogs.global_roles" :key="role.id" :value="String(role.id)">
                                    {{ role.name }}
                                </option>
                            </select>
                            <p class="sf-hint">
                                Un usuario global aplica su rol en todas las sucursales y no lleva asignaciones. El rol Admin Central solo puede ser global.
                            </p>
                            <p v-if="errorFor('global_role_id')" class="sf-error-text">{{ errorFor('global_role_id') }}</p>
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="default_branch_id">Sucursal inicial</label>
                            <select id="default_branch_id" v-model="form.default_branch_id" class="sf-input">
                                <option value="">Sin sucursal inicial</option>
                                <option v-for="branch in initialBranchOptions" :key="branch.id" :value="String(branch.id)">
                                    {{ branch.code }} — {{ branch.name }}
                                </option>
                            </select>
                            <p class="sf-hint">
                                Debe existir y estar activa. Para un usuario por sucursal, además debe estar entre sus asignaciones.
                            </p>
                            <p v-if="errorFor('default_branch_id')" class="sf-error-text">{{ errorFor('default_branch_id') }}</p>
                        </div>

                        <p v-if="errorFor('access')" class="sf-error-text">{{ errorFor('access') }}</p>
                    </fieldset>

                    <fieldset v-if="!isGlobal" class="sf-fieldset">
                        <legend class="sf-legend">Asignaciones por sucursal</legend>

                        <div v-if="form.assignments.length === 0" class="sf-message sf-warning" role="status">
                            Este usuario no tiene sucursales asignadas y no tendrá acceso operativo hasta que se le asigne una.
                        </div>

                        <div v-for="(assignment, index) in form.assignments" :key="index" class="sf-assignment-row">
                            <div class="sf-field">
                                <label class="sf-label" :for="`assignment-branch-${index}`">Sucursal</label>
                                <select
                                    :id="`assignment-branch-${index}`"
                                    v-model="assignment.branch_id"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': assignmentError(index, 'branch_id') }"
                                >
                                    <option value="">Seleccione una sucursal</option>
                                    <option
                                        v-for="branch in availableBranches(index)"
                                        :key="branch.id"
                                        :value="String(branch.id)"
                                    >
                                        {{ branch.code }} — {{ branch.name }}
                                    </option>
                                </select>
                                <p v-if="assignmentError(index, 'branch_id')" class="sf-error-text">{{ assignmentError(index, 'branch_id') }}</p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" :for="`assignment-role-${index}`">Rol en la sucursal</label>
                                <select
                                    :id="`assignment-role-${index}`"
                                    v-model="assignment.role_id"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': assignmentError(index, 'role_id') }"
                                >
                                    <option value="">Seleccione un rol</option>
                                    <option v-for="role in catalogs.branch_roles" :key="role.id" :value="String(role.id)">
                                        {{ role.name }}
                                    </option>
                                </select>
                                <p v-if="assignmentError(index, 'role_id')" class="sf-error-text">{{ assignmentError(index, 'role_id') }}</p>
                            </div>

                            <button type="button" class="sf-button sf-button-secondary sf-remove" @click="removeAssignment(index)">
                                Quitar
                            </button>
                        </div>

                        <button type="button" class="sf-button sf-button-secondary" @click="addAssignment">Agregar sucursal</button>
                        <p v-if="errorFor('assignments')" class="sf-error-text">{{ errorFor('assignments') }}</p>
                    </fieldset>

                    <fieldset class="sf-fieldset">
                        <legend class="sf-legend">Topes propios</legend>
                        <p class="sf-hint">
                            Nulo significa heredar el tope del rol (RN-02). No se exige que sean menores que el tope del rol.
                        </p>

                        <div class="sf-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="max_discount_percent">Descuento máximo (%)</label>
                                <input
                                    id="max_discount_percent"
                                    v-model="form.max_discount_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': errorFor('max_discount_percent') }"
                                />
                                <p v-if="errorFor('max_discount_percent')" class="sf-error-text">{{ errorFor('max_discount_percent') }}</p>
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="max_return_amount">Monto máximo de devolución</label>
                                <input
                                    id="max_return_amount"
                                    v-model="form.max_return_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="sf-input"
                                    :class="{ 'sf-input-error': errorFor('max_return_amount') }"
                                />
                                <p v-if="errorFor('max_return_amount')" class="sf-error-text">{{ errorFor('max_return_amount') }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <p class="sf-hint">
                        El estado activo no se modifica en este formulario. Use las acciones Activar o Desactivar del listado de usuarios.
                    </p>

                    <div class="sf-actions">
                        <button type="button" class="sf-button sf-button-secondary" :disabled="form.processing" @click="cancel">
                            Cancelar
                        </button>
                        <button type="submit" class="sf-button sf-button-primary" :disabled="form.processing">
                            {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear usuario') }}
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

interface RoleRef {
    id: number;
    code: string;
    name: string;
}

interface BranchRef {
    id: number;
    code: string;
    name: string;
}

interface AssignmentData {
    branch_id: number;
    branch_code: string | null;
    branch_name: string | null;
    role_id: number;
    role_code: string | null;
    role_name: string | null;
}

interface UserFormData {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    access_type: 'global' | 'branch';
    global_role_id: number | null;
    default_branch_id: number | null;
    assignments: AssignmentData[];
    pin_configured: boolean;
    max_discount_percent: number | null;
    max_return_amount: number | null;
}

interface Catalogs {
    global_roles: RoleRef[];
    branch_roles: RoleRef[];
    branches: BranchRef[];
}

interface AssignmentFormRow {
    branch_id: string;
    role_id: string;
}

const props = defineProps<{
    user: UserFormData | null;
    catalogs: Catalogs;
}>();

const isEditing = computed(() => props.user !== null);
const pinConfigured = computed(() => props.user?.pin_configured ?? false);
const clientError = ref<string | null>(null);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    pin: '',
    pin_clear: false,
    global_role_id: props.user?.global_role_id !== null && props.user?.global_role_id !== undefined
        ? String(props.user.global_role_id)
        : '',
    default_branch_id: props.user?.default_branch_id !== null && props.user?.default_branch_id !== undefined
        ? String(props.user.default_branch_id)
        : '',
    max_discount_percent: props.user?.max_discount_percent !== null && props.user?.max_discount_percent !== undefined
        ? String(props.user.max_discount_percent)
        : '',
    max_return_amount: props.user?.max_return_amount !== null && props.user?.max_return_amount !== undefined
        ? String(props.user.max_return_amount)
        : '',
    assignments: (props.user?.assignments ?? []).map((assignment): AssignmentFormRow => ({
        branch_id: String(assignment.branch_id),
        role_id: String(assignment.role_id),
    })) as AssignmentFormRow[],
});

const isGlobal = computed(() => form.global_role_id !== '');

const assignedBranchIds = computed(() =>
    form.assignments
        .map((assignment) => assignment.branch_id)
        .filter((branchId) => branchId !== '')
        .map((branchId) => Number(branchId))
);

const initialBranchOptions = computed(() => {
    if (isGlobal.value) {
        return props.catalogs.branches;
    }

    if (assignedBranchIds.value.length === 0) {
        return [];
    }

    return props.catalogs.branches.filter((branch) => assignedBranchIds.value.includes(branch.id));
});

// MENOR-2: si la sucursal inicial deja de ser válida por un cambio de
// asignaciones o de tipo de acceso, se limpia para no conservar un id oculto.
watch(
    initialBranchOptions,
    (options) => {
        if (form.default_branch_id === '') {
            return;
        }

        const stillValid = options.some((branch) => String(branch.id) === form.default_branch_id);

        if (!stillValid) {
            form.default_branch_id = '';
        }
    },
    { immediate: true },
);

const generalError = computed(() => {
    const keys = ['license', 'max_users', 'user', 'general'] as const;

    for (const key of keys) {
        const message = errorFor(key);

        if (message) {
            return message;
        }
    }

    return null;
});

function errorFor(field: string): string | undefined {
    return (form.errors as Record<string, string>)[field];
}

function assignmentError(index: number, field: 'branch_id' | 'role_id'): string | undefined {
    return errorFor(`assignments.${index}.${field}`);
}

function availableBranches(index: number): BranchRef[] {
    const selected = form.assignments
        .filter((_, rowIndex) => rowIndex !== index)
        .map((assignment) => assignment.branch_id)
        .filter((branchId) => branchId !== '');

    return props.catalogs.branches.filter((branch) => !selected.includes(String(branch.id)));
}

function addAssignment(): void {
    form.assignments.push({ branch_id: '', role_id: '' });
}

function removeAssignment(index: number): void {
    form.assignments.splice(index, 1);
}

function onPinInput(): void {
    if (form.pin !== '') {
        form.pin_clear = false;
    }
}

function onPinClearChange(): void {
    if (form.pin_clear) {
        form.pin = '';
    }
}

function submit(): void {
    clientError.value = null;

    const hasIncompleteAssignment = form.assignments.some(
        (assignment) => assignment.branch_id === '' || assignment.role_id === ''
    );

    if (hasIncompleteAssignment) {
        clientError.value = 'Complete la sucursal y el rol de cada asignación, o elimine las filas incompletas.';
        return;
    }

    form.transform((data) => {
        const payload: Record<string, unknown> = {
            name: data.name,
            email: data.email,
            global_role_id: data.global_role_id === '' ? null : Number(data.global_role_id),
            default_branch_id: data.default_branch_id === '' ? null : Number(data.default_branch_id),
            max_discount_percent: data.max_discount_percent === '' ? null : data.max_discount_percent,
            max_return_amount: data.max_return_amount === '' ? null : data.max_return_amount,
            pin_clear: data.pin_clear,
            assignments: data.global_role_id === ''
                ? data.assignments.map((assignment) => ({
                    branch_id: Number(assignment.branch_id),
                    role_id: Number(assignment.role_id),
                }))
                : [],
        };

        if (data.password !== '') {
            payload.password = data.password;
        }

        if (data.pin !== '') {
            payload.pin = data.pin;
        }

        return payload;
    });

    if (props.user) {
        form.put(`/users/${props.user.id}`);
    } else {
        form.post('/users');
    }
}

function cancel(): void {
    router.get('/users');
}
</script>

<style scoped>
.sf-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f3f4f6;
}

.sf-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1rem 2rem;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
}

.sf-brand {
    font-size: 1.5rem;
    font-weight: 800;
    color: #111827;
}

.sf-brand .stock {
    color: #0f172a;
}

.sf-brand .flash {
    color: #2563eb;
}

.sf-user {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.sf-main {
    flex: 1;
    width: 100%;
    max-width: 960px;
    margin: 0 auto;
    padding: 2rem;
}

.sf-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sf-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
}

.sf-subtitle {
    margin: 0.35rem 0 1.25rem;
    font-size: 0.875rem;
    color: #4b5563;
    line-height: 1.5;
}

.sf-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.sf-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.sf-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sf-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
}

.sf-input {
    width: 100%;
    padding: 0.55rem 0.7rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.95rem;
    background: #ffffff;
    color: #111827;
}

.sf-input:focus {
    border-color: #2563eb;
    outline: 2px solid #bfdbfe;
    outline-offset: 0;
}

.sf-input-error {
    border-color: #dc2626;
}

.sf-fieldset {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sf-legend {
    padding: 0 0.35rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: #111827;
}

.sf-check {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 0.9rem;
    color: #374151;
}

.sf-assignment-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
    gap: 0.75rem;
    align-items: end;
    padding: 0.75rem;
    border: 1px dashed #d1d5db;
    border-radius: 0.5rem;
}

.sf-remove {
    align-self: end;
}

.sf-hint {
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.4;
}

.sf-error-text {
    font-size: 0.75rem;
    color: #b91c1c;
}

.sf-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.sf-button {
    display: inline-block;
    padding: 0.55rem 1rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.sf-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sf-button-primary {
    background: #2563eb;
    color: #ffffff;
}

.sf-button-primary:hover:not(:disabled) {
    background: #1d4ed8;
}

.sf-button-secondary {
    background: #e5e7eb;
    color: #111827;
}

.sf-button-secondary:hover:not(:disabled) {
    background: #d1d5db;
}

.sf-message {
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.sf-warning {
    background: #fef3c7;
    color: #92400e;
}

.sf-error {
    background: #fee2e2;
    color: #991b1b;
    margin-bottom: 1rem;
}

@media (max-width: 760px) {
    .sf-header {
        padding: 1rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .sf-main {
        padding: 1rem;
    }

    .sf-card {
        padding: 1rem;
    }

    .sf-grid {
        grid-template-columns: 1fr;
    }

    .sf-assignment-row {
        grid-template-columns: 1fr;
    }

    .sf-actions {
        flex-direction: column-reverse;
    }

    .sf-actions .sf-button {
        width: 100%;
    }
}
</style>
