# Bunny Stream integration (local implementation)

This integration is **not release-accepted**. Automated tests use fake Bunny HTTP responses. No real upload, encoding, playback, direct-URL denial, or authenticated browser run has been performed. Do not enable the provider until the library/dashboard and local origin are configured and the checklist below passes.

## Configuration

Copy the empty `BUNNY_STREAM_*` placeholders from `.env.example` into the private server environment. Set `BUNNY_STREAM_ENABLED=true` only after setting the library-specific API key and the **separate** Stream token authentication key. Never use the account-wide API key, a `VITE_*` variable, source control, support screenshots, or client-side storage for either key. The public library ID is `773691`; the CDN hostname is `vz-a277a1c3-8b6.b-cdn.net`. The API endpoint is restricted in code to `https://video.bunnycdn.com` to prevent an API-key exfiltration via misconfiguration. Run the new migration before enabling. With missing/invalid configuration, uploads and signed playback fail closed.

`BUNNY_STREAM_MAX_UPLOAD_MEGABYTES` controls the client metadata limit (default 2048 MiB); video bytes go directly from the browser to Bunny over TUS, not through PHP. The browser and server validate the declared extension, MIME type, and size, but the server cannot inspect bytes in a direct-to-provider upload. Only a Bunny video that reaches status **3 (finished)** becomes playable. Bunny status **4** (first resolution finished) is deliberately still treated as processing.

`BUNNY_STREAM_PLAYBACK_TTL_SECONDS` accepts 30–300 seconds (default 120). `BUNNY_STREAM_UPLOAD_SIGNATURE_TTL_SECONDS` accepts 1–24 hours (default 24). Changing the signature lifetime does not necessarily extend an existing TUS resource; an expired upload may require a new TUS resource for the same video GUID. Playback tokens already issued remain valid until expiry even if learner access is later revoked. Upload-scoped signatures likewise remain usable until expiry after an author's permission changes; remove the affected video in Bunny if urgent revocation is required. Shorter TTL reduces those windows.

## Bunny dashboard compatibility

Configure the intended Stream library: disable Direct Play; enable **Block Direct URL File Access**, **Embed View Token Authentication**, and **CDN Token Authentication**; do not enable paid Multi-DRM. Confirm Frankfurt primary storage in the Bunny dashboard (code cannot verify it with a library-scoped key). Configure allowed embed domains for the exact local and eventual production hostnames and test both the iframe and its media requests. An empty/incorrect allowed-domain list plus blocked direct URLs can produce a 403 even with a valid embed token. Configure Bunny's documented TUS CORS support for the HTTPS site origin and ensure `Location` and `Upload-Offset` response headers are exposed. Do not disable token protection to solve CORS or 403 errors.

The signed iframe is `https://player.mediadelivery.net/embed/{libraryId}/{guid}?token=...&expires=...` with SHA-256 over the **token authentication key + GUID + Unix expiry**. It is not a direct CDN MP4/HLS link. CDN token authentication for direct file/segment URLs is a separate scheme; a query token on an HLS manifest does not automatically protect segment requests. This app does not generate direct CDN media URLs and uses the Bunny Player iframe. An iframe token is not DRM and does not prevent screen recording.

## Author flow and cleanup

Save a paid video lesson as a draft, then reopen it to upload. The author must have effective curriculum-update permission and either be the assigned instructor or have the global course-publish permission. The server creates one Bunny video for a stable request UUID; retrying the same request returns a newly signed TUS authorization for the same GUID. The browser stores only a request UUID and TUS resource URL in session storage to resume after reselecting the same file. It never receives the library API key or token key. The server polls Bunny's video API after upload; no webhook is configured.

An ambiguous provider-create failure is marked `uncertain`, blocks a duplicate creation, and needs manual reconciliation in the Bunny dashboard against the time/title before retrying. This avoids silently creating billable orphan videos after a timeout. Failed uploads must be removed via the lesson UI before another upload. Replacement keeps the current ready video playable until the new video is fully ready, then retires the previous video and attempts remote deletion. If deletion fails, the record is `cleanup_failed`; retry the authorized remove endpoint for that upload. Lesson, section, and course API deletion is blocked while any non-deleted video upload remains. Other deletion paths and provider-side billing inventory still require operator reconciliation before production use; no automatic remote delete is triggered by arbitrary model deletion. Do not delete a video manually in Bunny without reconciling its lesson record.

The server's upload metadata validation cannot guarantee the actual direct-upload bytes. Provider processing failure must be handled as failed content and removed. Monitor Bunny usage, storage, encoding, and bandwidth costs in the Bunny dashboard; do not assume testing is free.

## Manual local acceptance (not yet performed)

1. With a real authorized instructor account, upload a small MP4 in the local HTTPS site and confirm TUS progress, interruption/reselection resume, and Bunny processing to ready.
2. Preview the ready video as the assigned author. Replace it and verify the original remains available until the new video is ready, then confirm remote cleanup or `cleanup_failed` recovery.
3. Enroll a student with a current grant; verify signed iframe playback and no exposed API/token key in browser network responses.
4. Use a different unenrolled student; expect denial. Repeat after grant expiry, suspension, course unpublishing, lesson unpublishing, and section deactivation.
5. Verify a direct unsigned CDN/MP4/HLS URL is denied by Bunny. Verify the signed iframe still plays with the intended Direct Play, direct-file block, embed-token, CDN-token, and allowed-domain settings simultaneously.
6. Test Arabic RTL and English LTR, mobile and desktop widths, player loading/error states, and that loading or watching alone does not mark a lesson complete.
7. Reconcile Bunny video inventory and billable orphan records, then record real-provider results before declaring acceptance.

Troubleshooting: 503 from the app means provider configuration or Bunny API access is unavailable; 403 in Bunny player often indicates token-key mismatch, expired token, or allowed-domain/direct-file settings; CORS failures on `/tusupload` indicate Bunny origin/headers setup; a stuck processing state requires checking the Bunny video status and encode errors. Do not log or paste keys or signed URLs. For rollback, set `BUNNY_STREAM_ENABLED=false` (playback fails closed), stop author uploads, keep the database migration and GUIDs for reconciliation, and delete remote videos only after inventory review. Rolling back the migration destroys reconciliation records and is not a safe routine rollback.

Official references: [resumable uploads](https://bunny.net/docs/stream/tus-resumable-uploads), [token authentication](https://bunny.net/docs/stream/token-authentication), [security options](https://bunny.net/docs/stream/security-options), [video HTTP API](https://bunny.net/docs/stream/http-api).
