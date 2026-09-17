---name: InstaParty Public Storefrontdescription: Premium bilingual event marketplace shaped by rich celebration imagery, confident navy structure, and selective emerald energy.colors:  navy-deep: "#111B3A"  navy: "#1C2B54"  emerald: "#087F5B"  emerald-soft: "#DDF4EA"  ivory: "#FBF7EE"  surface: "#FFFDF8"  gold: "#C5A25D"  peach: "#F4C9AF"  lavender: "#D9D2EF"  blush: "#F1CED8"  ink: "#18213A"  muted: "#6B6F7C"  border: "#E7DFD2"  success: "#227A52"  warning: "#A56A10"  danger: "#B73A4B"  info: "#3F67A6"typography:  display:    fontFamily: "Manrope, system-ui, sans-serif"    fontSize: "clamp(2.5rem, 4.5vw, 4.25rem)"    fontWeight: 800    lineHeight: 1.02    letterSpacing: "-0.045em"  headline:    fontFamily: "Manrope, system-ui, sans-serif"    fontSize: "clamp(2rem, 3.6vw, 3.5rem)"    fontWeight: 750    lineHeight: 1.08  title:    fontFamily: "Manrope, system-ui, sans-serif"    fontSize: "1.375rem"    fontWeight: 700    lineHeight: 1.25  body:    fontFamily: "Manrope, system-ui, sans-serif"    fontSize: "1rem"    fontWeight: 450    lineHeight: 1.65  arabic:    fontFamily: "Alexandria, system-ui, sans-serif"    fontSize: "1rem"    fontWeight: 450    lineHeight: 1.75rounded:  control: "16px"  card: "24px"  feature: "32px"  hero: "40px"  pill: "9999px"spacing:  xs: "4px"  sm: "8px"  md: "16px"  lg: "24px"  xl: "40px"  2xl: "64px"  3xl: "96px"  4xl: "144px"components:  button-primary:    backgroundColor: "{colors.navy-deep}"    textColor: "{colors.ivory}"    rounded: "{rounded.pill}"    padding: "14px 24px"  button-secondary:    backgroundColor: "{colors.surface}"    textColor: "{colors.navy-deep}"    rounded: "{rounded.pill}"    padding: "14px 24px"  field:    backgroundColor: "{colors.surface}"    textColor: "{colors.ink}"    rounded: "{rounded.control}"    padding: "12px 16px"  vendor-card:    backgroundColor: "{colors.surface}"    rounded: "{rounded.card}"    padding: "16px"  package-card:    backgroundColor: "{colors.ivory}"    rounded: "{rounded.feature}"    padding: "24px"---

# Design System: InstaParty Public Storefront

## Creative North Star

**The celebration atelier:** a tailored, jewel-toned, buoyant marketplace where premium event styling meets practical local booking.

The physical scene is a parent or couple planning in daylight on a phone or laptop, moving between inspiration and real purchase decisions. This calls for a warm light theme with high-legibility navy structure, not a dark interface. Ivory provides ease, navy creates confidence, emerald adds fresh momentum, and tactile 3D objects make the celebration tangible.

The supplied homepage reference image is the PRIMARY VISUAL AUTHORITY and a reconstruction target, not loose inspiration. For any section visible in the reference, reproduce its composition, hierarchy, proportions, whitespace, object placement, palette relationships, cropping, and visual rhythm as closely as practical before introducing responsive adaptation or motion. Previously approved storefront screenshots become binding references for later work. Use this document only to resolve details the reference does not show. Do not reinterpret the reference into a different landing-page concept, generic SaaS layout, luxury cake brand, portfolio, or animation showcase.

## Authority and Implementation Boundary

This system applies to the Laravel and Blade public storefront. It does not authorize a second CMS, duplicate content models, new migrations, or hardcoded editable marketing copy.

Use the existing `HomeBlock` types, homepage settings, media library, design tokens, locale routes, authentication, and backend actions. Current supported homepage blocks include hero carousel, featured occasions, featured categories, featured services, vendor spotlight, CTA banner, text and image split, testimonials, and loyalty promotion.

Admin owns editable text, media, visibility, ordering, URLs, featured records, and schedules where supported. Blade owns markup, composition, responsive behavior, visual hierarchy, decorative layers, and motion. Decorative gradients, glows, confetti, curves, shadows, and positioning stay in frontend code unless a later requirement makes them true content.

