<?php
return [
    'idp_dev_token' => getenv('IDP_DEV_TOKEN') ?: '18c2b0ab927d8a3c9bf9ef78419a8f6d4535e47f',
    'services_url' => getenv('SERVICES_URL') ?: 'testpoint.io',
    'provider' => getenv('PROVIDER') ?: 'quickbooks',
    'tap_gw_app_id' => getenv('TAP_GW_APP_ID') ?: 945682,
    'dcp_app_id' => getenv('DCP_APP_ID') ?: 274953,
];