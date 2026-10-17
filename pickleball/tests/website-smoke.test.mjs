import test from "node:test";
import assert from "node:assert/strict";
import { existsSync, readdirSync, readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const TEST_ROOT = dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = resolve(TEST_ROOT, "..");

function executableWorks(candidate) {
  if (!candidate) return false;
  const result = spawnSync(candidate, ["-v"], { encoding: "utf8", windowsHide: true });
  return result.status === 0;
}

function findPhpBinary() {
  const candidates = [process.env.PHP_BIN, "php"];
  if (process.platform === "win32") {
    const laragonPhp = "C:\\laragon\\bin\\php";
    if (existsSync(laragonPhp)) {
      const installations = readdirSync(laragonPhp, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => join(laragonPhp, entry.name, "php.exe"))
        .sort()
        .reverse();
      candidates.push(...installations);
    }
  }
  return candidates.find(executableWorks) ?? null;
}

const phpBinary = findPhpBinary();

function runPhp(args) {
  assert.ok(phpBinary, "PHP was not found. Set PHP_BIN or install PHP before running website smoke tests.");
  return spawnSync(phpBinary, args, {
    cwd: PROJECT_ROOT,
    encoding: "utf8",
    windowsHide: true,
  });
}

function renderPage(file) {
  const result = runPhp([join(PROJECT_ROOT, file)]);
  const diagnostics = `${result.stderr ?? ""}\n${result.stdout ?? ""}`;
  assert.equal(result.status, 0, `${file} failed to render:\n${diagnostics}`);
  assert.doesNotMatch(diagnostics, /(?:PHP )?(?:warning|fatal error|parse error|deprecated|notice):/i, `${file} emitted a PHP diagnostic.`);
  assert.match(result.stdout, /<!doctype html>/i, `${file} did not render an HTML document.`);
  return result.stdout;
}

function collectMatches(source, pattern) {
  return new Set(Array.from(source.matchAll(pattern), (match) => match[1]));
}

function localAssetPaths(html) {
  const urls = Array.from(html.matchAll(/<(?:link|script)\b[^>]+(?:href|src)=["']([^"']+)["']/gi), (match) => match[1]);
  return urls
    .filter((url) => !/^(?:data:|https?:|\/\/|#)/i.test(url))
    .map((url) => decodeURIComponent(url.split("?")[0]))
    .filter(Boolean);
}

const pages = [
  {
    file: "index.php",
    script: "tournament-app.mjs",
    requiredIds: ["tournament-app", "standings-dialog", "standings-fullscreen-button", "standings-table-columns", "role-dialog"],
  },
  {
    file: "mobile-scorer.php",
    script: "mobile-scorer.mjs",
    requiredIds: ["phone-access-form", "phone-scorer-panel", "phone-board-select", "phone-app", "phone-winner-dialog"],
  },
  {
    file: "single-match.php",
    script: "app.mjs",
    requiredIds: ["app", "database-dialog", "resume-dialog", "winner-dialog", "database-settings-button"],
  },
];

test("all production PHP files pass syntax validation", () => {
  const files = readdirSync(PROJECT_ROOT)
    .filter((file) => file.endsWith(".php"))
    .sort();

  assert.ok(files.length > 0, "No production PHP files were found.");
  for (const file of files) {
    const result = runPhp(["-l", join(PROJECT_ROOT, file)]);
    assert.equal(result.status, 0, `${file} has invalid PHP syntax:\n${result.stderr || result.stdout}`);
    assert.match(result.stdout, /No syntax errors detected/i, `${file} was not validated by PHP lint.`);
  }
});

for (const page of pages) {
  test(`${page.file} renders without PHP diagnostics`, () => {
    const html = renderPage(page.file);
    assert.match(html, /<meta\s+name=["']viewport["']/i, `${page.file} is missing its viewport metadata.`);
    assert.match(html, /<main\b/i, `${page.file} is missing its main landmark.`);
  });

  test(`${page.file} keeps required controls and JavaScript hooks connected`, () => {
    const html = renderPage(page.file);
    const htmlIds = collectMatches(html, /\bid=["']([^"']+)["']/gi);
    const script = readFileSync(join(PROJECT_ROOT, page.script), "utf8");
    const hookedIds = collectMatches(script, /\$\(["']#([A-Za-z][\w:-]*)["']\)/g);

    for (const id of page.requiredIds) {
      assert.ok(htmlIds.has(id), `${page.file} is missing required element #${id}.`);
    }
    for (const id of hookedIds) {
      assert.ok(htmlIds.has(id), `${page.script} references missing element #${id} in ${page.file}.`);
    }
  });

  test(`${page.file} references existing local assets`, () => {
    const html = renderPage(page.file);
    for (const asset of localAssetPaths(html)) {
      assert.ok(existsSync(join(PROJECT_ROOT, asset)), `${page.file} references missing local asset ${asset}.`);
    }
  });
}

test("JavaScript module gateway maps every application entry point", () => {
  const gateway = readFileSync(join(PROJECT_ROOT, "js-module.php"), "utf8");
  const modules = [
    "app.mjs",
    "scoring-engine.mjs",
    "tournament-app.mjs",
    "tournament-engine.mjs",
    "tournament-sync.mjs",
    "single-match-sync.mjs",
    "mobile-scorer.mjs",
  ];

  for (const module of modules) {
    assert.ok(existsSync(join(PROJECT_ROOT, module)), `Missing JavaScript module ${module}.`);
    assert.match(gateway, new RegExp(`['\"]${module.replace(".", "\\.")}['\"]`), `js-module.php does not expose ${module}.`);
  }
});

test("LED-wall ranking contract remains present", () => {
  const html = renderPage("index.php");
  const css = readFileSync(join(PROJECT_ROOT, "styles.css"), "utf8");
  const app = readFileSync(join(PROJECT_ROOT, "tournament-app.mjs"), "utf8");

  assert.match(html, /id=["']standings-fullscreen-button["']/i);
  assert.match(html, /id=["']standings-table-columns["']/i);
  assert.match(css, /@media\s*\(min-width:\s*1400px\)\s*and\s*\(min-height:\s*800px\)/i);
  assert.match(css, /table-layout:\s*fixed/i);
  assert.match(app, /poster-rank/);
  assert.match(app, /poster-team/);
  assert.match(app, /poster-points/);
});