Do not expose CMS block boundaries as repetitive boxed sections. Render the same structured content with art-directed layouts appropriate to each block type.

## Color Strategy

Use a **Committed** strategy. Warm ivory carries the page, deep navy anchors major structure and primary actions, and emerald appears selectively as the lively accent. Gold and pastels create supporting celebration moments, not a rainbow component system.

Canonical CSS-authored colors should use OKLCH. The existing CMS design-token schema currently validates hex values, so use the equivalent hex values from the front matter when content-managed tokens are required. Do not change that schema during frontend design work.

| Role | OKLCH direction | Hex compatibility | Use ||---|---|---|---|| Deep navy | `oklch(0.24 0.055 267)` | `#111B3A` | Primary CTA, hero structure, high-emphasis headings || Navy | `oklch(0.31 0.063 265)` | `#1C2B54` | Navigation, secondary dark surfaces, icons || Emerald | `oklch(0.52 0.125 164)` | `#087F5B` | Select emphasis, active detail, links, small celebration cues || Warm ivory | `oklch(0.975 0.018 87)` | `#FBF7EE` | Main page ground || Soft surface | `oklch(0.992 0.009 84)` | `#FFFDF8` | Cards, fields, light overlays || Muted ink | `oklch(0.54 0.018 275)` | `#6B6F7C` | Supporting copy and metadata |

Gold, peach, lavender, and blush are supporting scene colors. Expressive package and event cards may each use a distinct, occasion-appropriate pastel identity when that color helps communicate their individual celebration world, as shown in the primary reference. Random color variation on generic marketplace cards remains prohibited. Semantic success, warning, danger, and info colors are reserved for states and always paired with text or an icon.

Do not use pure black or pure white. Avoid gradient text. Gradients may appear only as restrained background atmosphere or to integrate 3D artwork, using neighboring tones with low contrast.

## Typography

The voice is confident and editorial through scale, spacing, and composition, not through a generic luxury serif treatment.

- **English and Latin:** Manrope for display, headings, body, labels, and numbers.- **Arabic:** Alexandria for every Arabic role. Match hierarchy by optical weight and space, not by copying Latin line-height.- **Display:** 40 to 68 pixels fluid, weight 800, compact line-height, usually 8 to 13 characters per line in the hero. The hero should feel bold and confident without becoming oversized.- **Section headline:** 32 to 56 pixels fluid, strong weight contrast from body copy.- **Card title:** 20 to 24 pixels, weight 700.- **Body:** 16 to 18 pixels, maximum 68 characters per line, comfortable line-height.- **Labels and CTA text:** 14 to 16 pixels, weight 650 to 700, sentence case.

Arabic display text needs approximately 1.08 to 1.14 line-height and must be tested with real CMS copy. Avoid forced letter spacing in Arabic. Use uppercase only for short Latin labels, never for Arabic or body copy. Emerald emphasis should mark one meaningful phrase, not scatter highlights through a heading.

## Layout and Rhythm

Use a consistent max content width around 1280 pixels for text, navigation, controls, and standard marketplace content, with page gutters that grow from 20 pixels on mobile to 32 pixels on tablet and 48 to 64 pixels on desktop. Hero and other art-directed compositions may widen to 1400 to 1440 pixels. Decorative artwork may escape both the content grid and the wider composition frame when the crop is intentional.

Section spacing should vary by narrative importance:

- **Major storytelling transitions:** 80 to 120 pixels.- **Marketplace sections:** 56 to 88 pixels.- **Tightly related sections:** 40 to 64 pixels.

Use the lower end on small screens and the upper end when the composition needs more breathing room. Keep related heading and copy groups tighter than the space separating major sections.

Favor strong compositions over uniform grids. REFERENCE FIDELITY OVERRIDES GENERIC LAYOUT HEURISTICS whenever a supplied or approved screenshot exists:

This is a fidelity task before it is a creativity task. If the browser result looks materially different from the supplied reference, the section is not complete.

Do not invent badges, statistics, secondary CTAs, cards, panels, decorative copy, or alternative layouts merely to improve the design.

Do not solve composition problems by adding more UI.

Static composition must be approved before animation is added.

Favor strong compositions over uniform grids:

