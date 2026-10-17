# Pickleball UI design contract

## Direction

The interface uses a court-daylight theme: white score sheets, pale green court surfaces, and a deep court-green action color. It stays compact and operational because it is used while matches are active, often at a distance or on a phone.

## Color roles

- Canvas: `#f4f7f3`
- Primary surface: `#ffffff`
- Secondary surface: `#f8faf8`
- Ink: `#17241b`
- Muted ink: `#586a5e`
- Primary action and serving state: `#147a3f`
- Secondary division green: `#2f6e58`
- Destructive state only: `#b42318`

## Typography and composition

- Use Aptos or the native Segoe/system sans-serif stack for fast, dependable rendering.
- Keep scores large and tabular; keep controls compact and explicit.
- Use white panels and fine green-gray borders rather than decorative shadows.
- Reserve uppercase treatment for the brand, short status badges, and dense table headers.

## Interaction

- Primary actions use solid green with white text.
- Selected and serving states use a pale green fill plus a border or label, never color alone.
- Keyboard focus uses a visible dark-green outline.
- Red is reserved for destructive actions and errors.
- Role identity is explicit: the desktop board is Game Master control and live score buttons exist only on the Remote Scorer phone view.
- Game Master live courts are read-only score displays with a restrained live-status footer; correction controls appear only beside completed results.
