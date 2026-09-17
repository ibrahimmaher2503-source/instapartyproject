# Product

## Register

brand

## Scope

This context governs InstaParty's public Laravel and Blade storefront, beginning with the homepage and extending section by section to discovery, vendor, service, planning, authentication, and checkout surfaces.

Admin, CMS, and vendor operations are task-oriented product interfaces. They may use the product register when explicitly targeted, but they must remain the source of truth for public content and must not be visually rebuilt as part of storefront work.

## Users

InstaParty primarily serves Egyptian families, parents, couples, and hosts planning birthdays, weddings, engagements, and other celebrations. They often browse on a phone, compare unfamiliar vendors, coordinate choices with family, and need confidence about quality, price, availability, and fulfillment.

Vendors and Admin operators support that customer journey by maintaining services, media, availability, featured placements, visibility, ordering, localized copy, links, and marketplace trust. Their existing tools and data remain authoritative.

Arabic is primary and English is secondary. Arabic RTL and English LTR must feel equally composed, expressive, and complete.

## Product Purpose

InstaParty turns fragmented event planning into a trusted marketplace and guided planning experience. It helps a customer move from inspiration to a confident booking while presenting local vendors and celebration services with the quality of a premium event brand.

The public frontend must make discovery enjoyable without hiding practical information. Success means visitors understand what InstaParty offers quickly, feel inspired by the presentation, trust the marketplace, and can take the next useful action without friction.

## Content Model

Content equals Admin and CMS. Presentation equals the Blade frontend.

Editable text, images, visibility, ordering, links, featured items, localized values, and scheduled content must come from existing backend models, media handling, routes, authentication, localization, theme tokens, and CMS facilities wherever supported. Blade must not hardcode editable marketing content or create a parallel CMS.

Presentation details belong in frontend code. Layout, spacing, decorative 3D composition, gradients, glows, shadows, confetti, curves, motion, and responsive layering do not need CMS fields unless they become genuinely editable content. Missing decorative controls must not block frontend design work.

New tables, models, settings, or CMS fields require a later, specific content need and confirmation that the existing system cannot represent it. They are not part of this design context.

## Brand Personality

Premium, joyful, assured.

The experience should feel like entering a beautifully prepared celebration: rich but controlled, warm without becoming childish, and confident without becoming corporate. Copy should be welcoming, concise, and useful. Trust cues should feel integrated into the experience rather than added as compliance labels.

## Primary Reference

The supplied homepage reference image is the primary visual authority for the public frontend. Its premium event composition, deep navy structure, emerald accents, warm ivory ground, celebratory pastels, generous scale, rounded surfaces, and layered 3D party artwork outrank generic marketplace or Tailwind conventions.

When the reference does not answer a component or responsive question, follow `DESIGN.md`, then preserve consistency with already approved storefront sections. Do not reinterpret the visual language from scratch at each workflow stage.

## Anti-references

The public storefront must not resemble:

- A generic SaaS or Tailwind landing page.
- A corporate dashboard or Bootstrap admin template.
- A cheap classifieds marketplace with repetitive cards and heavy borders.
- A childish party app built from cartoons, rainbow color, or novelty type.
- A glassmorphism showcase, gradient catalogue, or animation demo.
- A flat page of identical sections with centered headings and uniform card grids.
- A CMS page builder whose configuration model is visible in the final composition.

## Design Principles

1. **Make celebration feel premium.** Use art direction, scale, depth, and generous rhythm to create desire while keeping the palette and composition controlled.

2. **Let trust and joy reinforce each other.** Present vendor quality, reviews, secure transactions, availability, and clear actions inside an inspiring experience, not as a separate corporate layer.

3. **Keep content authoritative and presentation expressive.** Read editable content from existing Admin and CMS sources. Give Blade freedom over composition and decoration without inventing content infrastructure.

4. **Treat every section as a distinct scene in one story.** Vary composition, card treatment, and visual emphasis by content type while preserving shared type, color, spacing, and interaction rules.

5. **Design Arabic and mobile as originals.** Recompose for RTL and smaller screens intentionally. Do not mirror blindly or shrink desktop art direction into an unusable stack.

6. **Spend visual complexity where it matters.** Use premium 3D artwork and motion at focal moments. Keep navigation, reading, forms, and transactional steps calm, fast, and familiar.

## Accessibility & Inclusion

Target WCAG 2.2 AA. Use semantic HTML, logical heading order, keyboard-operable controls, visible focus states, sufficient contrast, readable line lengths, and touch targets of at least 44 by 44 pixels for primary interactions.

Status and meaning must never rely on color alone. Decorative artwork should use empty alternative text or be hidden from assistive technology. Meaningful marketplace imagery needs useful localized alternative text. Respect reduced-motion preferences and preserve the full task flow when animation is disabled.

RTL affects layout, alignment, arrows, progress, navigation, carousels, card flow, and artwork balance. Arabic text must never be clipped, forced into Latin metrics, or treated as a translated afterthought.

## Delivery Workflow

Build the public frontend one section at a time. The standard sequence is `shape`, `layout`, `typeset`, `colorize`, `bolder`, `delight`, `animate`, `adapt`, `harden`, `critique`, `audit`, and `polish` on the same established direction.

Use `overdrive` only when explicitly requested and `live` for visual iteration on a specific browser element. After several approved sections or pages, use `extract` to consolidate stable tokens, buttons, cards, headings, forms, spacing patterns, and shared Blade components.