- Hero sections must avoid the generic equal-width SaaS split. On wide screens, the visual scene should normally carry approximately 58 to 65 percent of the composition, leaving 35 to 42 percent for content. Use asymmetry and controlled overlap without compromising the reading order.- Featured packages may use expressive editorial groupings and 3D artwork.- Vendor lists should be clean, image-led, and easy to compare.- Categories should be compact and icon-led.- Testimonials and trust content should break the card-grid rhythm.

Cards are an affordance, not the default container. Never nest cards. Avoid borders around entire sections. Use background shifts, whitespace, curves, and artwork to establish section identity.

### Section Transitions

Treat the homepage as one composed journey rather than independent rectangular sections stacked vertically. Controlled overlap, floating bridge components, artwork crossing section boundaries, display platforms, soft curves, and tonal transitions may connect adjacent scenes. Preserve clear semantic structure and sufficient breathing room even when visual layers overlap.

Transitions should clarify pacing or carry visual energy forward. Do not overlap controls, obscure headings, create focus-order confusion, or rely on fragile negative margins that fail under longer Arabic content.

### Responsive Recomposition

- **1440 desktop:** use generous whitespace, layered artwork, large type, and intentional asymmetry.- **1024 tablet:** preserve the hierarchy, reduce overlap, simplify peripheral decoration, and keep key art visible.- **390 mobile:** prioritize content and CTA order, stack deliberately, reduce 3D layer count, protect readable type, and prevent horizontal overflow.

Do not merely scale the desktop composition down. Artwork may move above, below, or behind content when that produces a stronger mobile scene. Keep interactive controls in a predictable reading and tab order.

## Shape, Depth, and Decoration

Use large, premium radii: 16 pixels for controls, 24 pixels for standard cards, 32 pixels for expressive package surfaces, and up to 40 pixels for hero or feature compositions. Pills are appropriate for primary and compact CTA buttons, filters, and status chips, but not every container.

Surfaces should feel tactile through soft tonal separation and restrained shadow. Use navy-tinted shadow color at low opacity, broad blur, and modest vertical offset. Hover lift should be no more than 2 to 4 pixels. Avoid harsh black shadows and stacked floating panels. Glassmorphism must not become a global aesthetic, but a restrained translucent or frosted treatment is allowed for genuinely floating utility surfaces such as Build Your Party when contrast and readability remain strong.

Stars, confetti, ribbons, circles, balloons, and soft abstract shapes should strengthen a focal composition or guide the eye. Never distribute decorations merely to fill empty space. Keep them away from copy, controls, and small-screen edges.

### Scene Depth

Build art-directed scenes in three deliberate planes:

1. **Background atmosphere:** restrained glows, tonal fields, soft shapes, or distant decorative elements that establish mood without competing with content.2. **Primary scene:** the dominant cake, gift, floral arrangement, package artwork, or grouped celebration composition that carries the section's visual story.3. **Foreground accents:** ribbons, balloons, confetti, flowers, or small celebration objects that create depth and connect the scene to surrounding space.

Foreground accents may intentionally crop at viewport edges, extend beyond the visual container, or cross a section boundary. Cropping must feel composed, remain stable across breakpoints, and never obstruct reading or interaction.

## 3D Art Direction

Premium 3D party objects are a signature, not a universal background. When transparent assets have been extracted from an approved reference, those assets and their reference composition are the source of truth: preserve their relative scale, hierarchy, overlap, floor contact, depth, negative space, and visual center of gravity. Do not scatter them as independent PNG stickers or substitute newly invented artwork. Use cakes, balloons, gifts, ribbons, party hats, flowers, and tasteful confetti as transparent standalone assets when possible. Favor tactile materials, soft studio light, controlled highlights, and believable depth over toy-like plastic or cartoon rendering.

Build scenes from independently positioned layers so they can recompose across breakpoints and RTL. HTML must retain all text, buttons, forms, links, prices, and other editable content. Never bake editable copy into artwork.

### Display Platform Motif

Pedestals, stages, and soft raised surfaces may selectively present important celebration artwork. Use them to give cakes, gifts, flowers, packages, or featured objects a deliberate place of honor and reinforce the celebration atelier concept. Platforms should establish scale and focus, not become a repeated base under every card or image.

The first hero image is an LCP asset: provide intrinsic dimensions, a stable aspect ratio, responsive sources where infrastructure permits, eager loading, and high fetch priority. Lazy-load below-fold art. Hide purely decorative assets from assistive technology. Limit layers on mobile and reserve space to prevent layout shift.

