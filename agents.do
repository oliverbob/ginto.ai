# AGENT INSTRUCTIONS FOR TESTING

You must test this repo in the LXC container:

  lxc exec box -- su - test
  cd ~/ginto.ai

# IMPORTANT: Any changes to the repo must be pushed, then pulled to that container for testing.

# DEPLOY SHORTCUT:
git add -A && git commit -m "msg" && git push && lxc exec box -- su - test -c "cd ~/ginto.ai && git pull" && lxc exec box -- systemctl restart php8.4-fpm

# CURL TEST (from within box):
lxc exec box -- curl -s "http://127.0.0.1:8000/api/sandbox/openwebui/status" | jq .

# ANTI RATE-LIMIT GUIDELINES
# To avoid hitting response length limits:
# 1. Create large files in smaller chunks (split controllers/views into multiple create_file calls)
# 2. Don't read entire large files when only a portion is needed
# 3. When creating controllers with many methods, create base structure first, then add methods incrementally
# 4. Prefer using replace_string_in_file for edits rather than rewriting entire files
# 5. Keep tool call responses focused - don't include unnecessary context in prompts

Workflow:
1. Make changes to the repo
2. Push changes to git
3. Pull changes in the container for testing
4. Logs are found in ~/ginto.ai/../storage/logs/
5. Always prioritize security in your design to avoid vulnerabilites

# FORM SECURITY GUIDELINES
# When creating or modifying forms:
# 1. ALWAYS sanitize ALL user inputs on the server side:
#    - Use strip_tags() for text fields (names, descriptions)
#    - Use preg_replace('/[^a-zA-Z0-9_\-]/', '', $input) for usernames/identifiers
#    - Use FILTER_SANITIZE_EMAIL for email fields
#    - Use (int) or (float) casting for numeric inputs
# 2. ALWAYS escape output in views to prevent XSS:
#    - Use htmlspecialchars($var, ENT_QUOTES, 'UTF-8') or an esc() helper function
#    - In JavaScript, escape dynamic content before inserting into DOM
# 3. ALWAYS use CSRF tokens for state-changing operations (POST, PUT, DELETE)
# 4. ALWAYS validate data types and ranges server-side (don't trust client validation)
# 5. Use prepared statements / Medoo's parameterized queries - NEVER concatenate SQL
# 6. For file uploads: validate MIME type, extension, and scan content; store outside webroot

Example:


  On host - after making changes:
    git add . && git commit -m "your message" && git push

  In container - to test:
    lxc exec box -- su - test -c "cd ~/ginto.ai && git pull"
    after pushing there, you need to do "docker compose restart". It will restart 10 seconds.
    If possible, test your work with curl.
    If you're working on endpoints, test them.
    If its chat endpoints test some prompts.
    For credentials read .env in the lxc.
    Read this instruction everytime as part of your last task.