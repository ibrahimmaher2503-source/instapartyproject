import { onDocumentCreated } from 'firebase-functions/v2/firestore';
import { logger } from 'firebase-functions/v2';
import * as admin from 'firebase-admin';
import { detectContactInfo, mostSevereReason } from './lib/contactPatterns';

/**
 * Fires when either party writes a message into a thread.
 *
 *  1. Runs contact-info detection (parity with MessagePatternDetector.php).
 *  2. If matched: redacts the doc in place ({ body: '', blocked, redacted, flagReason })
 *     BEFORE the peer's snapshot renders content — auto-blocking (FR-EXT-056-005).
 *  3. Mirrors the final message (clean or redacted) into the Laravel backend
 *     (chat_message_log + flags) via the internal mirror endpoint, idempotent on
 *     the Firestore message id (FR-EXT-056-007).
 *
 * Admin SDK writes bypass security rules, which is why the redaction update is allowed.
 */
export const onChatMessageCreated = onDocumentCreated(
  {
    document: 'threads/{threadId}/messages/{messageId}',
    region: 'europe-west1',
    secrets: [],
  },
  async (event) => {
    const snap = event.data;
    if (!snap) {
      return;
    }

    const { threadId, messageId } = event.params;
    const data = snap.data();
    const body: string = typeof data.body === 'string' ? data.body : '';
    const senderUserId: string = String(data.senderUserId ?? '');

    const matches = detectContactInfo(body);
    const blocked = matches.length > 0;
    const flagReason = blocked ? mostSevereReason(matches) : null;

    if (blocked) {
      // Redact in place so the contact info never reaches the peer.
      await snap.ref.update({
        body: '',
        blocked: true,
        redacted: true,
        flagReason,
      });
      logger.info('chat message blocked', { threadId, messageId, flagReason });
    }

    // Mirror to the backend (final state). Empty body when blocked.
    const mirrorUrl = process.env.BACKEND_MIRROR_URL;
    const secret = process.env.BACKEND_MIRROR_SECRET;
    if (!mirrorUrl || !secret) {
      logger.warn('mirror endpoint not configured; skipping mirror', { threadId, messageId });
      return;
    }

    try {
      const res = await fetch(mirrorUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Cloud-Function-Secret': secret,
        },
        body: JSON.stringify({
          firestore_thread_id: threadId,
          firestore_message_id: messageId,
          sender_user_id: senderUserId,
          body: blocked ? '' : body,
          blocked,
          flag_reason: flagReason,
          matched_patterns: matches.map((m) => ({
            flag_type: m.flagType,
            matched_pattern: m.matchedPattern,
          })),
        }),
      });
      if (!res.ok) {
        logger.error('mirror call failed', { status: res.status, threadId, messageId });
      }
    } catch (err) {
      // Non-fatal: the message is already delivered/redacted in Firestore.
      logger.error('mirror call threw', { err: String(err), threadId, messageId });
    }
  },
);

// Lazy init guard for emulator + deploy.
if (admin.apps.length === 0) {
  admin.initializeApp();
}
