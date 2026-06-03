# Payables Project Guide

## Project Context
- This is a PHP payables/transmittal application served from `C:\laragon\www\gso_test\Payables`.
- Composer is used only for `vlucas/phpdotenv`; database settings come from `.env`.
- The app currently uses both PDO (`config.php`) and MySQLi (`transmit_db.php`). Follow the local file's existing database style unless a task explicitly asks for consolidation.
- Keep `.env`, credentials, logs, and generated vendor files out of normal edits unless the user asks for them.

## Codex Agents
- Parallel sub-agents are available in this project when the user explicitly asks for agents, delegation, or parallel agent work.
- Use explorer agents for focused codebase questions and worker agents for bounded implementation tasks with clearly separated file ownership.
- Do not delegate work that blocks the immediate next local step; handle that directly in the main session.

## Skills And Plugins
- Use `beautify` for UI, UX, CSS, layout, dashboard, form, or frontend polish work.
- Use the Browser plugin after meaningful frontend changes to open and verify the local page when the target URL is known.
- Use the Spreadsheets, Documents, and Presentations plugins only for spreadsheet, document, or slide deck work.
- Use `imagegen` only when the user wants generated or edited bitmap imagery.
- Use `openai-docs` for OpenAI API or product documentation questions.

## Local Workflow
- Prefer small, focused edits that match the existing plain PHP, CSS, and JavaScript structure.
- Use `rg` for searching.
- Avoid touching `vendor/` unless dependency work is explicitly requested.
- For PHP checks, use `php -l <file>` on edited PHP files when PHP is available.
- If running the app locally, assume Laragon serves it under the workspace path and verify the concrete URL before browser testing.
