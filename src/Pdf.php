<?php

namespace Mralston\Pdf;

use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\AbstractBinaryInput;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Pdf
{
    private ?string $file = null;
    private ?string $url = null;
    private ?string $html = null;
    private ?string $view = null;
    private array $data = [];

    private bool $emulatePhantomJs = false;

    private ?string $securityToken = null;

    private array $requestHeaders = [];

    private ?array $options = [
        'marginTop' => 0,
        'marginBottom' => 0,
        'marginLeft' => 0,
        'marginRight' => 0,
        'paperWidth' => 8.3, // A4 width in inches
        'paperHeight' => 11.7, // A4 height in inches
        'printBackground' => true,
    ];
    private ?string $chromeBinary;

    private int $timeout = 30;

    private ?AbstractBinaryInput $pdf = null;
    private $browser = null;

    private ?int $source = null;

    private const SOURCE_FILE = 1;
    private const SOURCE_URL = 2;
    private const SOURCE_HTML = 3;
    private const SOURCE_VIEW = 4;

    public function __construct()
    {
        $this->setSecurityToken(config('pdf.security_token'));
    }

    public static function fromFile(?string $file = null): self
    {
        return (new Pdf())
            ->loadFile($file);
    }

    public static function fromUrl(?string $url = null): self
    {
        return (new Pdf())
            ->loadUrl($url);
    }

    public static function fromHtml(?string $html = null): self
    {
        return (new Pdf())
            ->loadHtml($html);
    }

    public static function fromView(?string $view = null, ?array $data = []): self
    {
        return (new Pdf())
            ->loadView($view, $data);
    }

    public function loadFile(?string $file = null): self
    {
        $this->file = $file;

        $this->source = self::SOURCE_FILE;

        return $this;
    }

    public function loadUrl(?string $url = null): self
    {
        $this->url = $url;

        $this->source = self::SOURCE_URL;

        return $this;
    }

    public function loadHtml(?string $html = null): self
    {
        $this->html = $html;

        $this->source = self::SOURCE_HTML;

        return $this;
    }

    public function loadView(?string $view = null, ?array $data = []): self
    {
        $this->view = $view;
        $this->data = $data;

        $this->source = self::SOURCE_VIEW;

        return $this;
    }

    public function setOptions(?array $options = []): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    public function setSecurityToken(?string $securityToken = null)
    {
        $this->securityToken = $securityToken;
    }

    public function setRequestHeaders(array $requestHeaders = [])
    {
        $this->requestHeaders = $requestHeaders;
    }

    public function emulatePhantomJs(): self
    {
        $this->emulatePhantomJs = true;

        return $this;
    }

    public function setChromeBinary(?string $chromeBinary): self
    {
        $this->chromeBinary = $chromeBinary;

        return $this;
    }

    public function setTimeout(?int $timeout = 30)
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function render(): self
    {
        switch ($this->source) {
            case self::SOURCE_FILE:
                return $this->renderFile();
                break;
            case self::SOURCE_URL:
                return $this->renderUrl();
                break;
            case self::SOURCE_HTML:
                return $this->renderHtml();
                break;
            case self::SOURCE_VIEW:
                return $this->renderView();
                break;
        }

        throw new \Exception('Invalid source.');
    }

    private function renderView(): self
    {
        return $this->renderHtml(view($this->view, $this->data));
    }

    private function renderHtml(?string $html = null): self
    {
        $tmpFile = tempnam(sys_get_temp_dir(), Str::random()) . '.html';

        file_put_contents($tmpFile, $html ?? $this->html);

        $result = $this->renderUrl('file://' . $tmpFile);

        unset($tmpFile);

        return $result;
    }

    private function renderFile(): self
    {
        return $this->renderUrl('file://' . $this->file);
    }

    private function renderUrl(?string $url = null): self
    {
        $this->pdf = null;

        $browserFactory = new BrowserFactory($this->chromeBinary ?? config('pdf.chrome_binary'));

        // starts headless chrome
        $this->browser = $browserFactory->createBrowser([
            'noSandbox' => true,
            'ignoreCertificateErrors' => true,
            'headers' => $this->prepareRequestHeaders(),
        ]);

        $page = $this->browser->createPage();

        $page->navigate($url ?? $this->url)
            ->waitForNavigation(Page::LOAD, $this->timeout * 1000);

        $this->pdf = $page->pdf(array_merge(
            $this->options,
            $this->emulatePhantomJs ? ['preferCSSPageSize' => true] : []
        ));

        return $this;
    }

    private function prepareRequestHeaders()
    {
        $headers = $this->requestHeaders;

        if ($this->emulatePhantomJs) {
            $headers['User-Agent'] = 'Mozilla/5.0 (Unknown; Linux x86_64) AppleWebKit/538.1 (KHTML, like Gecko) PhantomJS/2.1.1 Safari/538.1';
        }

        if (!empty($this->securityToken)) {
            $headers['X-Security-Token'] = $this->securityToken;
        }

        return $headers;
    }

    public function save(string $filename): void
    {
        if (empty($this->pdf)) {
            $this->render();
        }

        $this->pdf->saveToFile($filename, $this->timeout * 1000);

        $this->browser->close();
        $this->pdf = null;
    }

    public function output(): string
    {
        if (empty($this->pdf)) {
            $this->render();
        }

        $base64 = $this->pdf
            ->getResponseReader()
            ->waitForResponse(5000)
            ->getResultData('data');

        $this->browser->close();
        $this->pdf = null;

        return base64_decode($base64);
    }

    public function stream(): Response
    {
        if (empty($this->pdf)) {
            $this->render();
        }

        $base64 = $this->pdf
            ->getResponseReader()
            ->waitForResponse(5000)
            ->getResultData('data');

        $this->browser->close();
        $this->pdf = null;

        return new Response(base64_decode($base64), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function download(?string $filename = 'document.pdf'): Response
    {
        if (empty($this->pdf)) {
            $this->render();
        }

        $base64 = $this->pdf
            ->getResponseReader()
            ->waitForResponse(5000)
            ->getResultData('data');

        $this->browser->close();
        $this->pdf = null;

        return new Response(base64_decode($base64), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' =>  'attachment; filename="' . $filename . '"'
        ]);
    }
}
