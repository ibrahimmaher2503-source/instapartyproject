import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import { readFileSync } from 'fs';
import {
  initializeTestEnvironment,
  RulesTestEnvironment,
  assertFails,
  assertSucceeds,
} from '@firebase/rules-unit-testing';
import { doc, getDoc, setDoc, addDoc, collection, serverTimestamp } from 'firebase/firestore';

/**
 * Firestore security-rules tests (emulator). Run with:
 *   firebase emulators:exec "npm run test:rules"
 *
 * Validates: cross-party denials, Function-only-field denials, frozen-send denial,
 * admin full access (FR-EXT-056-003 / 056-009 / SC-004).
 */
let testEnv: RulesTestEnvironment;

const THREAD = 'thread-1';
const CUSTOMER = 'user-customer';
const VENDOR = 'user-vendor';
const OTHER = 'user-other';

beforeAll(async () => {
  testEnv = await initializeTestEnvironment({
    projectId: 'instaparty-test',
    firestore: { rules: readFileSync('../firestore.rules', 'utf8') },
  });

  await testEnv.withSecurityRulesDisabled(async (ctx) => {
    await setDoc(doc(ctx.firestore(), `threads/${THREAD}`), {
      bookingId: 'BK1',
      customerId: CUSTOMER,
      vendorUserId: VENDOR,
      participantUserIds: [CUSTOMER, VENDOR],
      status: 'open',
      frozen: false,
    });
  });
});

afterAll(async () => {
  await testEnv.cleanup();
});

function ctxFor(uid: string, admin = false) {
  return testEnv.authenticatedContext(uid, admin ? { role: 'admin' } : {});
}

describe('thread access', () => {
  it('participant can read own thread', async () => {
    await assertSucceeds(getDoc(doc(ctxFor(CUSTOMER).firestore(), `threads/${THREAD}`)));
  });

  it('non-participant cannot read thread', async () => {
    await assertFails(getDoc(doc(ctxFor(OTHER).firestore(), `threads/${THREAD}`)));
  });

  it('admin can read any thread', async () => {
    await assertSucceeds(getDoc(doc(ctxFor('admin1', true).firestore(), `threads/${THREAD}`)));
  });
});

describe('message create', () => {
  it('participant can send clean message', async () => {
    const col = collection(ctxFor(CUSTOMER).firestore(), `threads/${THREAD}/messages`);
    await assertSucceeds(addDoc(col, { senderUserId: CUSTOMER, body: 'hello', createdAt: serverTimestamp() }));
  });

  it('client cannot set moderation fields', async () => {
    const col = collection(ctxFor(CUSTOMER).firestore(), `threads/${THREAD}/messages`);
    await assertFails(
      addDoc(col, { senderUserId: CUSTOMER, body: 'x', createdAt: serverTimestamp(), blocked: false }),
    );
  });

  it('non-participant cannot send', async () => {
    const col = collection(ctxFor(OTHER).firestore(), `threads/${THREAD}/messages`);
    await assertFails(addDoc(col, { senderUserId: OTHER, body: 'x', createdAt: serverTimestamp() }));
  });
});

describe('frozen thread', () => {
  it('blocks sends when frozen', async () => {
    await testEnv.withSecurityRulesDisabled(async (ctx) => {
      await setDoc(doc(ctx.firestore(), 'threads/frozen-1'), {
        bookingId: 'BK2',
        customerId: CUSTOMER,
        vendorUserId: VENDOR,
        participantUserIds: [CUSTOMER, VENDOR],
        status: 'frozen',
        frozen: true,
      });
    });
    const col = collection(ctxFor(CUSTOMER).firestore(), 'threads/frozen-1/messages');
    await assertFails(addDoc(col, { senderUserId: CUSTOMER, body: 'x', createdAt: serverTimestamp() }));
  });
});
