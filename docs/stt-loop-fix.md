STT/TTS feedback loop fix

What was happening
------------------
When STT (speech-to-text) was auto-sending transcripts and then auto-restarting monitoring while the assistant's response played back (TTS), the microphone could capture the assistant's audio. That caused the transcript to contain assistant output (for example "thank you"), which then got sent back to the assistant — producing an infinite cycle.

What we changed
----------------
- The STT stop handler now detects when a transcript matches (or is effectively the same as) the most recent assistant message and skips auto-sending in that case.
- The STT auto-restart (monitor-for-interrupt) step now checks whether audio playback is currently active — it will not auto-start if TTS playback is in progress.

Manual verification steps
-------------------------
1. Start the app and enable audio playback (if used in your UI).
2. Use a short phrase that typically triggers a short assistant reply (e.g., "thank you" or "thanks").
3. Start STT and let the assistant respond with TTS.
4. Confirm that the client does NOT continually capture the assistant's reply and re-send it.
   - Previously you'd see repeated "thank you" messages auto-sent.
   - With the fix you should see the assistant reply once and no repeated auto-sends.

Notes
-----
- The strategy is conservative — it prevents cycles by comparing the transcript to the latest assistant output. If your use case requires auto-forwarding short commands that may match recent assistant messages, we can refine the heuristic (e.g., using fuzzy similarity, minimum word count) or allow an opt-in bypass.

If you want, I can add a small automated test harness (using jsdom) to simulate this flow and assert no repeat sends — let me know and I will add that next.
