<?php
declare(strict_types=1);

namespace Marwa\ErrorHandler\Support;

use Marwa\ErrorHandler\Contracts\RendererInterface;
use Throwable;

final class FallbackRenderer implements RendererInterface
{
    public function renderException(Throwable $e, string $appName, bool $dev): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo $dev
            ? $this->devExceptionHtml($e, $appName, $this->requestId(), $this->utcNow())
            : $this->prodGenericHtml($appName, $this->requestId(), $this->utcNow());
    }

    public function renderGeneric(string $appName): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo $this->prodGenericHtml($appName, $this->requestId(), $this->utcNow());
    }

    public function renderCli(Throwable $e, string $appName, bool $dev): void
    {
        $rid = $this->requestId();
        if ($dev) {
            fwrite(STDERR, "[500][{$appName}] " . get_class($e) . ": {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()} [rid:{$rid}]\n");
            $i = 0;
            foreach ($e->getTrace() as $t) {
                $file = (string)($t['file'] ?? '-');
                $line = (int)($t['line'] ?? 0);
                $func = (string)(($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''));
                fwrite(STDERR, "  #{$i} {$file}:{$line} {$func}\n");
                if (++$i >= 10) break;
            }
            if ($i === 0) {
                foreach (explode("\n", $e->getTraceAsString()) as $line) {
                    fwrite(STDERR, "  " . $line . "\n");
                }
            }
        } else {
            fwrite(STDERR, "[500][{$appName}] An error occurred. [rid:{$rid}]\n");
        }
    }

    /* ---------- Internals / Theming (auto light/dark) ---------- */

    private function devExceptionHtml(Throwable $e, string $brand, string $rid, string $ts): string
    {
        $esc = static fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $msg = $esc((string)$e->getMessage());
        $cls = $esc((string)$e::class);
        $fil = $esc((string)$e->getFile());
        $lin = (int)$e->getLine();

        $traceHtml = $this->buildTraceHtml($e, $esc);
        $phpVersion = PHP_VERSION;
        $phpSapi = PHP_SAPI;
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>{$brand} • Exception</title>
<style>
/* Base (light theme defaults) */
:root{
  --bg:#f8fafc; --bg-grad:#ffffff;
  --card:#ffffff; --muted:#64748b; --fg:#0f172a;
  --accent:#0ea5e9; --bad:#dc2626; --border:rgba(2,6,23,.1);
  --shadow:0 10px 30px rgba(2,6,23,.08);
}

/* Auto dark theme */
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
        <h1><span class="code">{$cls}</span>: {$msg}</h1>
        <div class="meta">Thrown at <b>{$fil}</b>:<b>{$lin}</b></div>

        <div class="section">Details</div>
        <div class="kv">
          <b>Status</b><span>500 Internal Server Error</span>
          <b>Request ID</b><span>{$rid}</span>
          <b>When</b><span>{$ts}</span>
          <b>PHP</b><span>{$phpVersion} • {$phpSapi}</span>
        </div>

        <div class="section">Trace (top 12)</div>
        <div class="trace">{$traceHtml}</div>
      </div>
      <div class="footer">
        <div>This is a development-only page (no logger/debugbar detected).</div>
        <div>&copy; {$brand}</div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function prodGenericHtml(string $brand, string $rid, string $ts): string
    {
        $title = "{$brand} • Error";
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>{$title}</title>
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
        <div class="meta">Request ID: {$rid} • {$ts}</div>
      </div>
      <div class="footer">&copy; {$brand}</div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    /** Build the trace HTML safely. Falls back to getTraceAsString() if needed. */
    private function buildTraceHtml(Throwable $e, callable $esc): string
    {
        $rows = [];
        $trace = $e->getTrace();

        if (!empty($trace)) {
            $i = 0;
            foreach ($trace as $t) {
                $file = $esc((string)($t['file'] ?? '-'));
                $line = (int)($t['line'] ?? 0);
                $call = $esc((string)(($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '')));
                $rows[] = "<div class=\"trace-row\"><code>#{$i}</code> <span>{$file}:{$line}</span> <em>{$call}</em></div>";
                if (++$i >= 12) break;
            }
        } else {
            $lines = preg_split('/\r\n|\r|\n/', $e->getTraceAsString()) ?: [];
            $i = 0;
            foreach ($lines as $line) {
                $rows[] = '<div class="trace-row"><code>#'.$i.'</code> <span>'.$esc($line).'</span></div>';
                if (++$i >= 12) break;
            }
            if ($i === 0) {
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
        $s = $_SERVER ?? [];
        return $s['HTTP_X_REQUEST_ID'] ?? $s['HTTP_X_CORRELATION_ID'] ?? ('r-' . bin2hex(random_bytes(6)));
    }
}
