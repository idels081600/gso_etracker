# Schedule Dashboard Design QA

## Evidence

- Source visual truth:
  - `output/references/girls-schedule-reference.png` — 1123 × 509 px
  - `output/references/boys-schedule-reference.png` — 1114 × 383 px
- Final implementation:
  - `output/playwright/schedule-dashboard-final-1366.png` — 1366 × 768 px
  - `output/playwright/girls-standings-modal-1366.png` — 1366 × 768 px
  - `output/playwright/schedule-dashboard-mobile-390.png` — 390 × 844 px responsive check
- Normalized source-versus-implementation comparison:
  - `output/playwright/schedule-design-comparison.png` — 1360 × 765 px
- Primary viewport: 1366 × 768 CSS px, device scale factor 1, screenshot captured at CSS-pixel density.
- Additional viewports: 1024 × 768 and 390 × 844 CSS px.
- State: new tournament with both divisions pending; separate interaction pass covered a live Girls game and the Girls standings modal.

## Comparison Method

The schedule regions from the 1366 × 768 implementation were cropped to 670 × 393 px per division. Each source schedule was proportionally normalized to 670 px wide and stacked directly with its matching implementation crop in `schedule-design-comparison.png`. This removes browser chrome and unrelated scoring regions from the focused comparison while retaining the complete table structure, titles, headers, pairings, and status treatment.

## Required Fidelity Surfaces

- Fonts and typography: The source uses spreadsheet-style Arial/Calibri typography. The implementation intentionally retains the existing tournament board's compact system/display typography, bold table headers, numeric alignment, and ellipsis behavior. All visible headers are readable after the final column-width correction.
- Spacing and layout rhythm: Both divisions preserve the eight-column schedule structure, compact rows, colored division header, score-column emphasis, and one row per game. The implementation uses an internal vertical scroller because each schedule shares a fixed four-panel 1366 × 768 board with live scoring above it; this is an expected product constraint rather than a fidelity defect.
- Colors and visual tokens: The green Girls and blue Boys division identities remain consistent with the existing dark-arena application. Pale score-column emphasis and orange Pending status reproduce the semantic emphasis from the spreadsheet sources without replacing the established product theme.
- Image quality and asset fidelity: The source contains no photographic, illustrative, logo, or non-standard icon assets requiring reproduction. All source information is native text/table UI, so no raster assets or placeholders were introduced into the product.
- Copy and content: Column labels, all 21 Girls pairings, all 15 Boys pairings, court numbers, division labels, Pending status, winner field, and notes field match the supplied references. The long tournament title is split into a kicker plus court heading to fit the existing half-width panel without losing content.

## Interaction And Accessibility Evidence

- Opened Girls Live Standings from the new button and verified the modal table, close-button autofocus, disabled empty-state actions, team management action, award action, and table readability.
- Activated Girls game 1 from its Pending status control and verified Joy & Irah / Born2x & Jane Yap were preselected in the match dialog.
- Started the matchup and verified schedule game 1 changed to Live with 0–0 scores and “Scoring now” notes.
- Reset the temporary QA matchup through the destructive confirmation so browser storage returned to a clean tournament state.
- Verified no page-level overflow at 1024 × 768 (`scrollWidth = clientWidth = 1024`, `scrollHeight = clientHeight = 768`).
- Verified no horizontal page overflow at 390 × 844 (`scrollWidth = clientWidth = 390`). Dense schedule tables retain their own horizontal scrolling on narrow screens.
- Browser console check: 0 errors, 0 warnings.

## Comparison History

1. Initial 1366 × 768 pass
   - P2: Game #, Score A, and Score B table headers truncated in the half-width panels.
   - Fix: reallocated fixed column widths—wider game/score columns with small reductions to team, winner, and notes columns.
   - Post-fix evidence: `schedule-dashboard-final-1366.png` and `schedule-design-comparison.png` show every header fully readable.
2. Initial 390 × 844 responsive pass
   - P2: secondary Single scorer and Fullscreen toolbar controls crowded Reset board beyond the right viewport edge.
   - Fix: hide those two secondary controls below 540 px while preserving Connect phone and Reset board.
   - Post-fix evidence: measured 390 px client width and 390 px scroll width; no horizontal page overflow remains.

## Findings

No actionable P0, P1, or P2 differences remain. The dark theme and internal table scrolling are intentional adaptations to the existing four-panel tournament board rather than unresolved mismatches.

## Serving-Player Selector Update QA

### Evidence

- Supplied reference: `output/references/server-selector-reference.png` — 468 × 56 px.
- Desktop implementation: `output/playwright/server-player-selector-dashboard.png` — 1366 × 768 px, plus focused selector crop `output/playwright/server-player-selector-desktop.png`.
- Phone implementation: `output/playwright/server-player-selector-phone.png` — 390 × 844 px, plus focused score-call crop `output/playwright/server-player-selector-phone-focus.png`.
- Combined source-versus-implementation review: `output/playwright/server-selector-design-comparison.png`.
- Density: CSS-pixel screenshots at device scale factor 1.
- State: Girls game 1, Joy & Irah versus Born2x & Jane Yap, server number 2 selected. A separate interaction pass switched service to Born2x & Jane Yap and verified the selector changed to Born2x / Jane Yap with Jane Yap marked Serving.

### Comparison And Findings

- Component structure: The compact footer location, dark control surface, nearby Reset and End actions, and yellow active-server emphasis match the supplied reference. Numeric labels were intentionally replaced with player names per the user request.
- Typography and spacing: The desktop selector remains within the existing fixed-height scoring quadrant. Long player names truncate safely instead of pushing Reset or End off-screen. The phone layout provides two equal-width 44 px minimum targets.
- Color and state: The selected player uses the established yellow serve token, dark high-contrast text, and the visible word `Serving`; the unselected player shows `Select`. Serve status is therefore not color-only.
- Copy and content: Joy / Irah and Born2x / Jane Yap are derived from their team names. Changing the serving team immediately changes both player labels. The official three-part score call retains server number 1 or 2.
- Interaction and accessibility: Both controls are native radio inputs grouped under a `Serving player` legend, have player-specific accessible labels, visible focus rings, and live announcements such as `Irah is serving.`
- Browser result: no JavaScript errors or missing assets. Chromium emitted one non-blocking password-form autofill advisory for the private scorer access-code form; it is unrelated to the server selector.
- Outcome: no actionable P0, P1, or P2 visual or interaction differences remain.

## Follow-up Polish

- P3: If the dashboard is later dedicated to a large TV instead of a laptop operator, an optional expanded court-schedule view could show all 21 Girls rows simultaneously.

final result: passed
