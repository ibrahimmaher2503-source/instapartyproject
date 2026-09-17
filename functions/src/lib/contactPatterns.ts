/**
 * Contact-info detector — a verbatim port of
 * app/Modules/Communication/Infrastructure/Services/MessagePatternDetector.php
 *
 * Keep this in lockstep with the PHP detector. The shared test corpus
 * (test/contactPatterns.test.ts) pins the two implementations together so the
 * same input is classified identically on both the transport (Cloud Function)
 * and backend (PHP) sides — FR-EXT-056-012 / SC-002.
 *
 * Flag types mirror App\Modules\Communication\Domain\Enums\ChatFlagType.
 */

export type FlagType = 'phone' | 'email' | 'external_link';

export interface MatchedPattern {
  flagType: FlagType;
  matchedPattern: string;
}

const EASTERN_ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

// Egyptian carriers 010/011/012/015 (Latin + Eastern-Arabic, separators tolerated).
const EGYPTIAN_MOBILE = /(?:\+?20|0)?[\s.\-]*1[\s.\-]*[0125][\s.\-]*\d(?:[\s.\-]*\d){7}/u;
const INTERNATIONAL_E164 = /\+\d{10,15}/u;
const EMAIL = /[\w.+\-]+@[\w\-]+\.[\w.\-]+/iu;
const EXTERNAL_LINK = /https?:\/\/(?!(?:www\.)?instaparty\.eg)[^\s]+/iu;

function normalize(body: string): string {
  // Eastern Arabic digit normalization.
  let out = body;
  EASTERN_ARABIC_DIGITS.forEach((d, i) => {
    out = out.split(d).join(String(i));
  });
  // Strip ZWNJ (U+200C), ZWJ (U+200D), NBSP (U+00A0).
  out = out.replace(/[‌‍ ]/g, '');
  // Collapse whitespace runs.
  out = out.replace(/\s+/gu, ' ');
  return out.trim();
}

function trimSnippet(snippet: string): string {
  // chat_moderation_flags.matched_pattern is VARCHAR(255).
  return snippet.length <= 255 ? snippet : snippet.substring(0, 255);
}

/**
 * Detect curated contact-info patterns. Deduplicated by flag type (first match wins),
 * matching the PHP detector's at-most-one-entry-per-flag-type contract.
 */
export function detectContactInfo(body: string): MatchedPattern[] {
  const normalized = normalize(body);
  if (normalized === '') {
    return [];
  }

  const matches = new Map<FlagType, MatchedPattern>();

  // Phone: Egyptian carriers first, then international E.164 fallback.
  let phone = normalized.match(EGYPTIAN_MOBILE);
  if (!phone) {
    phone = normalized.match(INTERNATIONAL_E164);
  }
  if (phone) {
    matches.set('phone', { flagType: 'phone', matchedPattern: trimSnippet(phone[0]) });
  }

  const email = normalized.match(EMAIL);
  if (email) {
    matches.set('email', { flagType: 'email', matchedPattern: trimSnippet(email[0]) });
  }

  const link = normalized.match(EXTERNAL_LINK);
  if (link) {
    matches.set('external_link', { flagType: 'external_link', matchedPattern: trimSnippet(link[0]) });
  }

  return Array.from(matches.values());
}

/**
 * Most-severe flag reason for chat_message_log.flag_reason.
 * Rank mirrors DetectSuspiciousMessageJob: phone > email > external_link.
 */
export function mostSevereReason(matches: MatchedPattern[]): FlagType | null {
  if (matches.length === 0) {
    return null;
  }
  const rank: Record<FlagType, number> = { phone: 5, email: 4, external_link: 3 };
  return matches.reduce((best, m) => (rank[m.flagType] > rank[best] ? m.flagType : best), matches[0].flagType);
}
