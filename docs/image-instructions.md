# Image Instructions for AI (picsum.photos placeholders)

These are guidelines for generating image HTML in the project. Use these when the AI produces UI or documentation that includes placeholder images.

## Rules

- Use `https://picsum.photos` as the image source for placeholders.
- Prefer consistent images using seeds: `https://picsum.photos/seed/{seed}/{width}/{height}` so images are reproducible across renders.
- Include a `srcset` with at least `400w`, `800w`, and `1200w` variants.
- Add `loading="lazy"` and provide meaningful `alt` text for accessibility.
- Optionally use a `<picture>` element with WebP sources for better performance.
- Use grayscale (`?grayscale`) or blur (`?blur=10`) query parameters when stylistically appropriate.

## Examples

### Simple `img` with `srcset`

```html
<img
  src="https://picsum.photos/seed/responsive1/800/600"
  srcset="
    https://picsum.photos/seed/responsive1/400/300 400w,
    https://picsum.photos/seed/responsive1/800/600 800w,
    https://picsum.photos/seed/responsive1/1200/900 1200w
  "
  sizes="(max-width: 768px) 100vw, 50vw"
  alt="Responsive placeholder image"
  loading="lazy"
  style="width:100%;height:auto;"
>
```

### `picture` with WebP source

```html
<picture>
  <source type="image/webp" srcset="https://picsum.photos/seed/responsive1/800/600.webp 800w, https://picsum.photos/seed/responsive1/1200/900.webp 1200w" sizes="(max-width: 768px) 100vw, 50vw">
  <img
    src="https://picsum.photos/seed/responsive1/800/600"
    srcset="https://picsum.photos/seed/responsive1/400/300 400w, https://picsum.photos/seed/responsive1/800/600 800w, https://picsum.photos/seed/responsive1/1200/900 1200w"
    sizes="(max-width: 768px) 100vw, 50vw"
    alt="Responsive placeholder image"
    loading="lazy"
    style="width:100%;height:auto;"
  >
</picture>
```

## Notes for Implementers

- Use predictable seeds (e.g., `seed=header-hero` or `seed=card-01`) so the same component shows the same placeholder over time.
- When the component needs a subdued image, add `?grayscale` or `?blur=10` to the URL.
- When producing production code for images, replace placeholder picsum URLs with proper CDN or asset URLs.

---

Add this file to the project's documentation and keep the examples up-to-date with any project-specific sizing patterns.