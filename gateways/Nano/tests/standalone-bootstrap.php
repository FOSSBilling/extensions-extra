<?php

declare(strict_types=1);

/*
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * Lets the tests run with no FOSSBilling install: defines a stand-in for the
 * core's Payment_Exception only when it is missing, then loads the helpers.
 * Inside a FOSSBilling checkout the real classes are already loaded and the
 * stand-in is never declared.
 */

if (!class_exists('Payment_Exception')) {
    class Payment_Exception extends Exception
    {
        public function __construct(string $message = '', ?array $variables = null, int $code = 0)
        {
            parent::__construct($variables ? strtr($message, $variables) : $message, $code);
        }
    }
}

foreach (['Blake2b', 'Address', 'Amount', 'Binding', 'Verifier', 'ClaimsFile'] as $nanoHelper) {
    require_once dirname(__DIR__) . '/src/lib/' . $nanoHelper . '.php';
}
unset($nanoHelper);
