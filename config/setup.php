<?php

return [
    /*
     * SETUP-001 · BKP-08 P1★.
     *
     * El paquete de recuperación verificable BKP-08 NO está implementado.
     * Por eso el onboarding en producción no demo está bloqueado con el
     * código explícito NOT_READY_FOR_PRODUCTION. Estas banderas se dejan en
     * `false` deliberadamente: activarlas sin un paquete real simularía una
     * capacidad inexistente.
     */
    'production_onboarding_enabled' => false,
    'recovery_package_available' => false,
];
