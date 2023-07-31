<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chrome Binary
    |--------------------------------------------------------------------------
    |
    | Fully qualified path to the Chrome or
    | Chromium binary which is used to
    | render the PDF.
    |
    */

    'chrome_binary' => env('CHROME_BINARY', '/usr/bin/chromium'),

    /*
    |--------------------------------------------------------------------------
    | Security Token
    |--------------------------------------------------------------------------
    |
    | Security token used for extremely lightweight authentication.
    | If set, this is sent as an X-Security-Token header when
    | rending a PDF file from a URL.
    |
    */

    'security_token' => env('PDF_SECURITY_TOKEN'),
];
