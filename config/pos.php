<?php

/*
|--------------------------------------------------------------------------
| POS defaults
|--------------------------------------------------------------------------
|
| These values are used until the administrator saves restaurant settings.
| They intentionally contain no prices, tax rates, or contact information
| for the real restaurant. Everything operational is editable in Settings.
|
*/

return [
    'defaults' => [
        'restaurant.name' => 'Behar Kabab House',
        'restaurant.legal_name' => '',
        'restaurant.address' => '',
        'restaurant.phone' => '',
        'restaurant.email' => '',
        'restaurant.website' => 'https://beharkababhouse.com',
        'restaurant.tax_id' => '',
        'restaurant.logo_path' => null,

        'currency.code' => 'PKR',
        'currency.symbol' => 'Rs',
        'currency.decimals' => 2,
        'locale' => 'en',
        'timezone' => 'Asia/Karachi',

        'tax.enabled' => false,
        'tax.rate_bp' => 0,
        'tax.inclusive' => false,
        'tax.label' => 'Tax',

        'service_charge.enabled' => false,
        'service_charge.rate_bp' => 0,
        'service_charge.label' => 'Service charge',

        'discount.cashier_max_percent' => 0,
        'discount.manager_max_percent' => 100,
        'rounding.mode' => 'half_up',

        'receipt.width_mm' => 80,
        'receipt.header' => '',
        'receipt.footer' => 'Thank you for dining with Behar Kabab House.',
        'receipt.show_logo' => true,
        'receipt.show_tax_id' => true,
        'receipt.printer_name' => '',
        'receipt.copies' => 1,

        'kitchen.enabled' => true,
        'kitchen.printer_name' => '',
        'kitchen.warning_minutes' => 10,
        'kitchen.critical_minutes' => 20,

        'tables.enabled' => true,

        'backup.directory' => '',
        'backup.reminder_days' => 7,
        'backup.auto_reminder' => true,

        'orders.require_shift' => true,
        'orders.default_type' => 'takeaway',
        'orders.show_kitchen_after_checkout' => true,

        'app.theme' => 'dark',
        'app.version' => '0.1.0',
    ],

    'receipt_widths' => [
        58 => '58 mm',
        80 => '80 mm',
    ],

    'order_types' => [
        'dine_in' => 'Dine-in',
        'takeaway' => 'Takeaway',
        'delivery' => 'Delivery',
    ],
];
