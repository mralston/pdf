# PDF 

## Introduction

A convenient way to generate PDF files in Laravel using a headless Chrome/Chromium instance.

This package is a wrapper around [chrome-php/chrome](https://packagist.org/packages/chrome-php/chrome).

## Config

You may publish the config file as follows:

```bash
php artisan vendor:publish --tag=pdf-config
```

## Usage

Here are now the basic functions of the wrapper library work:

```php
use Mralston\Pdf\Pdf;

// Can be instantiated using a view
$pdf = Pdf::fromView('path.to.blade-template', [
    'variable1' => $value1,
    'variable2' => $value2,
]);

// Or with HTML
$html = '<html><head><title>test</title></head><body>...</body></html>';

$pdf = Pdf::fromHtml($html);

// Or from a URL
$pdf = Pdf::fromUrl('https://www.google.com/');

// The path to the Chrome executable can be adjusted (defaults to /usr/bin/chromium)
// This can also be done using the CHROME_BINARY environment variable
$pdf->setChromeBinary('/bin/chrome');

// Options can be passed to the Chrome rendering engine
$pdf->setOptions([
    'landscape' => true,
    'scale' => 0.8,
]);

// It can be instructed to emulate PhantomJS (your mileage may vary)
$pdf->emulatePhantomJs();

// The timeout can be adjusted for slow pages (defaults to 30 seconds)
$pdf->setTimeout(60);

// The PDF can be saved to disk
$pdf->save('/path/to/output.pdf');

// Or streamed to the browser
return $pdf->stream();

// Or sent to the browser as a download
return $pdf->download($filename);
```

Putting it all together into a (somewhat) real-world example:

```php
use Mralston\Pdf\Pdf;
use App\Models\Invoice;
use Illuminate\Http\Response;

class InvoiceController
{
    public function downloadPdf(Invoice $invoice, string $filename): Response
    {
        return Pdf::fromView('invoices.show', [
            'invoice' => $invoice,
        ])
        ->setChromeBinary('/usr/bin/chrome')
        ->setOptions([
            'landscape' => true,
            'scale' => 0.9,
        ])
        ->setTimeout(60)
        ->download($filename);
    }
}
```

## Security Vulnerabilities

Please [e-mail security vulnerabilities directly to me](mailto:matt@mralston.co.uk).

## License

PDF is open-sourced software licensed under the [MIT license](LICENCE.md).