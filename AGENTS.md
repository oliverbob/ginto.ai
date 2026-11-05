# AI Agent Instructions for Seller Map Zone UX

This document records the final agreed behavior of the map/zone UX in `src/Views/mall/seller_products.php`.

## UX principles (user story)

- User can search for barangay in search input and click a result (autocomplete). Map opens and centers on that result.
- User can pan/zoom the map freely. This is a new intent path and must be treated like a new search.
- When user clicks on map after panning/zooming:
  - Do not add zone automatically to selected list.
  - Set a candidate location (to `mapCandidate`) at the clicked point, based on nearest `nearby` API result.
  - Center map and update top `Nearby Zones` tags for that point.
- Top `Nearby Zones (tap to add)` buttons are the only way to commit a zone to the bottom selected zone list.
  - Clicking `+` on a tag adds to `selectedZones` and sets `homeId` as main.
- `selectedZones` is persisted only via explicit user action (`+` or Save Zones) not map clicks.
- Candidate and suggestions should not fall back to previous origin after click.

## Implementation notes

- `mapCandidate` state variable introduced.
- `dzAddSuggested` implements the commit path and sets main zone.
- Map click handler selects `d.barangays[0]` from `/api/barangay/nearby` and makes it candidate.
- `moveend` refreshes nearby suggestions from current map center.
- `renderZones` shows existing selected zones plus candidate overlay.

## For next AI

If you encounter any new requirement where the user pans/clicks to a new area, ALWAYS treat it as current location intent and avoid reusing older zone context as main. Search + pan/click are equally valid placement actions; only commit with the top list.
