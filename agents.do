# AGENT INSTRUCTIONS FOR TESTING

You must test this repo in the LXC container:

  lxc exec docker-test -- su - test
  cd ~/ginto.ai

# IMPORTANT: Any changes to the repo must be pushed, then pulled to that container for testing.

Workflow:
1. Make changes to the repo
2. Push changes to git
3. Pull changes in the container for testing

Example:

  On host - after making changes:
    git add . && git commit -m "your message" && git push

  In container - to test:
    lxc exec docker-test -- su - test -c "cd ~/ginto.ai && git pull"
    after pushing there, you need to do "docker compose restart". It will restart 10 seconds.
    If possible, test your work with curl.
    If you're working on endpoints, test them.
    If its chat endpoints test some prompts.
    Read this instruction everytime as part of your last task.