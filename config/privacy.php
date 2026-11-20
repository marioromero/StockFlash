<?php

// CUSCORE-001 · RN-58 · Clave del índice ciego de RUT.
//
// No existe fallback a APP_KEY ni a una clave estática: si la variable está
// vacía o es más corta que 32 caracteres, las operaciones que requieren RUT
// fallan de forma segura. Las operaciones de clientes sin RUT no dependen de
// esta clave.
return [
    'blind_index_key' => env('BLIND_INDEX_KEY'),
];