## Components

### Buttons

Primary CTA uses deep navy with ivory text, generous horizontal padding, a pill or strongly rounded shape, and a clear focus ring. Hover may lift by 2 pixels and slightly lighten the navy. Active state returns toward the surface. Loading and disabled states must retain legibility.

Secondary actions use a soft surface with navy text or a quiet text treatment with emerald emphasis. Keep one primary CTA hierarchy per local decision area. Do not create competing color variants.

### Cards

- **Package cards:** expressive, art-led, larger radius, selective pastel surface, space for transparent 3D objects.- **Vendor cards:** clean and image-led, with name, trust cues, location or category, rating, and action hierarchy easy to scan.- **Category cards:** compact and minimal, with one clear icon or image and a short label.

Shared card language comes from radius, typography, spacing, and interaction behavior, not identical templates. Use image aspect ratios and intrinsic dimensions to avoid layout shift.

### Forms and Navigation

Forms use familiar controls, persistent labels, calm ivory or soft-surface fields, visible navy or emerald focus treatment, and inline localized errors. Do not use placeholders as labels.

Navigation should feel light and confident. Preserve language switching, authentication, search, and planning actions from existing routes. On mobile, reduce decorative chrome and keep key actions reachable. Directional icons and drawers must follow document direction.

## Motion

Motion supports hierarchy and depth, but it must never compensate for an incorrect static composition. The initial frame at scroll position zero must remain visually faithful to the approved reference when all motion is disabled. Build and approve the static scene first; only then add motion. Use opacity and transform for gentle section reveals, small hover lift, CTA feedback, and slow floating on one or two hero objects. Typical interaction duration is 160 to 240 milliseconds with quart, quint, or expo ease-out. Large scene reveals may use 400 to 650 milliseconds.

Avoid bounce, elastic easing, constant motion across many objects, aggressive parallax, and animation of layout properties. Respect `prefers-reduced-motion`; the reduced experience must show the final state immediately and preserve carousel controls.

## RTL and Localization

Use logical spacing and positioning. Mirror arrows, carousel direction, progress connectors, drawer origin, and directional motion. Do not blindly mirror party artwork; rebalance the composition so faces, text-safe areas, shadows, and object weight still support the reading direction.

CMS text can vary substantially in length. Components must tolerate long Arabic labels, wrapped headings, missing optional fields, and mixed Latin numerals. Test every approved section at 1440, 1024, and 390 pixels in both locales.

## Accessibility and Performance Gates

Every section must meet WCAG 2.2 AA contrast, semantic heading order, keyboard access, visible focus, 44 pixel primary touch targets, meaningful alternative text, and color-independent state communication.

Set image width and height, preserve aspect ratios, use responsive images when available, lazy-load below the fold, and avoid unnecessary JavaScript. Use CSS decoration where it is cheaper than imagery, but do not replace signature artwork with generic colored rectangles. Prevent CLS and measure the first hero asset as the likely LCP candidate.

## Workflow Guardrails

Develop one section at a time. `shape` establishes the section brief, then `layout`, `typeset`, `colorize`, `bolder`, `delight`, `animate`, `adapt`, `harden`, `critique`, `audit`, and `polish` refine the same direction. Later commands must preserve approved choices unless the user explicitly changes them.

Use `overdrive` only on explicit request. Use `live` for targeted browser variants. After several sections are stable, use `extract` to consolidate real repeated patterns into tokens and shared Blade components.

Do not implement pages, add CMS fields, create migrations, or refactor backend code merely because this document describes a future presentation pattern.

Reference Fidelity Gate

Before calling any public storefront section complete, explicitly verify:

The browser result follows the supplied or previously approved reference rather than merely sharing its colors.

Focal objects have the correct relative scale, placement, overlap, and cropping.

Negative space and HTML safe areas match the intended composition.

Editable text and controls remain real HTML backed by existing content sources.

Decorative density is controlled and no invented UI has been added.

Transparent 3D assets read as one coherent environment rather than separate stickers.

The static state is convincing without animation.

RTL and mobile are intentionally recomposed.

Reduced motion preserves the complete task flow.

The section has been inspected in a headed browser and refined at least twice.

Do not accept “technically correct but visually different.” If the result materially diverges from the reference, continue iterating before reporting completion.