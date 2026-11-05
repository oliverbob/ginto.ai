# Editor Assistant Composer — Design Spec (VS Code-like, pixel targets)

This document captures pixel-level tokens and visual specifications to make the assistant composer match VS Code's design.

## Baseline font & metrics
- Font-size: 13px
- Line-height: 1.35 (approx 17.55px)
- Gutter / base spacing: 8px

## Composer container
- Border: 1px solid rgba(255,255,255,0.06) (dark theme), fallback rgba(0,0,0,0.06) for light
- Background: inherits editor surface (dark-neutral)
- Padding: 8px (top/right/left) / 8px bottom (compact)
- Min-height: 40px (desktop), 140px for "composer-small" narrow mode
- Top corner radius: 0
- Bottom corner radius: 3px (subtle)
- Focus ring: 0 0 0 1px rgba(0,122,204,0.08) (outer stroke), subtle drop shadow on active only

## Textarea (body)
- Min-height: 1.1em (~one-line) — use computed fallback when necessary
- Max-height: 400px
- Padding: 12px
- Resize: none
- Overflow-y: auto when height > max-height
- Background: transparent (parent provides visible box)
- Placeholder color: rgba(255,255,255,0.45) (muted)

## Header (top) — attachments
- Small row inside composer, not separate surface
- File chip:
  - Height: 28px
  - Border-radius: 6px
  - Padding: 6px 10px
  - Border: 1px solid rgba(255,255,255,0.06)
  - Icon (paperclip) size: 16px
  - File tag: small square 26x20, font-weight:700, color accent (e.g., #fbbf24 for HTML)
- Add button: 28x28px, 1px border, 6px radius

## Footer
- Height: 44–48px for send button
- Footer padding: 8px
- Send button size: 48x48px (desktop), 36x36 (compact)
- Send button radius: 8px
- Send hover: slight upward transform (-4px) and shadow

## Responsive behavior
- composer-small: min-height 140px, overlay paddings adjusted
- composer-tiny: collapse labels into icons, reduce left indentation to 56px

## Accessibility
- Parent container has focus-within focus ring
- Textarea has aria-label and role="textbox"
- Buttons have aria-label and keyboard accessible

---

Use these tokens in CSS variables so we can tweak centrally and achieve pixel-perfect parity across sizes.
