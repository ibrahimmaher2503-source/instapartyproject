# InstaParty Chat — Cloud Functions

Firestore is the real-time transport for the restricted customer↔vendor chat. The
durable system of record (moderation, audit) stays in MySQL via the Laravel
`Communication` module. These functions bridge the two and enforce contact-info
blocking at write time.

## Firestore document shape

```
threads/{firestoreThreadId}
  bookingId            string   # bookings.public_id
  customerId           string   # users.id
  vendorUserId         string   # users.id
  participantUserIds   string[] # [customerId, vendorUserId]  (rules array-contains)
  status               string
  frozen               boolean

threads/{firestoreThreadId}/messages/{messageId}
  senderUserId   string
  body           string
  createdAt      timestamp (serverTimestamp)
  blocked        boolean   # Cloud-Function only
  redacted       boolean   # Cloud-Function only
  flagReason     string|null  # phone|email|external_link  (Cloud-Function only)
```

## Functions

- `onChatMessageCreated` — Firestore `onDocumentCreated` trigger. Runs
  `detectContactInfo` (parity with `MessagePatternDetector.php`); redacts the doc
  in place when contact info is found; mirrors the final message to the backend
  internal endpoint (`BACKEND_MIRROR_URL`, `X-Cloud-Function-Secret`).
- `mintChatToken` — HTTPS function. Mints a Firebase custom token (`uid=users.id`,
  `role=admin` claim for admins) from a verified platform session. Called by the
  Next.js `/api/proxy/firebase-token` route; guarded by `MINT_TOKEN_SECRET`.

## Config / secrets

```
firebase functions:secrets:set BACKEND_MIRROR_URL      # https://api.instaparty.eg/api/v1/internal/chat/mirror-message
firebase functions:secrets:set BACKEND_MIRROR_SECRET   # == CLOUD_FUNCTION_SHARED_SECRET in Laravel .env
firebase functions:secrets:set MINT_TOKEN_SECRET
```

## Develop / test / deploy

```
npm install
npm run build
npm test                  # contact-pattern parity
firebase emulators:exec "npm run test:rules"   # security rules
firebase deploy --only functions:chat
firebase deploy --only firestore:rules,firestore:indexes
```

Keep `src/lib/contactPatterns.ts` in lockstep with the PHP detector — the shared
test corpus exists to catch drift.
