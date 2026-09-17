import { onRequest } from 'firebase-functions/v2/https';
import { logger } from 'firebase-functions/v2';
import * as admin from 'firebase-admin';

/**
 * Identity bridge: exchange a verified platform session for a Firebase custom token.
 *
 * Called server-to-server by the Next.js /api/proxy/firebase-token route, which
 * holds the HttpOnly Sanctum token and first resolves the current user from the
 * Laravel API. The proxy posts { userId, isAdmin } here with the shared secret.
 *
 * The minted token has uid == users.id (string) and a custom claim role='admin'
 * for admins, which firestore.rules consume. No Firebase Auth schema or
 * userProfiles collection is touched (constraint).
 */
export const mintChatToken = onRequest(
  { region: 'europe-west1', cors: false },
  async (req, res) => {
    if (req.method !== 'POST') {
      res.status(405).json({ error: 'method_not_allowed' });
      return;
    }

    const secret = process.env.MINT_TOKEN_SECRET;
    if (!secret || req.get('X-Cloud-Function-Secret') !== secret) {
      res.status(401).json({ error: 'unauthorized' });
      return;
    }

    const userId = req.body?.userId;
    const isAdmin = req.body?.isAdmin === true;
    if (userId === undefined || userId === null || String(userId) === '') {
      res.status(422).json({ error: 'missing_user_id' });
      return;
    }

    try {
      const claims = isAdmin ? { role: 'admin' } : {};
      const token = await admin.auth().createCustomToken(String(userId), claims);
      res.status(200).json({ token });
    } catch (err) {
      logger.error('createCustomToken failed', { err: String(err) });
      res.status(500).json({ error: 'token_mint_failed' });
    }
  },
);

if (admin.apps.length === 0) {
  admin.initializeApp();
}
