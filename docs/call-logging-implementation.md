# Call Logging Implementation

## Overview
This implementation adds proper call logging to the messenger system, tracking call start/end times, duration, and call type (audio/video).

## Changes Made

### 1. Database Schema Updates
**File:** `database/migrations/20260121_add_call_support_to_messages.sql`

- Added `'call'` to the `message_type` enum in `member_messages` table
- Added `payload` JSON field to store call metadata (duration, type, reason)
- Added index on `message_type` for performance

**To apply migration:**
```bash
mysql -u your_user -p your_database < database/migrations/20260121_add_call_support_to_messages.sql
```

### 2. Backend Updates

#### MessengerServer.php
**File:** `src/Playground/MessengerServer.php`

- Added `$activeCalls` array to track ongoing calls and their start times
- Updated `handleCallOffer()` to store call start time when call is initiated
- Updated `handleCallEnd()` to calculate call duration and pass it to `saveCallEvent()`
- Updated `saveCallEvent()` to accept duration parameter and store it in the `payload` field
- Enhanced call logging with duration tracking

**Key features:**
- Tracks call start time when offer is sent
- Calculates duration in seconds when call ends
- Formats duration as "X min Y sec" in message content
- Stores structured data in JSON payload field:
  ```json
  {
    "type": "audio" | "video",
    "event": "call_started" | "call_ended",
    "reason": "ended" | "declined" | "busy" | null,
    "duration_seconds": 123
  }
  ```

#### MessengerController.php
**File:** `src/Controllers/MessengerController.php`

- Updated `getMessages()` query to include the `payload` field in results
- Ensures call metadata is available to the frontend

### 3. Frontend Updates

#### messenger-multi-chat.js
**File:** `public/assets/js/messenger-multi-chat.js`

- Updated `renderMessages()` in ChatWindow class to parse and display call duration
- Shows duration for ended calls: "☎️ You Ended audio call (5 min 23 sec)"
- Shows reason if no duration: "☎️ User Ended audio call (declined)"
- Properly formats duration: minutes and seconds, or just seconds for short calls

## Call Event Flow

1. **User initiates call:**
   - Client sends `call_offer` via WebSocket
   - Server saves "call_started" event to database
   - Server tracks call start time in `activeCalls` array

2. **Call is accepted:**
   - WebRTC peer connection established
   - Call proceeds normally

3. **Call ends:**
   - Either user sends `call_end` via WebSocket
   - Server calculates duration: `time() - start_time`
   - Server saves "call_ended" event with duration to database
   - Server removes call from `activeCalls` tracking

4. **Messages displayed:**
   - Both users see call events in their conversation
   - Format: "☎️ You Started audio call"
   - Format: "📹 User Ended video call (3 min 45 sec)"

## Benefits

1. **Full Call History:** All calls are logged in the conversation between users
2. **Duration Tracking:** Accurate call duration in minutes and seconds
3. **Call Type:** Distinguishes between audio ☎️ and video 📹 calls
4. **Call Reason:** Shows why call ended (declined, busy, ended normally)
5. **User Context:** Shows who initiated and ended the call
6. **Persistent:** Call logs remain in conversation history even after refresh

## Testing

To test the implementation:

1. Apply the database migration
2. Restart the WebSocket server:
   ```bash
   # SSH to server
   ssh root@yourIP "kill $(lsof -t -i:31827) 2>/dev/null; sleep 1; su - oliverbob -c 'cd ~/ginto.ai && nohup php bin/start_rachet_stream.php > /tmp/ratchet.log 2>&1 &'"
   ```
3. Make a test call between two users
4. Verify call events appear in conversation:
   - "Started audio/video call" when call begins
   - "Ended audio/video call (duration)" when call ends
5. Check logs:
   ```bash
   tail -f storage/logs/call.log
   ```

## Notes

- Call duration is tracked in seconds on the server side
- Frontend formats duration for display (min/sec)
- If a call is declined or busy, duration will be null and reason shown instead
- Call events use the same message structure as regular messages, just with `message_type='call'`
