<?php

return [
    'master_secret_hex'  => env('BLE_MASTER_SECRET_HEX', ''),
    'derive_salt'        => env('BLE_DERIVE_SALT', 'BLE_DERIVE_SALT_V1'),
    'derive_info_prefix' => env('BLE_DERIVE_INFO_PREFIX', 'ble-symm-v1|'),
];
