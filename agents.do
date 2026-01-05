# AGENT INSTRUCTIONS FOR TESTING

You must test this repo in the LXC container:

  lxc exec box -- su - test
  cd ~/ginto.ai

# IMPORTANT: Any changes to the repo must be pushed, then pulled to that container for testing.

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