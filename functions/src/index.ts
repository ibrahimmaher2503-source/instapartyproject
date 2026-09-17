import * as admin from 'firebase-admin';

if (admin.apps.length === 0) {
  admin.initializeApp();
}

export { onChatMessageCreated } from './onMessageCreated';
export { mintChatToken } from './mintChatToken';
