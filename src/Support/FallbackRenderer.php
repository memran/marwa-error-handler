<?php

declare(strict_types=1);

namespace Marwa\ErrorHandler\Support;

use Marwa\ErrorHandler\Contracts\RendererInterface;
use Throwable;

/**
 * Renders safe fallback pages when no framework or custom renderer is present.
 */
final class FallbackRenderer implements RendererInterface
{
    public function renderException(Throwable $e, string $appName, bool $dev): void
    {
        $this->sendHtmlHeaders();

        echo $dev
            ? $this->devExceptionHtml($e, $appName, $this->requestId(), $this->utcNow())
            : $this->prodGenericHtml($appName, $this->requestId(), $this->utcNow());
    }

    public function renderGeneric(string $appName): void
    {
        $this->sendHtmlHeaders();

        echo $this->prodGenericHtml($appName, $this->requestId(), $this->utcNow());
    }

    public function renderCli(Throwable $e, string $appName, bool $dev): void
    {
        $requestId = $this->requestId();

        if ($dev) {
            fwrite(
                STDERR,
                sprintf(
                    "[500][%s] %s: %s @ %s:%d [rid:%s]\n",
                    $appName,
                    $e::class,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $requestId,
                ),
            );

            $index = 0;
            foreach ($e->getTrace() as $frame) {
                $file = (string) ($frame['file'] ?? '-');
                $line = (int) ($frame['line'] ?? 0);
                $function = (string) (($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function']);
                fwrite(STDERR, sprintf("  #%d %s:%d %s\n", $index, $file, $line, $function));

                if (++$index >= 10) {
                    break;
                }
            }

            if ($index === 0) {
                foreach (preg_split('/\r\n|\r|\n/', $e->getTraceAsString()) ?: [] as $traceLine) {
                    fwrite(STDERR, sprintf("  %s\n", $traceLine));
                }
            }

            return;
        }

        fwrite(STDERR, sprintf("[500][%s] An error occurred. [rid:%s]\n", $appName, $requestId));
    }

    private function sendHtmlHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
    }

    private function devExceptionHtml(Throwable $e, string $appName, string $requestId, string $timestamp): string
    {
        $message = $this->escape($e->getMessage());
        $className = $this->escape($e::class);
        $file = $this->escape($e->getFile());
        $line = (int) $e->getLine();
        $brand = $this->escape($appName);
        $safeRequestId = $this->escape($requestId);
        $safeTimestamp = $this->escape($timestamp);
        $traceHtml = $this->buildTraceHtml($e);
        $phpVersion = $this->escape(PHP_VERSION);
        $phpSapi = $this->escape(PHP_SAPI);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>{$brand} | Exception</title>
<style>
:root{
  --bg:#f8fafc; --bg-grad:#ffffff;
  --card:#ffffff; --muted:#64748b; --fg:#0f172a;
  --accent:#0ea5e9; --bad:#dc2626; --border:rgba(2,6,23,.1);
  --shadow:0 10px 30px rgba(2,6,23,.08);
}
@media (prefers-color-scheme: dark) {
  :root{
    --bg:#0f172a; --bg-grad:#0b1220;
    --card:rgba(17,24,39,.85); --muted:#94a3b8; --fg:#e5e7eb;
    --accent:#22d3ee; --bad:#fb7185; --border:rgba(148,163,184,.15);
    --shadow:0 10px 30px rgba(0,0,0,.35);
  }
}
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(180deg,var(--bg),var(--bg-grad));color:var(--fg);
     font:400 14px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial}
.container{max-width:980px;margin:48px auto;padding:0 20px}
.card{background:var(--card);backdrop-filter:blur(6px);border:1px solid var(--border);
      border-radius:16px;overflow:hidden;box-shadow:var(--shadow)}
.header{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;border-bottom:1px solid var(--border)}
.brand{font-weight:700;letter-spacing:.3px}
.badge{padding:6px 10px;border-radius:999px;background:color-mix(in oklab, var(--accent) 15%, transparent);color:var(--accent);font-weight:600}
.body{padding:20px}
h1{margin:0 0 10px;font-size:18px}
.meta{color:var(--muted);font-size:12px;margin-bottom:14px}
.kv{display:grid;grid-template-columns:140px 1fr;gap:6px 12px;margin:12px 0}
.kv b{opacity:.9}
.section{margin:18px 0 6px;opacity:.9;font-weight:600}
.trace{background:color-mix(in oklab, var(--bg) 86%, black 0%);border:1px solid var(--border);
       border-radius:10px;padding:10px;max-height:360px;overflow:auto}
.trace-row{display:flex;gap:10px;padding:6px 0;border-bottom:1px dashed var(--border)}
.trace-row:last-child{border-bottom:0}
.footer{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-top:1px solid var(--border);color:var(--muted);font-size:12px}
.code{color:var(--bad)}
</style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="header">
        <div class="brand">{$brand}</div>
        <div class="badge">Dev Exception</div>
      </div>
      <div class="body">
        <h1><span class="code">{$className}</span>: {$message}</h1>
        <div class="meta">Thrown at <b>{$file}</b>:<b>{$line}</b></div>

        <div class="section">Details</div>
        <div class="kv">
          <b>Status</b><span>500 Internal Server Error</span>
          <b>Request ID</b><span>{$safeRequestId}</span>
          <b>When</b><span>{$safeTimestamp}</span>
          <b>PHP</b><span>{$phpVersion} | {$phpSapi}</span>
        </div>

        <div class="section">Trace (top 12)</div>
        <div class="trace">{$traceHtml}</div>
      </div>
      <div class="footer">
        <div>This is a development-only page rendered by the fallback renderer.</div>
        <div>&copy; {$brand}</div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function prodGenericHtml(string $appName, string $requestId, string $timestamp): string
    {
        $brand = $this->escape($appName);
        $safeRequestId = $this->escape($requestId);
        $safeTimestamp = $this->escape($timestamp);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>{$brand} | Error</title>
<style>
:root{
  --bg:#f8fafc; --bg-grad:#ffffff;
  --card:#ffffff; --muted:#64748b; --fg:#0f172a;
  --accent:#0ea5e9; --border:rgba(2,6,23,.1);
  --shadow:0 10px 30px rgba(2,6,23,.08);
}
@media (prefers-color-scheme: dark) {
  :root{
    --bg:#0f172a; --bg-grad:#0b1220;
    --card:rgba(17,24,39,.85); --muted:#94a3b8; --fg:#e5e7eb;
    --accent:#22d3ee; --border:rgba(148,163,184,.15);
    --shadow:0 10px 30px rgba(0,0,0,.35);
  }
}
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(180deg,var(--bg),var(--bg-grad));color:var(--fg);
     font:400 14px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial}
.container{max-width:720px;margin:20vh auto;padding:0 20px}
.card{background:var(--card);backdrop-filter:blur(6px);border:1px solid var(--border);
      border-radius:16px;overflow:hidden;box-shadow:var(--shadow);text-align:center}
.header{padding:20px 22px;border-bottom:1px solid var(--border);font-weight:700}
.body{padding:26px 22px}
h1{margin:0 0 10px;font-size:20px}
.meta{color:var(--muted);font-size:12px;margin-top:12px}
.footer{padding:12px 18px;border-top:1px solid var(--border);color:var(--muted);font-size:12px}
.badge{display:inline-block;margin-top:8px;padding:6px 10px;border-radius:999px;
       background:color-mix(in oklab, var(--accent) 15%, transparent);color:var(--accent);font-weight:600}
</style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="header">{$brand}</div>
      <div class="body">
        <h1>Something went wrong</h1>
        <div>Please try again later.</div>
        <div class="badge">500 Internal Server Error</div>
        <div class="meta">Request ID: {$safeRequestId} | {$safeTimestamp}</div>
      </div>
      <div class="footer">&copy; {$brand}</div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function buildTraceHtml(Throwable $e): string
    {
        $rows = [];
        $trace = $e->getTrace();

        if ($trace !== []) {
            $index = 0;

            foreach ($trace as $frame) {
                $file = $this->escape((string) ($frame['file'] ?? '-'));
                $line = (int) ($frame['line'] ?? 0);
                $call = $this->escape((string) (($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function']));
                $rows[] = sprintf(
                    '<div class="trace-row"><code>#%d</code> <span>%s:%d</span> <em>%s</em></div>',
                    $index,
                    $file,
                    $line,
                    $call,
                );

                if (++$index >= 12) {
                    break;
                }
            }
        } else {
            $index = 0;

            foreach (preg_split('/\r\n|\r|\n/', $e->getTraceAsString()) ?: [] as $traceLine) {
                $rows[] = sprintf(
                    '<div class="trace-row"><code>#%d</code> <span>%s</span></div>',
                    $index,
                    $this->escape($traceLine),
                );

                if (++$index >= 12) {
                    break;
                }
            }

            if ($index === 0) {
                $rows[] = '<div class="trace-row"><em>No trace available.</em></div>';
            }
        }

        return implode('', $rows);
    }

    private function utcNow(): string
    {
        return gmdate('Y-m-d H:i:s \U\T\C');
    }

    private function requestId(): string
    {
        foreach (['HTTP_X_REQUEST_ID', 'HTTP_X_CORRELATION_ID'] as $headerName) {
            $candidate = $_SERVER[$headerName] ?? null;

            if (is_string($candidate) && preg_match('/\A[a-zA-Z0-9._:-]{1,128}\z/', $candidate) === 1) {
                return $candidate;
            }
        }

        try {
            return 'r-' . bin2hex(random_bytes(6));
        } catch (Throwable) {
            return 'r-' . str_replace('.', '', uniqid('', true));
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
