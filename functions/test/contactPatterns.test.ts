import { describe, it, expect } from 'vitest';
import { detectContactInfo, mostSevereReason } from '../src/lib/contactPatterns';

/**
 * Shared corpus — keep aligned with the PHP MessagePatternDetector test cases so
 * both runtimes classify identically (SC-002 / FR-EXT-056-012).
 */
describe('detectContactInfo', () => {
  it('detects Egyptian mobile (Latin digits)', () => {
    const m = detectContactInfo('call me on 0100 123 4567 please');
    expect(m.map((x) => x.flagType)).toContain('phone');
  });

  it('detects Egyptian mobile (Eastern-Arabic digits)', () => {
    const m = detectContactInfo('رقمي ٠١٠٠١٢٣٤٥٦٧');
    expect(m.map((x) => x.flagType)).toContain('phone');
  });

  it('detects international E.164', () => {
    const m = detectContactInfo('reach me at +447911123456');
    expect(m.map((x) => x.flagType)).toContain('phone');
  });

  it('detects email', () => {
    const m = detectContactInfo('email me at vendor@gmail.com');
    expect(m.map((x) => x.flagType)).toContain('email');
  });

  it('detects external link but not instaparty.eg', () => {
    expect(detectContactInfo('see https://wa.me/123').map((x) => x.flagType)).toContain('external_link');
    expect(detectContactInfo('see https://instaparty.eg/x')).toHaveLength(0);
  });

  it('passes clean text', () => {
    expect(detectContactInfo('When do you arrive tomorrow?')).toHaveLength(0);
  });

  it('returns empty for whitespace', () => {
    expect(detectContactInfo('   ')).toHaveLength(0);
  });

  it('dedupes to one entry per flag type', () => {
    const m = detectContactInfo('a@b.com and c@d.com');
    expect(m.filter((x) => x.flagType === 'email')).toHaveLength(1);
  });

  it('ranks phone over email', () => {
    const m = detectContactInfo('0100 123 4567 or me@x.com');
    expect(mostSevereReason(m)).toBe('phone');
  });
});
